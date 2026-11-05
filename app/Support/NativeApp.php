<?php

namespace App\Support;

/**
 * De iOS/Android-app (Capacitor) herkent zichzelf aan de user agent "RenovionApp" (capacitor.config.ts).
 * Alleen daar geldt de Renovion OS-stijl; de website blijft op elk scherm zoals hij is.
 */
class NativeApp
{
    public static function active(): bool
    {
        return str_contains((string) request()->userAgent(), 'RenovionApp');
    }
}
