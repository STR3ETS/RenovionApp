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
        return view('chat.index', [
            'channels' => ChatChannel::forUser($request->user()),
            'user' => $request->user(),
        ]);
    }

    public function show(Request $request, ChatChannel $channel): View
    {
        abort_unless($channel->isAccessibleBy($request->user()), 403);

        $channel->markReadFor($request->user());

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
            'channels' => ChatChannel::forUser($request->user()),
            'messages' => $messages,
            'user' => $request->user(),
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
