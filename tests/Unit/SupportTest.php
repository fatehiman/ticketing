<?php

namespace Tests\Unit;

use App\Support\Dates;
use App\Support\Duration;
use App\Support\Money;
use App\Support\TicketFilter;
use Tests\TestCase;

class SupportTest extends TestCase
{
    public function test_dates_parse_jalali_and_gregorian(): void
    {
        $this->assertSame('2026-09-23', Dates::parse('1405/07/01')->toDateString());
        $this->assertSame('2026-09-23', Dates::parse('۱۴۰۵/۰۷/۰۱')->toDateString());
        $this->assertSame('2026-09-23', Dates::parse('2026-09-23')->toDateString());
        $this->assertNull(Dates::parse('1405/13/40'));
        $this->assertNull(Dates::parse('hello'));
    }

    public function test_duration_round_trip(): void
    {
        $this->assertSame(150, Duration::toMinutes('02:30'));
        $this->assertSame(150, Duration::toMinutes('۰۲:۳۰'));
        $this->assertNull(Duration::toMinutes('2:75'));
        $this->assertSame('02:30', Duration::format(150));
        $this->assertSame('120:05', Duration::format(7205));
    }

    public function test_money_parse(): void
    {
        $this->assertSame('1250000', Money::parse('1,250,000'));
        $this->assertSame('1250000', Money::parse('۱٬۲۵۰٬۰۰۰'));
        $this->assertNull(Money::parse(''));
    }

    public function test_filter_normalize_is_order_independent(): void
    {
        $a = TicketFilter::normalize(['status' => ['testing', 'backlog'], 'q' => ' x ', 'junk' => 1, 'priority' => []]);
        $b = TicketFilter::normalize(['q' => 'x', 'status' => ['backlog', 'testing', '']]);
        $this->assertSame($a, $b);
        $this->assertSame(['q' => 'x', 'status' => ['backlog', 'testing']], $a);
    }
}
