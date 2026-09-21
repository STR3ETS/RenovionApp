<?php

namespace App\Automations;

class AutomationRegistry
{
    /**
     * @var array<int, class-string<Automation>>
     */
    public const CLASSES = [
        MissingPhoneAutomation::class,
        NewLeadTaskAutomation::class,
        QuoteFollowUpAutomation::class,
        IntakeReminderAutomation::class,
        DepositWarningAutomation::class,
    ];

    /**
     * @return array<int, Automation>
     */
    public static function all(): array
    {
        return array_map(fn (string $class) => app($class), self::CLASSES);
    }
}
