<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Globale zoekfunctie in de topbar: doorzoekt klanten, projecten,
 * aanvragen en taken op naam/plaats/omschrijving.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';

        if ($q === '') {
            return view('search.index', [
                'q' => $q,
                'customers' => collect(),
                'projects' => collect(),
                'leads' => collect(),
                'tasks' => collect(),
            ]);
        }

        return view('search.index', [
            'q' => $q,
            'customers' => Customer::where(fn ($query) => $query
                ->where('name', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(10)
                ->get(),
            'projects' => Project::with('customer')
                ->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like)))
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(),
            'leads' => Lead::with('customer')
                ->where(fn ($query) => $query
                    ->where('service', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like)))
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
            'tasks' => Task::with(['customer', 'project'])
                ->where('title', 'like', $like)
                ->orderByRaw('deadline is null, deadline asc')
                ->limit(10)
                ->get(),
        ]);
    }
}
