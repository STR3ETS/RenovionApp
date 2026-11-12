<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Globale zoekfunctie: klanten, projecten, aanvragen en taken op naam, plaats of omschrijving.
 * Gedeeld door de zoekpagina van de website (SearchController) en de app (Api\App\SearchController).
 */
class GlobalSearch
{
    /** @return array{customers: Collection<int, Customer>, projects: Collection<int, Project>, leads: Collection<int, Lead>, tasks: Collection<int, Task>} */
    public static function run(string $q): array
    {
        $q = trim($q);

        if ($q === '') {
            return ['customers' => collect(), 'projects' => collect(), 'leads' => collect(), 'tasks' => collect()];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';

        return [
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
        ];
    }
}
