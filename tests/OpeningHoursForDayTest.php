<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\OpeningHours\Exceptions\NonMutableOffsets;
use Cline\OpeningHours\Exceptions\OverlappingTimeRanges;
use Cline\OpeningHours\OpeningHoursForDay;
use Cline\OpeningHours\Time;
use Cline\OpeningHours\TimeRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class OpeningHoursForDayTest extends TestCase
{
    #[Test()]
    public function it_can_be_created_from_an_array_of_time_range_strings(): void
    {
        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $this->assertCount(2, $openingHoursForDay);

        $this->assertInstanceOf(TimeRange::class, $openingHoursForDay[0]);
        $this->assertSame('09:00-12:00', (string) $openingHoursForDay[0]);

        $this->assertInstanceOf(TimeRange::class, $openingHoursForDay[1]);
        $this->assertSame('13:00-18:00', (string) $openingHoursForDay[1]);
    }

    #[Test()]
    public function it_cant_be_created_when_time_ranges_overlap(): void
    {
        $this->expectException(OverlappingTimeRanges::class);

        OpeningHoursForDay::fromStrings(['09:00-18:00', '14:00-20:00']);
    }

    #[Test()]
    public function it_can_determine_whether_its_open_at_a_time(): void
    {
        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-18:00']);

        $this->assertTrue($openingHoursForDay->isOpenAt(Time::fromString('09:00')));
        $this->assertFalse($openingHoursForDay->isOpenAt(Time::fromString('08:00')));
        $this->assertFalse($openingHoursForDay->isOpenAt(Time::fromString('18:00')));
    }

    #[Test()]
    public function it_casts_to_string(): void
    {
        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $this->assertSame('09:00-12:00,13:00-18:00', (string) $openingHoursForDay);
    }

    #[Test()]
    public function it_can_offset_is_existed(): void
    {
        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $this->assertTrue($openingHoursForDay->offsetExists(0));
        $this->assertTrue($openingHoursForDay->offsetExists(1));
        $this->assertFalse($openingHoursForDay->offsetExists(2));
    }

    #[Test()]
    public function it_can_unset_offset(): void
    {
        $this->expectException(NonMutableOffsets::class);

        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $openingHoursForDay->offsetUnset(0);
    }

    #[Test()]
    public function it_can_get_iterator(): void
    {
        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $this->assertCount(2, $openingHoursForDay->getIterator()->getArrayCopy());
    }

    #[Test()]
    public function it_cant_set_iterator_item(): void
    {
        $this->expectException(NonMutableOffsets::class);

        $openingHoursForDay = OpeningHoursForDay::fromStrings(['09:00-12:00', '13:00-18:00']);

        $openingHoursForDay[0] = TimeRange::fromString('07:00-11:00');
    }
}
