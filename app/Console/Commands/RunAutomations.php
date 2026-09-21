<?php

namespace App\Console\Commands;

use App\Automations\AutomationRegistry;
use App\Services\AttentionService;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'automations:run';

    protected $description = 'Voer alle automations uit (trigger → voorwaarden → actie, briefing §22)';

    public function handle(): int
    {
        foreach (AutomationRegistry::all() as $automation) {
            $count = $automation->run();

            $this->line(sprintf('%-35s %s', $automation->name(), $count > 0 ? "{$count} actie(s)" : '—'));
        }

        AttentionService::forgetCount();

        return self::SUCCESS;
    }
}
