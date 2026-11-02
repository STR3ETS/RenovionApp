<?php

namespace App\Support;

use App\Enums\HealthLight;

/**
 * Eén stoplicht met de reden erbij, zodat de gebruiker ziet waarom het
 * op oranje of rood staat en niet hoeft te raden.
 */
final class HealthSignal
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        public readonly HealthLight $light,
        public readonly string $reason,
    ) {}
}
