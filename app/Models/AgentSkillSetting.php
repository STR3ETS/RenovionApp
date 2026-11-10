<?php

namespace App\Models;

use App\Support\AgentRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De instelling van één skill: op welk niveau hij mag werken, of hij gepauzeerd
 * is, en vanaf welk bedrag een mens altijd beslist.
 *
 * Zonder instelling geldt het niveau uit de registry. Zo hoeft er niets in de
 * database te staan voordat het systeem werkt.
 */
#[Fillable(['skill', 'level', 'paused', 'limit_amount', 'updated_by'])]
class AgentSkillSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paused' => 'boolean',
            'limit_amount' => 'decimal:2',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * De werkende instelling van een skill: de aanpassing als die er is, anders
     * wat de registry zegt.
     *
     * @return array{level: string, paused: bool, limit: ?float, overridden: bool}
     */
    public static function effective(string $skill): array
    {
        $default = AgentRegistry::skill($skill)['level'] ?? 'observeren';
        $setting = self::firstWhere('skill', $skill);

        return [
            'level' => $setting?->level ?? $default,
            'paused' => (bool) ($setting?->paused ?? false),
            'limit' => $setting?->limit_amount !== null ? (float) $setting->limit_amount : null,
            'overridden' => $setting?->level !== null && $setting->level !== $default,
        ];
    }

    /**
     * Alle skills met hun werkende instelling, in één keer.
     *
     * @return array<string, array{level: string, paused: bool, limit: ?float, overridden: bool}>
     */
    public static function allEffective(): array
    {
        $settings = self::all()->keyBy('skill');
        $out = [];

        foreach (AgentRegistry::SKILLS as $id => $skill) {
            $setting = $settings->get($id);

            $out[$id] = [
                'level' => $setting?->level ?? $skill['level'],
                'paused' => (bool) ($setting?->paused ?? false),
                'limit' => $setting?->limit_amount !== null ? (float) $setting->limit_amount : null,
                'overridden' => $setting?->level !== null && $setting->level !== $skill['level'],
            ];
        }

        return $out;
    }
}
