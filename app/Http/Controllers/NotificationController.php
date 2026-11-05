<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /** Meldingenlijst achter de bel in de app: de laatste pushmeldingen, nieuwe bovenaan en na openen gelezen. */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->limit(30)->get();

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', ['notifications' => $notifications]);
    }
}
