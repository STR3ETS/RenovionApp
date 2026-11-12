<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Meldingen achter de bel in de app: de laatste 30 pushmeldingen, nieuwste bovenaan. Net als op de
 * serverpagina zijn ze na openen gelezen; wat nieuw was, blijft in dit antwoord nog als nieuw gemarkeerd.
 */
class NotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(30)->get();

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'notifications' => $notifications->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Melding',
                'body' => $notification->data['body'] ?? null,
                'icon' => $notification->data['icon'] ?? 'bell',
                'path' => $notification->data['url'] ?? '/',
                'at' => $notification->created_at->toIso8601String(),
                'new' => $notification->read_at === null,
            ]),
        ]);
    }
}
