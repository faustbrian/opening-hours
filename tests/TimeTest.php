<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Exceptions\InvalidTimeString;
use Cline\OpeningHours\Time;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function date_create_immutable;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class TimeTest extends TestCase
{
    #[Test()]
    public function it_can_be_created_from_a_string(): void
    {
        $this->assertSame('00:00', (string) Time::fromString('00:00'));
        $this->assertSame('16:32', (string) Time::fromString('16:32'));
        $this->assertSame('24:00', (string) Time::fromString('24:00'));
    }

    #[Test()]
    public function it_cant_be_created_from_an_invalid_string(): void
    {
        $this->expectException(InvalidTimeString::class);

        Time::fromString('aa:bb');
    }

    #[Test()]
    public function it_cant_be_created_from_an_invalid_hour(): void
    {
        $this->expectException(InvalidTimeString::class);

        Time::fromString('26:00');
    }

    #[Test()]
    public function it_cant_be_created_from_an_out_of_bound_hour(): void
    {
        $this->expectException(InvalidTimeString::class);

        Time::fromString('24:01');
    }

    #[Test()]
    public function it_cant_be_created_from_an_invalid_minute(): void
    {
        $this->expectException(InvalidTimeString::class);

        Time::fromString('14:60');
    }

    #[Test()]
    public function it_can_be_created_from_a_date_time_instance(): void
    {
        $dateTime = Date::parse('2016-09-27 16:00:00');

        $this->assertSame('16:00', (string) Time::fromDateTime($dateTime));

        $dateTime = CarbonImmutable::parse('2016-09-27 16:00:00');

        $this->assertSame('16:00', (string) Time::fromDateTime($dateTime));
    }

    #[Test()]
    public function it_can_determine_that_its_the_same_as_another_time(): void
    {
        $this->assertTrue(Time::fromString('09:00')->isSame(Time::fromString('09:00')));
        $this->assertFalse(Time::fromString('09:00')->isSame(Time::fromString('10:00')));
        $this->assertFalse(Time::fromString('09:00')->isSame(Time::fromString('09:30')));
    }

    #[Test()]
    public function it_can_determine_that_its_before_another_time(): void
    {
        $this->assertTrue(Time::fromString('09:00')->isBefore(Time::fromString('10:00')));
        $this->assertTrue(Time::fromString('09:00')->isBefore(Time::fromString('09:30')));
        $this->assertFalse(Time::fromString('09:00')->isBefore(Time::fromString('09:00')));
        $this->assertFalse(Time::fromString('09:00')->isBefore(Time::fromString('08:00')));
        $this->assertFalse(Time::fromString('09:00')->isBefore(Time::fromString('08:30')));
        $this->assertFalse(Time::fromString('08:30')->isBefore(Time::fromString('08:00')));
    }

    #[Test()]
    public function it_can_determine_that_its_after_another_time(): void
    {
        $this->assertTrue(Time::fromString('09:00')->isAfter(Time::fromString('08:00')));
        $this->assertTrue(Time::fromString('09:30')->isAfter(Time::fromString('09:00')));
        $this->assertTrue(Time::fromString('09:00')->isAfter(Time::fromString('08:30')));
        $this->assertFalse(Time::fromString('09:00')->isAfter(Time::fromString('09:00')));
        $this->assertFalse(Time::fromString('09:00')->isAfter(Time::fromString('09:30')));
        $this->assertFalse(Time::fromString('09:00')->isAfter(Time::fromString('10:00')));
    }

    #[Test()]
    public function it_can_determine_that_its_the_same_or_after_another_time(): void
    {
        $this->assertTrue(Time::fromString('09:00')->isSameOrAfter(Time::fromString('08:00')));
        $this->assertTrue(Time::fromString('09:00')->isSameOrAfter(Time::fromString('09:00')));
        $this->assertTrue(Time::fromString('09:30')->isSameOrAfter(Time::fromString('09:30')));
        $this->assertTrue(Time::fromString('09:30')->isSameOrAfter(Time::fromString('09:00')));
        $this->assertFalse(Time::fromString('09:00')->isSameOrAfter(Time::fromString('10:00')));
    }

    #[Test()]
    public function it_can_accept_any_date_format_with_the_date_time_interface(): void
    {
        $dateTime = date_create_immutable('2012-11-06 13:25:59.123456');

        $this->assertSame('13:25', (string) Time::fromDateTime($dateTime));
    }

    #[Test()]
    public function it_can_be_formatted(): void
    {
        $this->assertSame('09:00', Time::fromString('09:00')->format());
        $this->assertSame('09:00', Time::fromString('09:00')->format('H:i'));
        $this->assertSame('9 AM', Time::fromString('09:00')->format('g A'));
    }

    #[Test()]
    public function it_can_get_hours_and_minutes(): void
    {
        $time = Time::fromString('16:30');
        $this->assertSame(16, $time->hours());
        $this->assertSame(30, $time->minutes());
    }

    #[Test()]
    public function it_can_calculate_diff(): void
    {
        $time1 = Time::fromString('16:30');
        $time2 = Time::fromString('16:05');
        $this->assertSame(0, $time1->diff($time2)->h);
        $this->assertSame(25, $time1->diff($time2)->i);
    }

    #[Test()]
    public function it_should_not_mutate_passed_datetime(): void
    {
        $dateTime = Date::parse('2016-09-27 12:00:00');
        $time = Time::fromString('15:00');
        $this->assertSame('2016-09-27 15:00:00', $time->toDateTime($dateTime)->format('Y-m-d H:i:s'));
        $this->assertSame('2016-09-27 12:00:00', $dateTime->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_not_mutate_passed_datetime_immutable(): void
    {
        $dateTime = CarbonImmutable::parse('2016-09-27 12:00:00');
        $time = Time::fromString('15:00');
        $this->assertSame('2016-09-27 15:00:00', $time->toDateTime($dateTime)->format('Y-m-d H:i:s'));
        $this->assertSame('2016-09-27 12:00:00', $dateTime->format('Y-m-d H:i:s'));
    }
}
