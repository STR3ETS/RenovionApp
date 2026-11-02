<?php

namespace Tests\Unit;

use App\Support\Tally;
use PHPUnit\Framework\TestCase;

class TallyTest extends TestCase
{
    public function test_one_is_singular_and_everything_else_is_plural(): void
    {
        $this->assertSame('1 calculatie', Tally::of(1, 'calculatie', 'calculaties'));
        $this->assertSame('0 calculaties', Tally::of(0, 'calculatie', 'calculaties'));
        $this->assertSame('3 calculaties', Tally::of(3, 'calculatie', 'calculaties'));
    }

    public function test_the_word_can_be_used_without_the_number(): void
    {
        $this->assertSame('taak', Tally::word(1, 'taak', 'taken'));
        $this->assertSame('taken', Tally::word(2, 'taak', 'taken'));
    }
}
