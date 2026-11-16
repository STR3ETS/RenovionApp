<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\MeetingKind;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingReporter;
use App\Services\Transcription\Transcriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gespreksverslagen in de app (mobile/app/screens/verslagen.*): de lijst, het opnamescherm en het verslag,
 * met dezelfde gegevens als meetings/index, create en show. Opnemen (start, geluid, stoppen, status) en de
 * acties (taken, delen, opnieuw, verwijderen) gaan via de MeetingController van de website, die JSON geeft.
 * De opname zelf komt voor <audio> via een ondertekend adres (de app heeft geen sessiecookie).
 */
class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $meetings = Meeting::visibleTo($request->user())->with(['project', 'user'])->latest('started_at')->paginate(30);

        return response()->json([
            'total' => $meetings->total(),
            'page' => $meetings->currentPage(),
            'more' => $meetings->hasMorePages(),
            'meetings' => $meetings->getCollection()->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'sub' => collect([$meeting->kind->label(), $meeting->project?->name, $meeting->started_at->translatedFormat('j M Y H:i'), $this->duration($meeting)])->filter()->implode(', '),
                'status' => [
                    'value' => $meeting->status->value,
                    'label' => $meeting->status === MeetingStatus::Verwerken ? 'Bezig' : $meeting->status->label(),
                    'classes' => $meeting->status->appBadgeClasses(),
                ],
                'path' => route('meetings.show', $meeting, false),
            ])->values(),
        ]);
    }

    /** Het opnamescherm: projecten om aan te koppelen (uitvoerder alleen de eigen), soorten en de uitleg. */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'projects' => Project::active()
                ->when($user->cannot('manage-crm'), fn ($query) => $query->whereHas('craftsmen', fn ($craftsmen) => $craftsmen->where('users.id', $user->id)))
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name])->values(),
            'kinds' => array_map(fn (MeetingKind $kind) => ['value' => $kind->value, 'label' => $kind->label()], MeetingKind::cases()),
            'processor' => config('renovion.transcription.driver') === 'assemblyai' ? 'in de EU' : 'door OpenAI',
            'retention_days' => (int) config('renovion.transcription.audio_retention_days'),
            'privacy_path' => route('privacy', [], false).'#gesprekken',
        ]);
    }

    public function show(Request $request, Meeting $meeting, Transcriber $transcriber, MeetingReporter $reporter): JsonResponse
    {
        $user = $request->user();
        abort_unless($meeting->isAccessibleBy($user), 403);

        $meeting->load(['project.customer', 'user']);
        $status = $meeting->status;
        $ready = $status === MeetingStatus::Klaar;
        $actions = $ready ? ($meeting->actions ?? []) : [];
        $retention = (int) config('renovion.transcription.audio_retention_days');
        $openActions = collect($actions)->contains(fn (array $action) => $action['task_id'] === null);

        return response()->json([
            'id' => $meeting->id,
            'title' => $meeting->title,
            'sub' => collect([$meeting->kind->label(), $meeting->started_at->translatedFormat('j M Y H:i'), $this->duration($meeting), $meeting->user?->name])->filter()->implode(', '),
            'status' => ['value' => $status->value, 'label' => $status->label(), 'classes' => $status->appBadgeClasses()],
            'duration' => $this->duration($meeting) ?? '0:00',
            'project' => $meeting->project ? ['id' => $meeting->project->id, 'name' => $meeting->project->name, 'path' => route('projects.show', $meeting->project, false)] : null,
            // Wacht de verwerking op een sleutel? Dan zegt het verslag welke, in plaats van eindeloos "bezig".
            'waiting_for' => match (true) {
                $status !== MeetingStatus::Verwerken => null,
                $meeting->transcript === null && ! $transcriber->isConfigured() => 'De transcriptiedienst staat nog niet aan (sleutel ontbreekt).',
                ! $reporter->isConfigured() => 'Het gesprek is omgezet naar tekst, maar Nova staat nog niet aan (sleutel ontbreekt).',
                default => null,
            },
            'error' => $status === MeetingStatus::Mislukt ? $meeting->error : null,
            'can_stop' => $status === MeetingStatus::Opname && $meeting->user_id === $user->id,
            'can_delete' => $user->can('manage-crm') || $meeting->user_id === $user->id,
            'summary' => $ready ? ($meeting->summary ?: 'Geen samenvatting.') : null,
            'decisions' => $ready ? array_values($meeting->decisions ?? []) : [],
            'actions' => collect($actions)->map(fn (array $action, int $index) => [
                'index' => $index,
                'title' => $action['title'],
                'owner_id' => $action['owner_id'] ?? null,
                'due' => $action['due'] ?? null,
                'task_id' => $action['task_id'] ?? null,
            ])->values(),
            'questions' => $ready ? array_values($meeting->questions ?? []) : [],
            'team' => $openActions
                ? User::where('role', '!=', UserRole::Klant)->orderBy('name')->get(['id', 'name'])->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])->values()
                : [],
            'transcript' => collect($meeting->transcript ?? [])->map(fn (array $line) => [
                'time' => gmdate($line['start'] >= 3600000 ? 'G:i:s' : 'i:s', intdiv($line['start'], 1000)),
                'speaker' => $line['speaker'] !== '' ? $meeting->speakerName($line['speaker']) : null,
                'color' => ['A' => 'text-brand-700', 'B' => 'text-project-700', 'C' => 'text-calc-700', 'D' => 'text-lead-700'][$line['speaker']] ?? 'text-navy-900',
                'text' => $line['text'],
            ])->values(),
            'audio' => $meeting->hasAudio() && $status !== MeetingStatus::Opname ? [
                'url' => URL::temporarySignedRoute('api.app.meetings.audio', today()->addDays(2), $meeting, absolute: false),
                'note' => 'Wordt '.($meeting->ended_at ? 'op '.$meeting->ended_at->copy()->addDays($retention)->translatedFormat('j M Y') : 'na '.$retention.' dagen').' verwijderd. Het verslag blijft.',
            ] : null,
            'audio_deleted' => $meeting->audio_deleted_at !== null,
            'channels' => $ready
                ? ChatChannel::forUser($user)->map(fn (ChatChannel $channel) => [
                    'id' => $channel->id,
                    'name' => $channel->displayName(),
                    'selected' => $meeting->project_id !== null && $channel->project_id === $meeting->project_id,
                ])->values()
                : [],
            'links' => [
                'index' => route('meetings.index', [], false),
                'tasks' => route('tasks.index', [], false),
            ],
        ]);
    }

    /** De opname voor <audio>: zonder token, met een ondertekend adres uit het verslag (met Range voor de schuifbalk). */
    public function audio(Meeting $meeting): BinaryFileResponse
    {
        abort_unless($meeting->hasAudio() && Storage::exists($meeting->audio_path), 404);

        return response()->file(Storage::path($meeting->audio_path), [
            'Content-Type' => $meeting->audio_mime ?? 'audio/webm',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function duration(Meeting $meeting): ?string
    {
        return $meeting->duration_seconds ? gmdate($meeting->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', $meeting->duration_seconds) : null;
    }
}
