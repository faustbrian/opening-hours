<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\OpeningHours\Exceptions\InvalidTimeRangeArray;
use Cline\OpeningHours\Exceptions\InvalidTimeRangeList;
use Cline\OpeningHours\Exceptions\InvalidTimeRangeString;
use Cline\OpeningHours\Time;
use Cline\OpeningHours\TimeRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class TimeRangeTest extends TestCase
{
    #[Test()]
    public function it_can_be_created_from_a_string(): void
    {
        $this->assertSame('16:00-18:00', (string) TimeRange::fromString('16:00-18:00'));
    }

    #[Test()]
    public function it_cant_be_created_from_an_invalid_range(): void
    {
        $this->expectException(InvalidTimeRangeString::class);

        TimeRange::fromString('16:00/18:00');
    }

    #[Test()]
    public function it_will_throw_an_exception_when_passing_a_invalid_array(): void
    {
        $this->expectException(InvalidTimeRangeArray::class);

        TimeRange::fromArray([]);
    }

    #[Test()]
    public function it_will_throw_an_exception_when_passing_a_empty_array_to_list(): void
    {
        $this->expectException(InvalidTimeRangeList::class);

        TimeRange::fromList([]);
    }

    #[Test()]
    public function it_will_throw_an_exception_when_passing_a_invalid_array_to_list(): void
    {
        $this->expectException(InvalidTimeRangeList::class);

        TimeRange::fromList([
            'foo',
        ]);
    }

    #[Test()]
    public function it_can_get_the_time_objects(): void
    {
        $timeRange = TimeRange::fromString('16:00-18:00');

        $this->assertInstanceOf(Time::class, $timeRange->start());
        $this->assertInstanceOf(Time::class, $timeRange->end());
    }

    #[Test()]
    public function it_can_determine_that_it_spills_over_to_the_next_day(): void
    {
        $this->assertTrue(TimeRange::fromString('18:00-01:00')->spillsOverToNextDay());
        $this->assertFalse(TimeRange::fromString('18:00-23:00')->spillsOverToNextDay());
    }

    #[Test()]
    public function it_can_determine_that_it_contains_a_time(): void
    {
        $this->assertTrue(TimeRange::fromString('16:00-18:00')->containsTime(Time::fromString('16:00')));
        $this->assertTrue(TimeRange::fromString('16:00-18:00')->containsTime(Time::fromString('17:00')));
        $this->assertFalse(TimeRange::fromString('16:00-18:00')->containsTime(Time::fromString('18:00')));

        $this->assertFalse(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('00:30')));
        $this->assertTrue(TimeRange::fromMidnight(Time::fromString('01:00'))->containsTime(Time::fromString('00:30')));
        $this->assertTrue(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('22:00')));
        $this->assertFalse(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('17:00')));
        $this->assertFalse(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('02:00')));
        $this->assertFalse(TimeRange::fromMidnight(Time::fromString('01:00'))->containsTime(Time::fromString('02:00')));

        $this->assertTrue(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('18:00')));
        $this->assertFalse(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('00:59')));
        $this->assertFalse(TimeRange::fromString('18:00-01:00')->containsTime(Time::fromString('01:00')));
        $this->assertTrue(TimeRange::fromMidnight(Time::fromString('01:00'))->containsTime(Time::fromString('00:59')));
        $this->assertFalse(TimeRange::fromMidnight(Time::fromString('01:00'))->containsTime(Time::fromString('01:00')));
    }

    #[Test()]
    public function it_can_determine_that_it_contains_a_time_over_midnight(): void
    {
        $this->assertFalse(TimeRange::fromString('10:00-18:00')->containsNightTime(Time::fromString('17:00')));
        $this->assertFalse(TimeRange::fromString('18:00-10:00')->containsNightTime(Time::fromString('17:00')));
        $this->assertFalse(TimeRange::fromString('10:00-18:00')->containsNightTime(Time::fromString('08:00')));
        $this->assertTrue(TimeRange::fromString('18:00-10:00')->containsNightTime(Time::fromString('08:00')));
    }

    #[Test()]
    public function it_can_determine_that_it_overlaps_another_time_range(): void
    {
        $this->assertTrue(TimeRange::fromString('16:00-18:00')->overlaps(TimeRange::fromString('15:00-17:00')));
        $this->assertTrue(TimeRange::fromString('16:00-18:00')->overlaps(TimeRange::fromString('17:00-19:00')));
        $this->assertTrue(TimeRange::fromString('16:00-18:00')->overlaps(TimeRange::fromString('17:00-17:30')));

        $this->assertTrue(TimeRange::fromString('22:00-02:00')->overlaps(TimeRange::fromString('21:00-23:00')));
        $this->assertFalse(TimeRange::fromString('22:00-02:00')->overlaps(TimeRange::fromString('01:00-02:00')));
        $this->assertTrue(TimeRange::fromString('22:00-02:00')->overlaps(TimeRange::fromString('23:00-23:30')));

        $this->assertFalse(TimeRange::fromString('16:00-18:00')->overlaps(TimeRange::fromString('14:00-15:00')));
        $this->assertFalse(TimeRange::fromString('16:00-18:00')->overlaps(TimeRange::fromString('19:00-20:00')));
    }

    #[Test()]
    public function it_can_be_formatted(): void
    {
        $this->assertSame('16:00-18:00', TimeRange::fromString('16:00-18:00')->format());
        $this->assertSame('16:00 - 18:00', TimeRange::fromString('16:00-18:00')->format('H:i', '%s - %s'));
        $this->assertSame('from 4 PM to 6 PM', TimeRange::fromString('16:00-18:00')->format('g A', 'from %s to %s'));
    }
}
