<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;

/**
 * De nazorgfase telt niet meer mee in de voortgang (Project::syncProgress). Eenmalig de voortgang ophogen
 * waar die daardoor hoger uitkomt, zodat de 95%-grens van de kwaliteitsketen meteen klopt. Alleen omhoog:
 * een waarde die niet uit de fasen kwam (bijv. een afgerond project op 100%) blijft staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Project::query()
            ->where('status', '!=', ProjectStatus::Afgerond)
            ->each(function (Project $project) {
                $progress = $project->calculatedProgress();

                if ($progress !== null && $progress > (int) $project->progress) {
                    $project->forceFill(['progress' => $progress])->save();
                }
            });
    }

    public function down(): void
    {
        // Niets terug te zetten: de voortgang volgt bij de volgende wijziging weer de berekening van dat moment.
    }
};
