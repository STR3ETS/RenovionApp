<?php

namespace App\Http\Controllers;

use App\Models\ChatChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Teamchat (briefing §11): team- en projectkanalen in één interface.
 */
class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $channels = ChatChannel::forUser($request->user());

        // Op een breed scherm staat het laatst gebruikte gesprek er direct naast;
        // op een telefoon zie je eerst de lijst.
        $latest = $channels->sortByDesc('last_message_at')->first();

        if ($latest === null) {
            return view('chat.index', ['channels' => $channels, 'user' => $request->user()]);
        }

        return $this->conversation($request, $latest, $channels, listFirst: true);
    }

    public function show(Request $request, ChatChannel $channel): View
    {
        abort_unless($channel->isAccessibleBy($request->user()), 403);

        $channel->markReadFor($request->user());

        return $this->conversation($request, $channel, ChatChannel::forUser($request->user()));
    }

    /**
     * Het chatvenster: links de gesprekken, rechts het gekozen gesprek.
     *
     * @param  \Illuminate\Support\Collection<int, ChatChannel>  $channels
     */
    private function conversation(Request $request, ChatChannel $channel, $channels, bool $listFirst = false): View
    {
        $messages = $channel->messages()
            ->with('user')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message) => $message->toChatArray($request->user()));

        return view('chat.show', [
            'channel' => $channel,
            'channels' => $channels,
            'messages' => $messages,
            'user' => $request->user(),
            'listFirst' => $listFirst,
        ]);
    }

    /**
     * Nieuw teamkanaal (bijv. per afdeling).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage-crm'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $channel = ChatChannel::create([
            'type' => 'team',
            'name' => $validated['name'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('chat.show', $channel)->with('success', 'Kanaal aangemaakt.');
    }
}
