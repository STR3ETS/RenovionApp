<?php

namespace App\Http\Controllers;

use App\Services\AttentionService;
use Illuminate\View\View;

class AttentionController extends Controller
{
    public function index(AttentionService $attention): View
    {
        return view('attention.index', [
            'items' => $attention->items(),
        ]);
    }
}
