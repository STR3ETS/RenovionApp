<?php

namespace App\Http\Controllers;

use App\Actions\CreateTask;
use App\Actions\ProcessMeeting;
use App\Enums\MeetingKind;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ChatChannel;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingReporter;
use App\Services\Transcription\Transcriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gespreksverslagen (Nova notuleert): de telefoon neemt op en stuurt het geluid in stukjes van een paar
 * seconden, zodat een weggevallen verbinding of een lege accu hooguit het laatste stukje kost. Na Stop
 * transcribeert de dienst het (EU) en maakt Nova het verslag; acties worden pas taken na aanvinken.
 */
class MeetingController extends Controller
{
    private const MAX_BYTES = 300 * 1024 * 1024;

    public function index(Request $request): View
    {
        return view('meetings.index', [
            'meetings' => Meeting::visibleTo($request->user())->with(['project', 'user'])->latest('started_at')->paginate(30),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('meetings.create', [
            'projects' => Project::active()
                ->when($user->cannot('manage-crm'), fn ($query) => $query->whereHas('craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id)))
                ->orderBy('name')->get(['id', 'name']),
            'selectedProject' => $request->integer('project') ?: null,
            'kinds' => MeetingKind::cases(),
        ]);
    }

    /** Opname begint: het verslag bestaat meteen, het geluid volgt in stukjes. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(MeetingKind::class)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'consent' => ['accepted'],
            'mime' => ['nullable', 'string', 'max:100'],
        ]);

        $project = isset($validated['project_id']) ? Project::find($validated['project_id']) : null;
        abort_if($project && ! $project->isAccessibleBy($request->user()), 403);

        $mime = str($validated['mime'] ?? 'audio/webm')->before(';')->lower()->toString();
        $extension = match ($mime) {
            'audio/mp4', 'audio/aac', 'audio/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            default => 'webm',
        };

        $meeting = Meeting::create([
            'title' => $validated['title'],
            'kind' => $validated['kind'],
            'project_id' => $project?->id,
            'user_id' => $request->user()->id,
            'status' => MeetingStatus::Opname,
            'consent_at' => now(),
            'started_at' => now(),
            'audio_mime' => $mime,
        ]);
        $meeting->update(['audio_path' => 'meetings/'.$meeting->id.'/opname.'.$extension]);

        AuditLog::record($meeting, 'opname_gestart', [], ['titel' => $meeting->title, 'toestemming' => 'bevestigd']);

        return response()->json([
            'id' => $meeting->id,
            'chunk_url' => route('meetings.chunk', $meeting),
            'stop_url' => route('meetings.stop', $meeting),
            'show_url' => route('meetings.show', $meeting),
        ], 201);
    }

    /**
     * Een stukje geluid. Op volgorde: seq moet het volgende stuk zijn. Al ontvangen = gewoon oké (de
     * telefoon stuurde opnieuw na een time-out), te ver vooruit = 409 met wat er verwacht wordt.
     */
    public function chunk(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);
        abort_unless($meeting->status === MeetingStatus::Opname, 422, 'Deze opname is al gestopt.');

        $validated = $request->validate([
            'seq' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:20480'],
        ]);

        if ($validated['seq'] < $meeting->audio_chunks) {
            return response()->json(['received' => $meeting->audio_chunks]);
        }
        if ($validated['seq'] > $meeting->audio_chunks) {
            return response()->json(['received' => $meeting->audio_chunks], 409);
        }
        abort_if($meeting->audio_bytes > self::MAX_BYTES || $meeting->started_at->diffInMinutes(now()) > config('renovion.transcription.max_minutes') + 10, 422, 'De opname is te lang geworden. Stop en begin een nieuwe.');

        Storage::makeDirectory(dirname($meeting->audio_path));
        file_put_contents(Storage::path($meeting->audio_path), $request->file('chunk')->get(), FILE_APPEND | LOCK_EX);

        $meeting->update([
            'audio_chunks' => $meeting->audio_chunks + 1,
            'audio_bytes' => filesize(Storage::path($meeting->audio_path)),
            'duration_seconds' => (int) $meeting->started_at->diffInSeconds(now()),
        ]);

        return response()->json(['received' => $meeting->audio_chunks]);
    }

    public function stop(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeOwner($request, $meeting);

        $validated = $request->validate(['duration' => ['nullable', 'integer', 'min:0', 'max:86400']]);

        if ($meeting->status === MeetingStatus::Opname) {
            $meeting->update([
                'status' => MeetingStatus::Verwerken,
                'ended_at' => now(),
                'duration_seconds' => $validated['duration'] ?? $meeting->duration_seconds,
            ]);
            AuditLog::record($meeting, 'opname_gestopt', [], ['duur' => gmdate('H:i:s', $meeting->duration_seconds)]);
        }

        return response()->json(['url' => route('meetings.show', $meeting)]);
    }

    public function show(Request $request, Meeting $meeting, Transcriber $transcriber, MeetingReporter $reporter): View
    {
        abort_unless($meeting->isAccessibleBy($request->user()), 403);

        $meeting->load(['project.customer', 'user']);

        return view('meetings.show', [
            'meeting' => $meeting,
            // Wacht de verwerking op een sleutel? Dan zegt het verslag welke, in plaats van eindeloos "bezig".
            'waitingFor' => match (true) {
                $meeting->status !== MeetingStatus::Verwerken => null,
                $meeting->transcript === null && ! $transcriber->isConfigured() => 'De transcriptiedienst staat nog niet aan (sleutel ontbreekt).',
                ! $reporter->isConfigured() => 'Het gesprek is omgezet naar tekst, maar Nova staat nog niet aan (sleutel ontbreekt).',
                default => null,
            },
            'team' => User::where('role', '!=', UserRole::Klant)->orderBy('name')->get(['id', 'name']),
            'channels' => ChatChannel::forUser($request->user()),
        ]);
    }

    /**
     * Het verslag kijkt elke paar seconden of het klaar is; daarbij gaat de verwerking ook een stap verder,
     * maar pas ná het antwoord: Whisper zet een lange opname in één keer om en het verslag van Nova kan ook
     * even duren. Een verzoek dat zo lang open staat, breekt de webserver af (504). De pagina vraagt 5 s later
     * opnieuw en ziet dan of het klaar is.
     */
    public function status(Request $request, Meeting $meeting, ProcessMeeting $process): JsonResponse
    {
        abort_unless($meeting->isAccessibleBy($request->user()), 403);

        if ($meeting->status === MeetingStatus::Verwerken) {
            defer(function () use ($process, $meeting) {
                ignore_user_abort(true);
                $process->handle($meeting);
            }, 'meeting-process-'.$meeting->id);
        }

        return response()->json(['status' => $meeting->status->value, 'label' => $meeting->status->label()]);
    }

    public function audio(Request $request, Meeting $meeting): BinaryFileResponse
    {
        abort_unless($meeting->isAccessibleBy($request->user()), 403);
        abort_unless($meeting->hasAudio() && Storage::exists($meeting->audio_path), 404);

        return response()->file(Storage::path($meeting->audio_path), [
            'Content-Type' => $meeting->audio_mime ?? 'audio/webm',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Aangevinkte acties worden taken (eigenaar en datum zoals de gebruiker ze zette). */
    public function tasks(Request $request, Meeting $meeting, CreateTask $createTask): RedirectResponse
    {
        abort_unless($meeting->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.selected' => ['nullable', 'boolean'],
            'actions.*.title' => ['required', 'string', 'max:255'],
            // Alleen iemand van het team, nooit een klantaccount (het formulier biedt ook alleen het team aan).
            'actions.*.owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNot('role', UserRole::Klant->value)],
            'actions.*.deadline' => ['nullable', 'date'],
        ]);

        $actions = $meeting->actions ?? [];
        $created = 0;
        foreach ($validated['actions'] as $index => $input) {
            if (empty($input['selected']) || ! isset($actions[$index]) || $actions[$index]['task_id'] !== null) {
                continue;
            }
            $task = $createTask->handle([
                'title' => $input['title'],
                'note' => 'Uit gespreksverslag "'.$meeting->title.'" van '.$meeting->started_at->translatedFormat('j M Y').'.',
                'project_id' => $meeting->project_id,
                'owner_id' => $input['owner_id'] ?? null,
                'deadline' => $input['deadline'] ?? null,
            ], $request->user());
            $actions[$index] = [...$actions[$index], 'title' => $input['title'], 'task_id' => $task->id];
            $created++;
        }
        $meeting->update(['actions' => $actions]);

        return back()->with('success', $created === 0 ? 'Geen nieuwe taken gekozen.' : ($created === 1 ? '1 taak aangemaakt.' : $created.' taken aangemaakt.'));
    }

    /** Nova deelt de samenvatting in een kanaal, met een link naar het verslag. */
    public function share(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->isAccessibleBy($request->user()) && $meeting->status === MeetingStatus::Klaar, 403);

        $channel = ChatChannel::findOrFail($request->validate(['channel_id' => ['required', 'integer']])['channel_id']);
        abort_unless($channel->isAccessibleBy($request->user()), 403);

        $lines = collect([
            'Gespreksverslag "'.$meeting->title.'" ('.$meeting->started_at->translatedFormat('j M').'), gedeeld door '.$request->user()->name.'.',
            $meeting->summary,
            $meeting->decisions ? 'Besloten: '.implode('; ', $meeting->decisions).'.' : null,
            $meeting->actions ? count($meeting->actions).' '.(count($meeting->actions) === 1 ? 'actie' : 'acties').' en '.count($meeting->questions ?? []).' open '.(count($meeting->questions ?? []) === 1 ? 'vraag' : 'vragen').'.' : null,
        ])->filter()->implode("\n\n");

        $channel->messages()->create([
            'user_id' => null,
            'body' => $lines,
            'nova' => ['executed' => true, 'url' => route('meetings.show', $meeting, false)],
        ]);

        return back()->with('success', 'Gedeeld in '.$channel->displayName().'.');
    }

    public function retry(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->isAccessibleBy($request->user()) && $meeting->status === MeetingStatus::Mislukt, 403);

        $meeting->update(['status' => MeetingStatus::Verwerken, 'error' => null, 'attempts' => 0, 'provider_id' => $meeting->transcript === null ? null : $meeting->provider_id]);
        ProcessMeeting::clearBackoff($meeting);

        return back()->with('success', 'Nova probeert het opnieuw.');
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm') || $meeting->user_id === $request->user()->id, 403);

        AuditLog::record($meeting, 'verwijderd', ['titel' => $meeting->title], []);
        if ($meeting->audio_path) {
            Storage::deleteDirectory(dirname($meeting->audio_path));
        }
        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Verslag en opname verwijderd.');
    }

    private function authorizeOwner(Request $request, Meeting $meeting): void
    {
        abort_unless($meeting->user_id === $request->user()->id, 403);
    }
}
