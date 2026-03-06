<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\OpeningHours;
use Cline\OpeningHours\TimeRange;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class OpeningHoursOverflowTest extends TestCase
{
    #[Test()]
    public function it_fills_opening_hours_with_overflow(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['09:00-02:00'],
        ]);

        $this->assertInstanceOf(TimeRange::class, $openingHours->forDay('monday')[0]);
        $this->assertSame('09:00-02:00', (string) $openingHours->forDay('monday')[0]);
    }

    #[Test()]
    public function check_open_with_overflow(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['09:00-02:00'],
            'tuesday' => ['19:00-04:00'],
            'wednesday' => ['09:00-02:00'],
        ]);

        $shouldBeOpen = Date::parse('2019-04-23 01:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-23 03:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-23 18:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-23 20:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-23 23:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-24 02:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-24 03:59:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-24 04:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeOpen));

        $shouldBeOpen = Date::parse('2019-04-24 09:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));
    }

    #[Test()]
    public function check_open_with_overflow_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['09:00-02:00'],
        ]);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 01:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));
    }

    #[Test()]
    public function next_close_with_overflow(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['09:00-02:00'],
        ]);

        $shouldBeOpen = Date::parse('2019-04-23 01:00:00');
        $this->assertSame('2019-04-23 02:00:00', $openingHours->nextClose($shouldBeOpen)->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function next_close_with_overflow_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['09:00-02:00'], // 2019-04-22
        ]);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 01:00:00');
        $nextTimeClosed = $openingHours->nextClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-23 02:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 23:00:00');
        $nextTimeClosed = $openingHours->nextClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-23 02:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 07:00:00');
        $nextTimeClosed = $openingHours->nextClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-23 02:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 01:00:00');
        $nextTimeClosed = $openingHours->nextOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-29 09:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 23:00:00');
        $nextTimeClosed = $openingHours->nextOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-29 09:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 07:00:00');
        $nextTimeClosed = $openingHours->nextOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 09:00:00', $nextTimeClosed);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 23:30:00');
        $previousTimeOpen = $openingHours->previousOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 09:00:00', $previousTimeOpen);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 01:00:00');
        $previousTimeOpen = $openingHours->previousOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 09:00:00', $previousTimeOpen);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 05:00:00');
        $previousTimeOpen = $openingHours->previousOpen($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 09:00:00', $previousTimeOpen);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-22 23:30:00');
        $previousTimeOpen = $openingHours->previousClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 02:00:00', $previousTimeOpen);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 01:00:00');
        $previousTimeOpen = $openingHours->previousClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 02:00:00', $previousTimeOpen);

        $shouldBeOpen = CarbonImmutable::parse('2019-04-23 05:00:00');
        $previousTimeOpen = $openingHours->previousClose($shouldBeOpen)->format('Y-m-d H:i:s');
        $this->assertSame('2019-04-22 02:00:00', $previousTimeOpen);
    }

    #[Test()]
    public function previous_open_and_close_with_overflow_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['18:00-05:00'],
            'tuesday' => ['18:00-05:00'],
        ]);
        $tuesday = Date::parse('2024-06-11 06:00:00');

        $this->assertSame('2024-06-10 18:00', $openingHours->previousOpen($tuesday)->format('Y-m-d H:i'));
        $this->assertSame('2024-06-11 05:00', $openingHours->previousClose($tuesday)->format('Y-m-d H:i'));
    }

    #[Test()]
    public function overflow_on_simple_ranges(): void
    {
        // Tuesday 4th of June 2019, 11.35 am
        $time = Date::parse('2019-06-04 11:35:00');

        $openWithOverflow = OpeningHours::create([
            'overflow' => true,
            'monday' => ['11:00-18:00'],
            'tuesday' => ['13:37-15:37'],
        ]);

        $openWithoutOverflow = OpeningHours::create([
            'overflow' => false,
            'monday' => ['11:00-18:00'],
            'tuesday' => ['13:37-15:37'],
        ]);

        $this->assertFalse($openWithOverflow->isOpenAt($time));
        $this->assertFalse($openWithoutOverflow->isOpenAt($time));
    }

    #[Test()]
    public function overflow_next_close(): void
    {
        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['18:00-05:00'], // 2024-11-11
            'tuesday' => ['17:00-06:00'], // 2024-11-12
        ]);

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-12 04:00:00'),
        );

        $this->assertSame('2024-11-12 05:00', $nextClose->format('Y-m-d H:i'));

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-12 05:30:00'),
        );

        $this->assertSame('2024-11-13 06:00', $nextClose->format('Y-m-d H:i'));

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-12 05:30:00'),
        );

        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['18:00-05:00'], // 2024-11-11
            'tuesday' => ['05:40-05:50', '17:00-06:00'], // 2024-11-12
        ]);

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-12 05:30:00'),
        );

        $this->assertSame('2024-11-12 05:50', $nextClose->format('Y-m-d H:i'));

        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['18:00-22:00', '23:00-05:00'], // 2024-11-11
            'tuesday' => ['17:00-06:00'], // 2024-11-12
        ]);

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-11 23:30:00'),
        );

        $this->assertSame('2024-11-12 05:00', $nextClose->format('Y-m-d H:i'));

        $nextClose = $openingHours->nextClose(
            Date::parse('2024-11-12 04:00:00'),
        );

        $this->assertSame('2024-11-12 05:00', $nextClose->format('Y-m-d H:i'));
    }
}
