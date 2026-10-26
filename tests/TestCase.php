<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Noodrem: tests draaien uitsluitend op sqlite :memory: (phpunit.xml).
     * Een gecachte config (php artisan optimize) overschrijft die instelling
     * en laat RefreshDatabase anders de échte lokale database freshen - dat
     * is al eens gebeurd. Daarom checken we hier, vóórdat de test-traits
     * (waaronder RefreshDatabase) draaien, en stoppen we de suite hard.
     */
    protected function setUpTraits(): array
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            self::fail(
                'Tests draaien niet op sqlite :memory: maar op "'.config('database.default').'" - '
                .'waarschijnlijk staat er een gecachte config (bootstrap/cache/config.php). '
                .'Draai eerst: php artisan optimize:clear'
            );
        }

        return parent::setUpTraits();
    }
}
