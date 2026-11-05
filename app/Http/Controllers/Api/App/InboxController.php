<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\AttentionService;
use Illuminate\Http\JsonResponse;

/** Inbox in de iOS/Android-app: dezelfde uitzonderingen als Aandacht op de website. */
class InboxController extends Controller
{
    public function __invoke(AttentionService $attention): JsonResponse
    {
        return response()->json([
            'items' => $attention->items()->map(fn (array $item) => [
                'severity' => $item['severity'],
                'icon' => $item['icon'],
                'label' => $item['label'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                // Relatief, de app opent het als serverpagina (webcode).
                'path' => parse_url($item['url'], PHP_URL_PATH).(($query = parse_url($item['url'], PHP_URL_QUERY)) ? '?'.$query : ''),
            ])->values(),
        ]);
    }
}
