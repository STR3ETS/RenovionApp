<?php

namespace App\Http\Controllers;

use App\Enums\ActionSource;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Services\NovaAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatMessageController extends Controller
{
    /**
     * Berichten ophalen (polling): alles ná het laatst bekende id.
     */
    public function index(Request $request, ChatChannel $channel): JsonResponse
    {
        abort_unless($channel->isAccessibleBy($request->user()), 403);

        $request->validate(['after' => ['nullable', 'integer']]);

        $messages = $channel->messages()
            ->with('user')
            ->when($request->filled('after'), fn ($query) => $query->where('id', '>', $request->integer('after')))
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isNotEmpty()) {
            $channel->markReadFor($request->user());
        }

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $message) => $message->toChatArray($request->user())),
        ]);
    }

    public function store(Request $request, ChatChannel $channel, NovaAssistant $assistant): JsonResponse
    {
        abort_unless($channel->isAccessibleBy($request->user()), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'image', 'max:10240'],
        ]);

        $message = $channel->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $request->file('attachment')?->store('chat-attachments'),
            'attachment_name' => $request->file('attachment')?->getClientOriginalName(),
        ]);

        $channel->markReadFor($request->user());

        $nieuw = collect([$message]);

        // "@nova" in een bericht: Nova denkt mee (alleen voor CRM-rollen, briefing §11/§24).
        if (str_contains(mb_strtolower($message->body ?? ''), '@nova')
            && $request->user()->can('manage-crm')
            && $assistant->isConfigured()) {
            $nieuw->push($this->novaReply($channel, $message, $assistant, $request));
        }

        return response()->json([
            'messages' => $nieuw->filter()->map(fn (ChatMessage $bericht) => $bericht->toChatArray($request->user()))->values(),
        ]);
    }

    /**
     * "Ja, doe maar": voer het Nova-voorstel uit een chatbericht uit.
     */
    public function confirmNova(Request $request, ChatMessage $message, NovaAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);
        abort_unless($message->channel->isAccessibleBy($request->user()), 403);

        $nova = $message->nova;

        if ($message->user_id !== null || ! is_array($nova['action'] ?? null) || ($nova['executed'] ?? false)) {
            return response()->json(['message' => 'Dit voorstel kan niet (meer) worden uitgevoerd.'], 422);
        }

        $result = $assistant->execute($nova['action'], $request->user(), ActionSource::Nova);

        $message->update(['nova' => [...$nova, 'executed' => true, 'url' => $result['url']]]);

        $bevestiging = $message->channel->messages()->create([
            'user_id' => null,
            'body' => $result['message'],
            'nova' => ['executed' => true, 'url' => $result['url']],
        ]);

        return response()->json([
            'messages' => [
                $message->fresh('user')->toChatArray($request->user()),
                $bevestiging->toChatArray($request->user()),
            ],
        ]);
    }

    /**
     * Taak maken vanuit een chatbericht (briefing §11).
     */
    public function toTask(Request $request, ChatMessage $message): JsonResponse
    {
        abort_unless($message->channel->isAccessibleBy($request->user()), 403);
        abort_if(blank($message->body), 422);

        $project = $message->channel->project;

        Task::create([
            'title' => str($message->body)->limit(120),
            'note' => 'Vanuit de chat'.($message->user ? ' — bericht van '.$message->user->name : ''),
            'project_id' => $project?->id,
            'customer_id' => $project?->customer_id,
            'owner_id' => $request->user()->id,
            'source' => ActionSource::Handmatig,
        ]);

        return response()->json(['message' => 'Taak aangemaakt vanuit het chatbericht.']);
    }

    public function attachment(Request $request, ChatMessage $message): BinaryFileResponse
    {
        abort_unless($message->channel->isAccessibleBy($request->user()), 403);
        abort_unless($message->attachment_path !== null && Storage::exists($message->attachment_path), 404);

        return response()->file(Storage::path($message->attachment_path), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function novaReply(ChatChannel $channel, ChatMessage $message, NovaAssistant $assistant, Request $request): ?ChatMessage
    {
        try {
            $prompt = trim(str_ireplace('@nova', '', (string) $message->body));

            if ($channel->isProjectChannel() && $channel->project !== null) {
                $prompt = 'Context: dit gaat over project "'.$channel->project->name.'" (id '.$channel->project->id.'). '.$prompt;
            }

            $result = $assistant->propose($prompt, $request->user());

            return $channel->messages()->create([
                'user_id' => null,
                'body' => $result['type'] === 'proposal'
                    ? 'Zal ik dit klaarzetten?'
                    : $result['text'],
                'nova' => $result['type'] === 'proposal' ? [
                    'action' => $result['action'],
                    'preview' => $result['preview'],
                    'executed' => false,
                ] : null,
            ]);
        } catch (\Throwable) {
            return $channel->messages()->create([
                'user_id' => null,
                'body' => 'Ik kon dit even niet verwerken — probeer het zo nog eens.',
            ]);
        }
    }
}
