<?php

namespace App\Actions;

use App\Enums\InvoiceType;
use App\Models\Budget;
use App\Models\BudgetOption;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDeduction;

/**
 * De eindafrekening: aanneemsom plus goedgekeurd meerwerk, min akkoord
 * minderwerk, min wat er al gefactureerd is. Wat overblijft is de slottermijn.
 *
 * Bij een particuliere klant mag vijf procent van de aanneemsom worden
 * achtergehouden tot drie maanden na oplevering. Dat is het opschortingsrecht
 * uit de wet; de klant zet dat in depot of regelt een bankgarantie.
 */
class BuildFinalSettlement
{
    /** Het deel dat een particuliere klant mag achterhouden. */
    private const RETENTION_PCT = 5;

    /** Hoe lang de onderhoudstermijn duurt. */
    private const RETENTION_MONTHS = 3;

    /**
     * @return array<string, mixed>
     */
    public function preview(Project $project): array
    {
        $budget = Budget::where('project_id', $project->id)
            ->orderByRaw('case when status = ? then 0 else 1 end', [Budget::STATUS_FINAL])
            ->latest('id')
            ->first();

        $totals = $budget?->totals();

        $base = $totals !== null ? $totals->totalExcludingVat : (float) $project->value;
        $extras = $totals?->approvedExtras ?? 0.0;
        $vatPct = $totals !== null ? $totals->vatPercentage : 21.0;

        $deductions = ProjectDeduction::where('project_id', $project->id)
            ->where('status', ProjectDeduction::STATUS_AGREED)
            ->get();

        $final = round($base + $extras - (float) $deductions->sum('amount'), 2);

        // Alleen facturen die geen eindafrekening zijn tellen als al gefactureerd.
        $invoiced = round((float) Invoice::revenue()
            ->where('project_id', $project->id)
            ->where('type', '!=', InvoiceType::Eindafrekening)
            ->sum('amount'), 2);

        $retention = $this->retentionFor($project, $final, $vatPct);

        return [
            'budget' => $budget,
            'base' => round($base, 2),
            'extras' => round($extras, 2),
            'extra_lines' => $budget?->options->where('status', BudgetOption::STATUS_APPROVED) ?? collect(),
            'deductions' => $deductions,
            'deduction_total' => round((float) $deductions->sum('amount'), 2),
            'final' => $final,
            'invoiced' => $invoiced,
            'balance' => round($final - $invoiced, 2),
            'vat_pct' => $vatPct,
            'retention' => $retention,
            'retention_release_on' => $project->end_date_actual?->copy()->addMonths(self::RETENTION_MONTHS)
                ?? today()->addMonths(self::RETENTION_MONTHS),
            'existing' => Invoice::where('project_id', $project->id)
                ->where('type', InvoiceType::Eindafrekening)
                ->first(),
        ];
    }

    /**
     * Vijf procent over het bedrag inclusief btw, en alleen voor particulieren.
     * Een zakelijke opdrachtgever heeft dit recht niet.
     */
    private function retentionFor(Project $project, float $final, float $vatPct): float
    {
        if ($project->retention_type === 'nvt') {
            return 0.0;
        }

        return round($final * (1 + $vatPct / 100) * self::RETENTION_PCT / 100, 2);
    }
}
