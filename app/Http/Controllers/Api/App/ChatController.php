<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Teamchat in de app: de kanalenlijst (zoals chat/partials/channels-app) en een gesprek openen.
 * Berichten ophalen, versturen, Nova bevestigen en "→ Taak" gaan via ChatMessageController,
 * dezelfde code als de website (die geeft de app ondertekende adressen voor bijlagen).
 */
class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'can_create' => $user->can('manage-crm'),
            'channels' => ChatChannel::overviewFor($user)->map(function (ChatChannel $channel) use ($user) {
                $latest = $channel->latestMessage;

                return [
                    'id' => $channel->id,
                    'name' => $channel->displayName(),
                    'kind' => $channel->isProjectChannel() ? 'project' : 'team',
                    'unread' => $channel->unreadCountFor($user),
                    'time' => $latest?->listTime(),
                    'preview' => $latest?->preview($user),
                ];
            }),
        ]);
    }

    /** Een gesprek: kop en de laatste 100 berichten. Openen = gelezen, net als op de website. */
    public function show(Request $request, ChatChannel $channel): JsonResponse
    {
        $user = $request->user();
        abort_unless($channel->isAccessibleBy($user), 403);

        $channel->markReadFor($user);

        $messages = $channel->messages()
            ->with('user')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'id' => $channel->id,
            'name' => $channel->displayName(),
            'kind' => $channel->isProjectChannel() ? 'project' : 'team',
            'subtitle' => $channel->isProjectChannel() ? 'Projectchat, intern: de klant leest niet mee' : 'Teamkanaal',
            'project_path' => $channel->isProjectChannel() && $channel->project ? route('projects.show', $channel->project, false) : null,
            'nova' => $user->can('manage-crm'),
            'messages' => $messages->map(fn (ChatMessage $message) => $message->toChatArray($user, forApp: true)),
        ]);
    }

    /** Nieuw teamkanaal (alleen kantoor), zoals het plusje boven de kanalenlijst. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $channel = ChatChannel::create([
            'type' => 'team',
            'name' => $validated['name'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['id' => $channel->id], 201);
    }

    /** Foto in een bericht, voor <img> in de app: zonder token, met het ondertekende adres uit toChatArray. */
    public function attachment(ChatMessage $message): BinaryFileResponse
    {
        abort_unless($message->attachment_path !== null && Storage::exists($message->attachment_path), 404);

        return response()->file(Storage::path($message->attachment_path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
