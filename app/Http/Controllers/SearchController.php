<?php

namespace App\Http\Controllers;

use App\Support\GlobalSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Globale zoekfunctie in de topbar: doorzoekt klanten, projecten,
 * aanvragen en taken op naam/plaats/omschrijving (GlobalSearch).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $q = trim((string) $request->query('q'));

        return view('search.index', ['q' => $q, ...GlobalSearch::run($q)]);
    }
}
