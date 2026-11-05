<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class DutchDatesTest extends TestCase
{
    public function test_short_months_and_days_have_no_dot_so_a_comma_can_follow(): void
    {
        $date = Carbon::parse('2026-10-31 14:30');

        $this->assertSame('31 okt', $date->translatedFormat('j M'));
        $this->assertSame('za 31 okt', $date->translatedFormat('D j M'));
        $this->assertSame('31 okt, 14:30', $date->translatedFormat('j M, H:i'));
        $this->assertSame('31 mei', Carbon::parse('2026-05-31')->translatedFormat('j M'));
    }

    public function test_full_names_stay_the_same(): void
    {
        $this->assertSame('zaterdag 31 oktober', Carbon::parse('2026-10-31')->translatedFormat('l j F'));
    }
}
