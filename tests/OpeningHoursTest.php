<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Day;
use Cline\OpeningHours\Exceptions\InvalidDateRange;
use Cline\OpeningHours\Exceptions\MaximumLimitExceeded;
use Cline\OpeningHours\Exceptions\SearchLimitReached;
use Cline\OpeningHours\OpeningHours;
use Cline\OpeningHours\OpeningHoursForDay;
use Cline\OpeningHours\Time;
use Cline\OpeningHours\TimeRange;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Traversable;

use const PHP_FLOAT_EPSILON;

use function array_map;
use function array_values;
use function date_create_immutable;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function implode;
use function iterator_to_array;
use function mb_strtolower;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class OpeningHoursTest extends TestCase
{
    #[Test()]
    public function it_can_return_the_opening_hours_for_a_regular_week(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $openingHoursForWeek = $openingHours->forWeek();

        $this->assertCount(7, $openingHoursForWeek);
        $this->assertSame('09:00-18:00', (string) $openingHoursForWeek['monday'][0]);
        $this->assertCount(0, $openingHoursForWeek['tuesday']);
        $this->assertCount(0, $openingHoursForWeek['wednesday']);
        $this->assertCount(0, $openingHoursForWeek['thursday']);
        $this->assertCount(0, $openingHoursForWeek['friday']);
        $this->assertCount(0, $openingHoursForWeek['saturday']);
        $this->assertCount(0, $openingHoursForWeek['sunday']);
    }

    #[Test()]
    public function it_can_return_consecutive_opening_hours_for_a_regular_week(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => [],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-18:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-20:00'],
            'saturday' => ['09:00-17:00'],
            'sunday' => [],
        ]);

        $openingHoursForWeek = $openingHours->forWeekConsecutiveDays();

        $this->assertCount(5, $openingHoursForWeek);
        $this->assertInstanceOf(OpeningHoursForDay::class, $openingHoursForWeek['tuesday']['opening_hours']);
        $this->assertSame('09:00-18:00', (string) $openingHoursForWeek['tuesday']['opening_hours']);
        $this->assertSame('wednesday', array_values($openingHoursForWeek['tuesday']['days'])[1]);

        $openingHours = OpeningHours::create([
            'monday' => [],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-15:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-18:00'],
            'saturday' => ['09:00-15:00'],
            'sunday' => [],
        ]);

        $dump = array_map(fn (array $data): string => implode(', ', $data['days']).': '.$data['opening_hours'], $openingHours->forWeekConsecutiveDays());

        $this->assertSame([
            'monday' => 'monday: ',
            'tuesday' => 'tuesday: 09:00-18:00',
            'wednesday' => 'wednesday: 09:00-15:00',
            'thursday' => 'thursday, friday: 09:00-18:00',
            'saturday' => 'saturday: 09:00-15:00',
            'sunday' => 'sunday: ',
        ], $dump);

        $openingHours = OpeningHours::create([
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-15:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-18:00'],
            'saturday' => ['09:00-15:00'],
            'sunday' => [],
            'monday' => [],
        ]);

        $dump = array_map(fn (array $data): string => implode(', ', $data['days']).': '.$data['opening_hours'], $openingHours->forWeekConsecutiveDays());

        $this->assertSame([
            'monday' => 'monday: ',
            'tuesday' => 'tuesday: 09:00-18:00',
            'wednesday' => 'wednesday: 09:00-15:00',
            'thursday' => 'thursday, friday: 09:00-18:00',
            'saturday' => 'saturday: 09:00-15:00',
            'sunday' => 'sunday: ',
        ], $dump);
    }

    #[Test()]
    public function it_can_return_combined_opening_hours_for_a_regular_week(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['11:00-15:00'],
            'thursday' => ['11:00-15:00'],
            'friday' => ['12:00-14:00'],
        ]);

        $openingHoursForWeek = $openingHours->forWeekCombined();

        $this->assertCount(4, $openingHoursForWeek);
        $this->assertInstanceOf(OpeningHoursForDay::class, $openingHoursForWeek['wednesday']['opening_hours']);
        $this->assertSame('11:00-15:00', (string) $openingHoursForWeek['wednesday']['opening_hours']);
        $this->assertSame('thursday', array_values($openingHoursForWeek['wednesday']['days'])[1]);

        $openingHours = OpeningHours::create([
            'monday' => [],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-15:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-18:00'],
            'saturday' => ['09:00-15:00'],
            'sunday' => [],
        ]);

        $dump = array_map(fn (array $data): string => implode(', ', $data['days']).': '.$data['opening_hours'], $openingHours->forWeekCombined());

        $this->assertSame([
            'monday' => 'monday, sunday: ',
            'tuesday' => 'tuesday, thursday, friday: 09:00-18:00',
            'wednesday' => 'wednesday, saturday: 09:00-15:00',
        ], $dump);
    }

    #[Test()]
    public function it_can_validate_the_opening_hours(): void
    {
        $valid = OpeningHours::isValid([
            'monday' => ['09:00-18:00'],
        ]);

        $invalid = OpeningHours::isValid([
            'notaday' => ['18:00-09:00'],
        ]);

        $this->assertTrue($valid);
        $this->assertFalse($invalid);
    }

    #[Test()]
    public function it_can_return_the_exceptions(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $exceptions = $openingHours->exceptions();

        $this->assertCount(1, $exceptions);
        $this->assertCount(0, $exceptions['2016-09-26']);
    }

    #[Test()]
    public function it_can_return_the_opening_hours_for_a_regular_week_day(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $openingHoursForMonday = $openingHours->forDay('monday');
        $this->assertCount(1, $openingHoursForMonday);
        $this->assertInstanceOf(TimeRange::class, $openingHoursForMonday[0]);
        $this->assertSame('09:00-18:00', (string) $openingHoursForMonday[0]);

        $openingHoursForTuesday = $openingHours->forDay('tuesday');
        $this->assertCount(0, $openingHoursForTuesday);
    }

    #[Test()]
    public function it_can_determine_that_its_regularly_open_on_a_week_day(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $this->assertTrue($openingHours->isOpenOn('monday'));
        $this->assertFalse($openingHours->isOpenOn('tuesday'));
        $this->assertFalse($openingHours->isOpenOn('2019-08-31'));
        $this->assertFalse($openingHours->isOpenOn('2019-09-01'));
        $this->assertTrue($openingHours->isOpenOn('2020-08-31'));
        $this->assertFalse($openingHours->isOpenOn('2020-09-01'));
        $this->assertTrue($openingHours->isOpenOn(
            Date::parse('First Monday of January')->format('m-d'),
        ));
        $this->assertFalse($openingHours->isOpenOn(
            Date::parse('First Tuesday of January')->format('m-d'),
        ));
    }

    #[Test()]
    public function it_can_determine_that_its_regularly_closed_on_a_week_day(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $this->assertFalse($openingHours->isClosedOn('monday'));
        $this->assertTrue($openingHours->isClosedOn('tuesday'));
        $this->assertTrue($openingHours->isClosedOn('2019-08-31'));
        $this->assertTrue($openingHours->isClosedOn('2019-09-01'));
        $this->assertFalse($openingHours->isClosedOn('2020-08-31'));
        $this->assertTrue($openingHours->isClosedOn('2020-09-01'));
        $this->assertFalse($openingHours->isClosedOn(
            Date::parse('First Monday of January')->format('m-d'),
        ));
        $this->assertTrue($openingHours->isClosedOn(
            Date::parse('First Tuesday of January')->format('m-d'),
        ));
    }

    #[Test()]
    public function it_can_return_the_opening_hours_for_a_specific_date(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $openingHoursForMonday1909 = $openingHours->forDate(
            Date::parse('2016-09-19 00:00:00'),
        );
        $openingHoursForMonday2609 = $openingHours->forDate(
            Date::parse('2016-09-26 00:00:00'),
        );

        $this->assertCount(1, $openingHoursForMonday1909);
        $this->assertInstanceOf(TimeRange::class, $openingHoursForMonday1909[0]);
        $this->assertSame('09:00-18:00', (string) $openingHoursForMonday1909[0]);

        $this->assertCount(0, $openingHoursForMonday2609);

        $openingHoursForMonday1909 = $openingHours->forDate(
            CarbonImmutable::parse('2016-09-19 00:00:00'),
        );
        $openingHoursForMonday2609 = $openingHours->forDate(
            CarbonImmutable::parse('2016-09-26 00:00:00'),
        );

        $this->assertCount(1, $openingHoursForMonday1909);
        $this->assertInstanceOf(TimeRange::class, $openingHoursForMonday1909[0]);
        $this->assertSame('09:00-18:00', (string) $openingHoursForMonday1909[0]);

        $this->assertCount(0, $openingHoursForMonday2609);
    }

    #[Test()]
    public function it_can_determine_that_its_open_at_a_certain_date_and_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $shouldBeOpen = Date::parse('2016-09-26 11:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));
        $this->assertFalse($openingHours->isClosedAt($shouldBeOpen));

        $shouldBeOpen = CarbonImmutable::parse('2016-09-26 11:00:00');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpen));
        $this->assertFalse($openingHours->isClosedAt($shouldBeOpen));

        $shouldBeOpenAlternativeDate = date_create_immutable('2016-09-26 11:12:13.123456');
        $this->assertTrue($openingHours->isOpenAt($shouldBeOpenAlternativeDate));
        $this->assertFalse($openingHours->isClosedAt($shouldBeOpenAlternativeDate));

        $shouldBeClosedBecauseOfTime = Date::parse('2016-09-26 20:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosedBecauseOfTime));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosedBecauseOfTime));

        $shouldBeClosedBecauseOfTime = CarbonImmutable::parse('2016-09-26 20:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosedBecauseOfTime));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosedBecauseOfTime));

        $shouldBeClosedBecauseOfDay = Date::parse('2016-09-27 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosedBecauseOfDay));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosedBecauseOfDay));

        $shouldBeClosedBecauseOfDay = CarbonImmutable::parse('2016-09-27 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosedBecauseOfDay));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosedBecauseOfDay));
    }

    #[Test()]
    public function it_can_determine_that_its_open_at_a_certain_date_and_time_on_an_exceptional_day(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $shouldBeClosed = Date::parse('2016-09-26 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosed));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosed));

        $shouldBeClosed = CarbonImmutable::parse('2016-09-26 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($shouldBeClosed));
        $this->assertTrue($openingHours->isClosedAt($shouldBeClosed));
    }

    #[Test()]
    public function it_can_determine_that_its_open_at_a_certain_date_and_time_on_an_recurring_exceptional_day(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'exceptions' => [
                '01-01' => [],
                '12-25' => ['09:00-12:00'],
                '12-26' => [],
            ],
        ]);

        $closedOnNewYearDay = Date::parse('2017-01-01 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnNewYearDay));
        $this->assertTrue($openingHours->isClosedAt($closedOnNewYearDay));

        $closedOnNewYearDay = CarbonImmutable::parse('2017-01-01 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnNewYearDay));
        $this->assertTrue($openingHours->isClosedAt($closedOnNewYearDay));

        $closedOnSecondChristmasDay = Date::parse('2025-12-16 12:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnSecondChristmasDay));
        $this->assertTrue($openingHours->isClosedAt($closedOnSecondChristmasDay));

        $closedOnSecondChristmasDay = CarbonImmutable::parse('2025-12-16 12:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnSecondChristmasDay));
        $this->assertTrue($openingHours->isClosedAt($closedOnSecondChristmasDay));

        $openOnChristmasMorning = Date::parse('2025-12-25 10:00:00');
        $this->assertTrue($openingHours->isOpenAt($openOnChristmasMorning));
        $this->assertFalse($openingHours->isClosedAt($openOnChristmasMorning));

        $openOnChristmasMorning = CarbonImmutable::parse('2025-12-25 10:00:00');
        $this->assertTrue($openingHours->isOpenAt($openOnChristmasMorning));
        $this->assertFalse($openingHours->isClosedAt($openOnChristmasMorning));
    }

    #[Test()]
    public function it_can_prioritize_exceptions_by_giving_full_dates_priority(): void
    {
        $openingHours = OpeningHours::create([
            'exceptions' => [
                '2018-01-01' => ['09:00-18:00'],
                '01-01' => [],
                '12-25' => ['09:00-12:00'],
                '12-26' => [],
            ],
        ]);

        $openOnNewYearDay2018 = Date::parse('2018-01-01 11:00:00');
        $this->assertTrue($openingHours->isOpenAt($openOnNewYearDay2018));
        $this->assertFalse($openingHours->isClosedAt($openOnNewYearDay2018));

        $openOnNewYearDay2018 = CarbonImmutable::parse('2018-01-01 11:00:00');
        $this->assertTrue($openingHours->isOpenAt($openOnNewYearDay2018));
        $this->assertFalse($openingHours->isClosedAt($openOnNewYearDay2018));

        $closedOnNewYearDay2019 = Date::parse('2019-01-01 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnNewYearDay2019));
        $this->assertTrue($openingHours->isClosedAt($closedOnNewYearDay2019));

        $closedOnNewYearDay2019 = CarbonImmutable::parse('2019-01-01 11:00:00');
        $this->assertFalse($openingHours->isOpenAt($closedOnNewYearDay2019));
        $this->assertTrue($openingHours->isClosedAt($closedOnNewYearDay2019));
    }

    #[Test()]
    public function it_can_handle_consecutive_open_hours(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-03:00', '09:00-24:00'],
            'friday' => ['00:00-03:00'],
        ]);

        $monday = Date::parse('2019-02-04 11:00:00');
        $dayHours = $openingHours->forDay('monday');
        $this->assertTrue($openingHours->isOpenAt($monday));
        $this->assertFalse($openingHours->isClosedAt($monday));
        $this->assertSame('09:00-24:00', $dayHours->nextOpenRange(Time::fromString('08:00'))->format());
        // $this->assertFalse($dayHours->previousOpenRange(Time::fromString('08:00')));
        $this->assertNotInstanceOf(TimeRange::class, $dayHours->nextOpenRange(Time::fromString('10:00')));
        $this->assertNotInstanceOf(TimeRange::class, $dayHours->previousOpenRange(Time::fromString('10:00')));
        $this->assertSame('09:00-24:00', $dayHours->nextCloseRange(Time::fromString('08:00'))->format());
        $this->assertNotInstanceOf(TimeRange::class, $dayHours->previousCloseRange(Time::fromString('10:00')));
        $this->assertSame('09:00-24:00', $dayHours->nextCloseRange(Time::fromString('10:00'))->format());
        $this->assertSame('2019-02-06 03:00:00', $openingHours->nextClose($monday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-06 09:00:00', $openingHours->nextOpen($monday)->format('Y-m-d H:i:s'));

        $this->assertSame('2019-02-01 03:00:00', $openingHours->previousClose($monday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-04 09:00:00', $openingHours->previousOpen($monday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-01 00:00:00', $openingHours->previousOpen(
            Date::parse('2019-02-04 08:50:00'),
        )->format('Y-m-d H:i:s'));

        $monday = CarbonImmutable::parse('2019-02-04 11:00:00');
        $this->assertTrue($openingHours->isOpenAt($monday));
        $this->assertFalse($openingHours->isClosedAt($monday));
        $this->assertSame('2019-02-06 03:00:00', $openingHours->nextClose($monday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-06 09:00:00', $openingHours->nextOpen($monday)->format('Y-m-d H:i:s'));

        $wednesday = Date::parse('2019-02-06 09:00:00');
        $dayHours = $openingHours->forDay('wednesday');
        $this->assertTrue($openingHours->isOpenAt($wednesday));
        $this->assertFalse($openingHours->isClosedAt($wednesday));
        $this->assertSame('00:00-03:00', $dayHours->previousCloseRange(Time::fromString('08:00'))->format());
        $this->assertSame('00:00-03:00', $dayHours->previousCloseRange(Time::fromString('08:00'))->format());
        $this->assertSame('00:00', $dayHours->previousOpen(Time::fromString('08:00'))->format());
        $this->assertSame('03:00', $dayHours->previousClose(Time::fromString('08:00'))->format());
        $this->assertSame('2019-02-07 00:00:00', $openingHours->nextClose($wednesday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-08 00:00:00', $openingHours->nextOpen($wednesday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-06 03:00:00', $openingHours->previousClose($wednesday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-04 09:00:00', $openingHours->previousOpen($wednesday)->format('Y-m-d H:i:s'));

        $wednesday = CarbonImmutable::parse('2019-02-06 09:00:00');
        $this->assertTrue($openingHours->isOpenAt($wednesday));
        $this->assertFalse($openingHours->isClosedAt($wednesday));
        $this->assertSame('2019-02-07 00:00:00', $openingHours->nextClose($wednesday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-08 00:00:00', $openingHours->nextOpen($wednesday)->format('Y-m-d H:i:s'));

        $friday = CarbonImmutable::parse('2019-02-08 09:00:00');
        $this->assertSame('2019-02-08 03:00:00', $openingHours->previousClose($friday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-08 00:00:00', $openingHours->previousOpen($friday)->format('Y-m-d H:i:s'));

        $friday = CarbonImmutable::parse('2019-02-08 02:00:00');
        $this->assertSame('2019-02-07 00:00:00', $openingHours->previousClose($friday)->format('Y-m-d H:i:s'));
        $this->assertSame('2019-02-08 00:00:00', $openingHours->previousOpen($friday)->format('Y-m-d H:i:s'));

        $friday = CarbonImmutable::parse('2022-08-05 03:00:00.000001');
        $this->assertSame('2022-08-05 03:00:00', $openingHours->previousClose($friday)->format('Y-m-d H:i:s'));

        $friday = CarbonImmutable::parse('2022-08-05 00:00:00.000000');
        $this->assertSame('2022-08-04 00:00:00', $openingHours->previousClose($friday)->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_non_working_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 09:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_edges_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'], // 2016-09-26
            'tuesday' => ['09:00-11:00', '13:00-19:00'], // 2016-09-27
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 00:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 00:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-20 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 09:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 09:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-20 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 11:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 11:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 09:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 09:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 13:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 13:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 09:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 19:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 19:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 23:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-26 23:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-27 23:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-27 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));

        $previousTimeOpen = $openingHours->previousOpen(
            Date::parse('2016-09-27 08:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $previousTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $previousTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_mixed_structures(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => [
                [
                    'hours' => '09:00-11:00',
                    'data' => ['foobar'],
                ],
                '13:00-19:00',
            ],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 00:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-11 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 00:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 11:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 09:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-11 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 09:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 11:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 10:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-11 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 10:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 11:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 11:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-11 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 11:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-11 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 13:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-18 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 13:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 15:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-18 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 15:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-11 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 19:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-18 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 19:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-18 11:00:00', $nextTimeClose->format('Y-m-d H:i:s'));

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2019-02-11 21:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2019-02-18 09:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2019-02-11 21:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2019-02-18 11:00:00', $nextTimeClose->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_non_working_date_time_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));

        /** @var CustomDate $nextTimeOpen */
        $nextTimeOpen = $openingHours->nextOpen(
            new CustomDate('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(CustomDate::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 13:00:00', $nextTimeOpen->foo());
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_non_working_date_time(): void
    {
        $ranges = [
            'monday' => ['09:00-18:00'],
            /* all the default week settings */
            'exceptions' => [
                // add non-dynamic exceptions, else let empty
            ],
        ];
        $dynamicClosedRanges = [
            '2016-11-07' => ['12:30-13:00'],
        ];

        foreach ($dynamicClosedRanges as $day => $closedRanges) {
            $weekDay = mb_strtolower(
                new DateTime($day)->format('l'),
            );
            $dayRanges = OpeningHoursForDay::fromStrings($ranges[$weekDay]);
            $newRanges = [];

            foreach ($dayRanges as $dayRange) {
                /* @var \Cline\OpeningHours\TimeRange $dayRange */
                foreach ($closedRanges as $exceptionRange) {
                    $range = TimeRange::fromString($exceptionRange);

                    if ($dayRange->containsTime($range->start()) && $dayRange->containsTime($range->end())) {
                        $newRanges[] = TimeRange::fromString($dayRange->start()->format().'-'.$range->start()->format())->format();
                        $newRanges[] = TimeRange::fromString($range->end()->format().'-'.$dayRange->end()->format())->format();

                        continue 2;
                    }

                    if ($dayRange->containsTime($range->start())) {
                        $newRanges[] = TimeRange::fromString($dayRange->start()->format().'-'.$range->start()->format())->format();

                        continue 2;
                    }

                    if ($dayRange->containsTime($range->end())) {
                        $newRanges[] = TimeRange::fromString($range->end()->format().'-'.$dayRange->end()->format())->format();

                        continue 2;
                    }
                }

                $newRanges[] = $dayRange->format();
            }

            $ranges['exceptions'][$day] = $newRanges;
        }

        $openingHours = OpeningHours::createAndMergeOverlappingRanges($ranges);

        $this->assertSame('09:00-12:30,13:00-18:00', (string) $openingHours->forDate(
            Date::parse('2016-11-07'),
        ));
        $this->assertSame('09:00-18:00', (string) $openingHours->forDate(
            Date::parse('2016-11-14'),
        ));
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_non_working_date_time_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
        ]);

        $nextTimeOpen = $openingHours->nextClose(
            CarbonImmutable::parse('2016-09-26 12:00:00'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $nextTimeOpen);
        $this->assertSame('2016-09-26 19:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_working_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 16:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 10:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_working_date_time_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            CarbonImmutable::parse('2016-09-26 16:00:00'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 10:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_working_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
        ]);

        $nextTimeClose = $openingHours->nextClose(
            Date::parse('2016-09-26 16:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeClose);
        $this->assertSame('2016-09-26 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_working_date_time_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
        ]);

        $nextTimeClose = $openingHours->nextClose(
            CarbonImmutable::parse('2016-09-26 16:00:00'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $nextTimeClose);
        $this->assertSame('2016-09-26 19:00:00', $nextTimeClose->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_early_morning(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::parse('2016-09-26 04:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 10:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_open_hours_from_early_morning_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            CarbonImmutable::parse('2016-09-26 04:00:00'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $nextTimeOpen);
        $this->assertSame('2016-09-27 10:00:00', $nextTimeOpen->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_early_morning(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $nextClosedTime = $openingHours->nextClose(
            Date::parse('2016-09-26 04:00:00'),
        );

        $this->assertInstanceOf(DateTime::class, $nextClosedTime);
        $this->assertSame('2016-09-27 11:00:00', $nextClosedTime->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_determine_next_close_hours_from_early_morning_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-11:00', '13:00-19:00'],
            'tuesday' => ['10:00-11:00', '14:00-19:00'],
            'exceptions' => [
                '2016-09-26' => [],
            ],
        ]);

        $nextClosedTime = $openingHours->nextClose(
            CarbonImmutable::parse('2016-09-26 04:00:00'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $nextClosedTime);
        $this->assertSame('2016-09-27 11:00:00', $nextClosedTime->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_set_the_timezone_on_the_openings_hours_object(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'exceptions' => [
                '2016-11-14' => ['09:00-13:00'],
            ],
        ], 'Europe/Amsterdam');

        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 10:00', new DateTimeZone('UTC')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 15:59', new DateTimeZone('UTC')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 08:00', new DateTimeZone('UTC')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 07:00', new DateTimeZone('UTC')),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-10-10 06:00', new DateTimeZone('UTC')),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-10-10 06:59', new DateTimeZone('UTC')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-10-10 06:00', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-10-10 08:59', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 09:00', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 17:59', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-10-10 18:01', new DateTimeZone('Europe/Amsterdam')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-11-14 17:59', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-11-14 12:59', new DateTimeZone('Europe/Amsterdam')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-11-14 15:59', new DateTimeZone('America/Denver')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 09:59', new DateTimeZone('America/Denver')),
        ));

        $this->assertTrue($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 10:00', new DateTimeZone('UTC')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 15:59', new DateTimeZone('UTC')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 08:00', new DateTimeZone('UTC')),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 06:00', new DateTimeZone('UTC')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 06:00', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 09:00', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTimeImmutable('2016-10-10 17:59', new DateTimeZone('Europe/Amsterdam')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-11-14 17:59', new DateTimeZone('Europe/Amsterdam')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-11-14 12:59', new DateTimeZone('Europe/Amsterdam')),
        ));

        $this->assertFalse($openingHours->isOpenAt(
            new DateTime('2016-11-14 15:59', new DateTimeZone('America/Denver')),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            new DateTime('2016-10-10 09:59', new DateTimeZone('America/Denver')),
        ));

        $timezone = date_default_timezone_get();
        date_default_timezone_set('America/Denver');

        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2016-10-10 09:59'),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            Date::parse('2016-10-10 10:00'),
        ));

        $this->assertTrue($openingHours->isOpenAt(
            CarbonImmutable::parse('2016-10-10 09:59'),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            CarbonImmutable::parse('2016-10-10 10:00'),
        ));

        date_default_timezone_set($timezone);
    }

    #[Test()]
    #[DataProvider('provideIt_can_handle_timezone_for_date_stringCases')]
    public function it_can_handle_timezone_for_date_string(string|DateTimeZone|null $timezone): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ], $timezone);
        $this->assertFalse($openingHours->isOpenOn('2020-10-20'));
        $this->assertTrue($openingHours->isOpenOn('2020-10-19'));
    }

    public static function provideIt_can_handle_timezone_for_date_stringCases(): iterable
    {
        yield ['-12:00'];

        yield ['America/Denver'];

        yield ['UTC'];

        yield ['+13:30'];
    }

    #[Test()]
    public function it_can_determine_that_its_open_now(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-23:59'],
            'tuesday' => ['00:00-23:59'],
            'wednesday' => ['00:00-23:59'],
            'thursday' => ['00:00-23:59'],
            'friday' => ['00:00-23:59'],
            'saturday' => ['00:00-23:59'],
            'sunday' => ['00:00-23:59'],
        ]);

        $this->assertTrue($openingHours->isOpen());
    }

    #[Test()]
    public function it_can_use_day_enum(): void
    {
        $openingHours = OpeningHours::create([]);
        $method = new ReflectionMethod(OpeningHours::class, 'readDatesRange');
        $days = $method->invoke($openingHours, Day::MONDAY);

        if ($days instanceof Traversable) {
            $days = iterator_to_array($days);
        }

        $this->assertSame(['monday'], $days);
    }

    #[Test()]
    public function it_can_determine_that_its_closed_now(): void
    {
        $openingHours = OpeningHours::create([]);

        $this->assertTrue($openingHours->isClosed());
    }

    #[Test()]
    public function it_can_retrieve_regular_closing_days_as_strings(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-18:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-18:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertSame(['saturday', 'sunday'], $openingHours->regularClosingDays());
    }

    #[Test()]
    public function it_can_retrieve_regular_closing_days_as_iso_numbers(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'tuesday' => ['09:00-18:00'],
            'wednesday' => ['09:00-18:00'],
            'thursday' => ['09:00-18:00'],
            'friday' => ['09:00-18:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertSame([6, 7], $openingHours->regularClosingDaysISO());
    }

    #[Test()]
    public function it_can_retrieve_a_list_of_exceptional_closing_dates(): void
    {
        $openingHours = OpeningHours::create([
            'exceptions' => [
                '2017-06-01' => [],
                '2017-06-02' => [],
            ],
        ]);

        $exceptionalClosingDates = $openingHours->exceptionalClosingDates();

        $this->assertCount(2, $exceptionalClosingDates);
        $this->assertSame('2017-06-01', $exceptionalClosingDates[0]->format('Y-m-d'));
        $this->assertSame('2017-06-02', $exceptionalClosingDates[1]->format('Y-m-d'));
    }

    #[Test()]
    public function it_works_when_starting_at_midnight(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-16:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            Date::now(),
        );
        $this->assertInstanceOf(DateTime::class, $nextTimeOpen);
    }

    #[Test()]
    public function it_works_when_starting_at_midnight_immutable(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-16:00'],
        ]);

        $nextTimeOpen = $openingHours->nextOpen(
            CarbonImmutable::now(),
        );
        $this->assertInstanceOf(DateTimeImmutable::class, $nextTimeOpen);
    }

    #[Test()]
    public function it_can_set_the_timezone_on_construct_with_date_time_zone(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-16:00'],
        ], new DateTimeZone('Asia/Taipei'));
        $openingHoursForWeek = $openingHours->forWeek();

        $this->assertCount(7, $openingHoursForWeek);
        $this->assertSame('00:00-16:00', (string) $openingHoursForWeek['monday'][0]);
        $this->assertCount(0, $openingHoursForWeek['tuesday']);
        $this->assertCount(0, $openingHoursForWeek['wednesday']);
        $this->assertCount(0, $openingHoursForWeek['thursday']);
        $this->assertCount(0, $openingHoursForWeek['friday']);
        $this->assertCount(0, $openingHoursForWeek['saturday']);
        $this->assertCount(0, $openingHoursForWeek['sunday']);
    }

    #[Test()]
    public function it_can_set_the_timezone_on_construct_with_string(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-16:00'],
        ], 'Asia/Taipei');
        $openingHoursForWeek = $openingHours->forWeek();

        $this->assertCount(7, $openingHoursForWeek);
        $this->assertSame('00:00-16:00', (string) $openingHoursForWeek['monday'][0]);
        $this->assertCount(0, $openingHoursForWeek['tuesday']);
        $this->assertCount(0, $openingHoursForWeek['wednesday']);
        $this->assertCount(0, $openingHoursForWeek['thursday']);
        $this->assertCount(0, $openingHoursForWeek['friday']);
        $this->assertCount(0, $openingHoursForWeek['saturday']);
        $this->assertCount(0, $openingHoursForWeek['sunday']);
    }

    #[Test()]
    public function it_throws_an_exception_on_invalid_timezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Timezone');

        OpeningHours::create([
            'timezone' => ['input' => ['foo']],
        ]);
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_void_array_next_open(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No open date/time found in the next 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([])->nextOpen(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_void_array_previous_open(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No open date/time found in the previous 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([])->previousOpen(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_full_array_next_open(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No open date/time found in the next 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
        ])->nextOpen(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_full_array_previous_open(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No open date/time found in the previous 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
        ])->previousOpen(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_full_array_next_open_with_exceptions(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No open date/time found in the next 366 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
            'exceptions' => [
                '2022-09-05' => [],
            ],
        ])->nextOpen(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_search_limit_exceeded_with_next_open(): void
    {
        $this->expectException(SearchLimitReached::class);
        $this->expectExceptionMessage('Search reached the limit: 2019-06-13 19:02:00.000000 UTC');

        OpeningHours::create([])->nextOpen(
            Date::parse('2019-06-06 19:02:00'),
            Date::parse('2019-06-13 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_search_limit_exceeded_with_next_close(): void
    {
        $this->expectException(SearchLimitReached::class);
        $this->expectExceptionMessage('Search reached the limit: 2019-06-13 19:02:00.000000 UTC');

        OpeningHours::create([])->nextClose(
            Date::parse('2019-06-06 19:02:00'),
            Date::parse('2019-06-13 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_search_limit_exceeded_with_previous_open(): void
    {
        $this->expectException(SearchLimitReached::class);
        $this->expectExceptionMessage('Search reached the limit: 2019-06-03 19:02:00.000000 UTC');

        OpeningHours::create([])->previousOpen(
            Date::parse('2019-06-06 19:02:00'),
            Date::parse('2019-06-03 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_search_limit_exceeded_with_previous_close(): void
    {
        $this->expectException(SearchLimitReached::class);
        $this->expectExceptionMessage('Search reached the limit: 2019-06-03 19:02:00.000000 UTC');

        OpeningHours::create([])->previousClose(
            Date::parse('2019-06-06 19:02:00'),
            Date::parse('2019-06-03 19:02:00'),
        );
    }

    #[Test()]
    public function it_stops_at_cap_limit_with_next_open(): void
    {
        $this->assertSame(
            '2019-06-13 19:02:00',
            OpeningHours::create([])->nextOpen(
                Date::parse('2019-06-06 19:02:00'),
                null,
                Date::parse('2019-06-13 19:02:00'),
            )->format('Y-m-d H:i:s'),
        );
    }

    #[Test()]
    public function it_stops_at_cap_limit_exceeded_with_next_close(): void
    {
        $this->assertSame(
            '2019-06-13 19:02:00',
            OpeningHours::create([])->nextClose(
                Date::parse('2019-06-06 19:02:00'),
                null,
                Date::parse('2019-06-13 19:02:00'),
            )->format('Y-m-d H:i:s'),
        );
    }

    #[Test()]
    public function it_stops_at_cap_limit_with_previous_open(): void
    {
        $this->assertSame(
            '2019-06-03 19:02:00',
            OpeningHours::create([])->previousOpen(
                Date::parse('2019-06-06 19:02:00'),
                null,
                Date::parse('2019-06-03 19:02:00'),
            )->format('Y-m-d H:i:s'),
        );
    }

    #[Test()]
    public function it_stops_at_cap_limit_exceeded_with_previous_close(): void
    {
        $this->assertSame(
            '2019-06-03 19:02:00',
            OpeningHours::create([])->previousClose(
                Date::parse('2019-06-06 19:02:00'),
                null,
                Date::parse('2019-06-03 19:02:00'),
            )->format('Y-m-d H:i:s'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_void_array_next_close(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No close date/time found in the next 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([])->nextClose(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_void_array_previous_close(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No close date/time found in the previous 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([])->previousClose(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_full_array_next_close(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No close date/time found in the next 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
        ])->nextClose(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_throws_an_exception_on_limit_exceeded_full_array_previous_close(): void
    {
        $this->expectException(MaximumLimitExceeded::class);
        $this->expectExceptionMessage('No close date/time found in the previous 8 days, use $openingHours->setDayLimit() to increase the limit.');

        OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
        ])->previousClose(
            Date::parse('2019-06-06 19:02:00'),
        );
    }

    #[Test()]
    public function it_should_handle_far_exception(): void
    {
        $this->assertSame('2019-12-25 00:00:00', OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
            'exceptions' => [
                '12-25' => [],
            ],
        ])->nextClose(
            Date::parse('2019-06-06 19:02:00'),
        )->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_handle_very_far_future_exception_by_changing_limit(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
            'exceptions' => [
                '2022-12-25' => [],
            ],
        ]);
        $openingHours->setDayLimit(3_000);

        $this->assertSame('2022-12-25 00:00:00', $openingHours->nextClose(
            Date::parse('2019-06-06 19:02:00'),
        )->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_handle_very_far_past_exception_by_changing_limit(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['00:00-24:00'],
            'tuesday' => ['00:00-24:00'],
            'wednesday' => ['00:00-24:00'],
            'thursday' => ['00:00-24:00'],
            'friday' => ['00:00-24:00'],
            'saturday' => ['00:00-24:00'],
            'sunday' => ['00:00-24:00'],
            'exceptions' => [
                '2013-12-25' => [],
            ],
        ]);
        $openingHours->setDayLimit(3_000);

        $this->assertSame('2013-12-26 00:00:00', $openingHours->previousOpen(
            Date::parse('2019-06-06 19:02:00'),
        )->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_handle_open_range(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['10:00-16:00', '19:30-20:30'],
            'tuesday' => ['22:30-04:00'],
            'wednesday' => ['07:00-10:00'],
            'thursday' => ['09:00-12:00'],
            'friday' => ['09:00-12:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertNotInstanceOf(TimeRange::class, $openingHours->currentOpenRange(
            Date::parse('2019-07-15 08:00:00'),
        ));
        $this->assertNotInstanceOf(TimeRange::class, $openingHours->currentOpenRange(
            Date::parse('2019-07-15 17:00:00'),
        ));
        $this->assertNotInstanceOf(TimeRange::class, $openingHours->currentOpenRange(
            Date::parse('2019-07-16 22:00:00'),
        ));
        $this->assertNotInstanceOf(TimeRange::class, $openingHours->currentOpenRange(
            Date::parse('2019-07-17 04:00:00'),
        ));
        $range = $openingHours->currentOpenRange(
            Date::parse('2019-07-15 11:00:00'),
        );
        $this->assertInstanceOf(TimeRange::class, $range);
        $this->assertSame('10:00-16:00', $range->format());
        $this->assertSame('19:30-20:30', $openingHours->currentOpenRange(
            Date::parse('2019-07-15 20:00:00'),
        )->format());
        $this->assertSame('22:30-04:00', $openingHours->currentOpenRange(
            Date::parse('2019-07-16 22:30:00'),
        )->format());
        $this->assertSame('22:30-04:00', $openingHours->currentOpenRange(
            Date::parse('2019-07-16 22:40:00'),
        )->format());
        $this->assertSame('22:30-04:00', $openingHours->currentOpenRange(
            Date::parse('2019-07-17 03:59:59'),
        )->format());
        $this->assertSame('07:00-10:00', $openingHours->currentOpenRange(
            Date::parse('2019-07-17 07:59:59'),
        )->format());

        $this->assertNull($openingHours->currentOpenRange(
            Date::parse('2019-07-15 08:00:00'),
        ));
        $period = $openingHours->currentOpenRange(
            Date::parse('2019-07-15 11:00:00'),
        );
        $this->assertInstanceOf(TimeRange::class, $period);
        $this->assertSame('2019-07-15 10:00:00', $period->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-15 16:00:00', $period->end()->format('Y-m-d H:i:s'));

        $openingHours = OpeningHours::create([
            'overflow' => true,
            'monday' => ['10:00-16:00', '19:30-02:30'],
        ]);

        $this->assertNotInstanceOf(TimeRange::class, $openingHours->currentOpenRange(
            Date::parse('2020-09-21 18:00'),
        ));
        $range = $openingHours->currentOpenRange(
            Date::parse('2020-09-21 22:00'),
        );
        $this->assertInstanceOf(TimeRange::class, $range);
        $this->assertSame('2020-09-21 19:30', $range->start()->format('Y-m-d H:i'));
        $this->assertInstanceOf(TimeRange::class, $range);
        $this->assertSame('2020-09-22 02:30', $range->end()->format('Y-m-d H:i'));
        $this->assertInstanceOf(DateTime::class, $range->start()->date());
        $this->assertSame('2020-09-21 19:30', $range->start()->date()->format('Y-m-d H:i'));
        $this->assertInstanceOf(DateTime::class, $range->end()->date());
        $this->assertSame('2020-09-22 02:30', $range->end()->date()->format('Y-m-d H:i'));
    }

    #[Test()]
    public function it_should_handle_open_start_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['10:00-16:00', '19:30-20:30'],
            'tuesday' => ['22:30-04:00'],
            'wednesday' => ['07:00-10:00'],
            'thursday' => ['09:00-12:00'],
            'friday' => ['09:00-12:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-15 08:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-15 17:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-16 22:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-17 04:00:00'),
        ));
        $this->assertSame('2019-07-15 10:00:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-15 11:00:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-15 19:30:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-15 20:00:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-16 22:30:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-16 22:30:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-16 22:30:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-16 22:40:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-16 22:30:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-17 03:59:59'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-17 07:00:00', $openingHours->currentOpenRangeStart(
            Date::parse('2019-07-17 07:59:59'),
        )->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_handle_open_end_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['10:00-16:00', '19:30-20:30'],
            'tuesday' => ['22:30-04:00'],
            'wednesday' => ['07:00-10:00'],
            'thursday' => ['09:00-12:00'],
            'friday' => ['09:00-12:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-15 08:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-15 17:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-16 22:00:00'),
        ));
        $this->assertNotInstanceOf(DateTimeInterface::class, $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-17 04:00:00'),
        ));
        $this->assertSame('2019-07-15 16:00:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-15 11:00:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-15 20:30:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-15 20:00:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-17 04:00:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-16 22:30:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-17 04:00:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-16 22:40:00'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-17 04:00:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-17 03:59:59'),
        )->format('Y-m-d H:i:s'));
        $this->assertSame('2019-07-17 10:00:00', $openingHours->currentOpenRangeEnd(
            Date::parse('2019-07-17 07:59:59'),
        )->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_should_support_empty_arrays_with_merge(): void
    {
        $hours = OpeningHours::createAndMergeOverlappingRanges(
            [
                'exceptions' => [
                    '01-01' => [
                        'hours' => [],
                        'data' => [
                            'id' => 'my_id',
                        ],
                    ],
                    '02-02' => [
                        'hours' => [],
                        'data' => [
                            'id' => 'my_id',
                        ],
                    ],
                ],
            ],
        );

        $this->assertTrue($hours->isClosedAt(
            CarbonImmutable::parse('2020-01-01'),
        ));
    }

    #[Test()]
    public function it_can_calculate_time_diff(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['10:00-16:00', '19:30-20:30'],
            'tuesday' => ['22:30-04:00'],
            'wednesday' => ['07:00-10:00'],
            'thursday' => ['09:00-12:00'],
            'friday' => ['09:00-12:00'],
            'saturday' => [],
            'sunday' => [],
        ]);

        $this->assertEqualsWithDelta(2.0, $openingHours->diffInClosedSeconds(
            CarbonImmutable::parse('Monday 09:59:58'),
            CarbonImmutable::parse('Monday 10:59:58'),
        ), PHP_FLOAT_EPSILON);
        $this->assertSame(3_600.0 - 2.0, $openingHours->diffInOpenSeconds(
            CarbonImmutable::parse('Monday 09:59:58'),
            CarbonImmutable::parse('Monday 10:59:58'),
        ));
        $this->assertEqualsWithDelta(1.5, $openingHours->diffInClosedSeconds(
            CarbonImmutable::parse('Monday 09:59:58.5'),
            CarbonImmutable::parse('Monday 10:59:58.5'),
        ), PHP_FLOAT_EPSILON);
        $this->assertSame(3_600.0 - 1.5, $openingHours->diffInOpenSeconds(
            CarbonImmutable::parse('Monday 09:59:58.5'),
            CarbonImmutable::parse('Monday 10:59:58.5'),
        ));

        $this->assertSame(1.5 * 60, $openingHours->diffInOpenMinutes(
            CarbonImmutable::parse('Monday 3pm'),
            CarbonImmutable::parse('Monday 8pm'),
        ));
        $this->assertSame(3.5 * 60, $openingHours->diffInClosedMinutes(
            CarbonImmutable::parse('Monday 3pm'),
            CarbonImmutable::parse('Monday 8pm'),
        ));
        $this->assertEqualsWithDelta(18.5, $openingHours->diffInOpenHours(
            CarbonImmutable::parse('2020-06-21 3pm'),
            CarbonImmutable::parse('2020-06-25 2pm'),
        ), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(76.5, $openingHours->diffInClosedHours(
            CarbonImmutable::parse('2020-06-21 3pm'),
            CarbonImmutable::parse('2020-06-25 2pm'),
        ), PHP_FLOAT_EPSILON);
        $this->assertSame(-18.5, $openingHours->diffInOpenHours(
            CarbonImmutable::parse('2020-06-25 2pm'),
            CarbonImmutable::parse('2020-06-21 3pm'),
        ));
        $this->assertSame(-76.5, $openingHours->diffInClosedHours(
            CarbonImmutable::parse('2020-06-25 2pm'),
            CarbonImmutable::parse('2020-06-21 3pm'),
        ));
    }

    public function test_merge_overlapping_supports_hours_and_ignore_data(): void
    {
        $data = OpeningHours::mergeOverlappingRanges([
            'monday' => [
                ['hours' => '09:00-21:00', 'data' => 'foobar'],
                ['hours' => '20:00-23:00'],
            ],
            'tuesday' => [[' hours' => '09:00-18:00']],
        ]);

        $monday = OpeningHours::create($data)->forDay('Monday');

        $this->assertNull($monday->data);
        $this->assertNull($monday[0]->data);
        $this->assertSame('09:00-23:00', (string) $monday);
    }

    public function test_hours_range_are_kept(): void
    {
        $data = OpeningHours::mergeOverlappingRanges([
            'monday' => [
                'hours' => [
                    '09:00-12:00',
                    '13:00-18:00',
                ],
            ],
        ]);

        $monday = OpeningHours::create($data)->forDay('Monday');

        $this->assertNull($monday->data);
        $this->assertNull($monday[0]->data);
        $this->assertSame('09:00-12:00,13:00-18:00', (string) $monday);
    }

    public function test_search_with_empty_hours(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
            'sunday' => [],
            'exceptions' => [
                '2016-11-11' => ['09:00-12:00'],
            ],
        ]);

        $minutes = $openingHours->diffInClosedMinutes(
            CarbonImmutable::parse('2023-05-17 12:00'),
            CarbonImmutable::parse('2023-05-23 12:00'),
        );

        $this->assertEqualsWithDelta(6.0, $minutes / 60 / 24, PHP_FLOAT_EPSILON);

        $minutes = $openingHours->diffInOpenMinutes(
            CarbonImmutable::parse('2023-05-17 12:00'),
            CarbonImmutable::parse('2023-05-23 12:00'),
        );

        $this->assertEqualsWithDelta(0.0, $minutes, PHP_FLOAT_EPSILON);
    }

    public function test_ranges(): void
    {
        $openingHours = OpeningHours::create([
            'monday - wednesday' => ['08:30-12:00', '14:30-16:00'],
            'thursday to friday' => ['14:30-18:00'],
            'saturday-sunday' => [],
            'exceptions' => [
                '2016-11-11-2016-11-14' => ['09:00-12:00'],
                '11-30-12-01' => ['09:00-14:00'],
                '12-24 to 12-26' => [],
                '11-10 - 11-12' => ['07:00-10:00'],
            ],
        ]);

        $this->assertSame([
            'monday' => '08:30-12:00,14:30-16:00',
            'tuesday' => '08:30-12:00,14:30-16:00',
            'wednesday' => '08:30-12:00,14:30-16:00',
            'thursday' => '14:30-18:00',
            'friday' => '14:30-18:00',
            'saturday' => '',
            'sunday' => '',
        ], array_map(
            static fn (OpeningHoursForDay $day): string => (string) $day,
            $openingHours->forWeek(),
        ));
        $this->assertSame('07:00-10:00', (string) $openingHours->forDate(
            CarbonImmutable::parse('2016-11-10 11:00'),
        ));
        $this->assertSame('09:00-12:00', (string) $openingHours->forDate(
            CarbonImmutable::parse('2016-11-12 11:00'),
        ));
        $this->assertSame('09:00-14:00', (string) $openingHours->forDate(
            CarbonImmutable::parse('2023-12-01 11:00'),
        ));
        $this->assertSame('', (string) $openingHours->forDate(
            CarbonImmutable::parse('2024-12-25 11:00'),
        ));
    }

    public function test_ranges_week_overlap(): void
    {
        $this->expectException(InvalidDateRange::class);
        $this->expectExceptionMessage('Unable to record `tuesday to friday` as it would override `tuesday`.');

        OpeningHours::create([
            'monday - wednesday' => ['08:30-12:00', '14:30-16:00'],
            'tuesday to friday' => ['14:30-18:00'],
        ]);
    }

    public function test_ranges_exception_overlap(): void
    {
        $this->expectException(InvalidDateRange::class);
        $this->expectExceptionMessage('Unable to record `11-10 to 11-12` as it would override `11-11`.');

        OpeningHours::create([
            'monday - wednesday' => ['08:30-12:00', '14:30-16:00'],
            'exceptions' => [
                '11-11-11-14' => ['09:00-12:00'],
                '11-10 to 11-12' => ['07:00-10:00'],
            ],
        ]);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_first_day_minute_open_every_day(bool $overflow): void
    {
        $config = [
            'monday' => ['00:01-00:00'],
            'tuesday' => ['00:01-00:00'],
            'wednesday' => ['00:01-00:00'],
            'thursday' => ['00:01-00:00'],
            'friday' => ['00:01-00:00'],
            'saturday' => ['00:01-00:00'],
            'sunday' => ['00:01-00:00'],
            'overflow' => $overflow,
        ];
        $openingHoursObject = OpeningHours::create($config);

        $nextOpen = $openingHoursObject->nextOpen(
            CarbonImmutable::parse('2024-10-22 00:00:00'),
        );

        $this->assertSame('2024-10-22 00:01:00.000000', $nextOpen->format('Y-m-d H:i:s.u'));

        $nextOpen = $openingHoursObject->nextOpen(
            CarbonImmutable::parse('2024-10-22 00:00:30'),
        );

        $this->assertSame('2024-10-22 00:01:00.000000', $nextOpen->format('Y-m-d H:i:s.u'));

        $nextOpen = $openingHoursObject->nextOpen(
            CarbonImmutable::parse('2024-10-22 00:01:00'),
        );

        $this->assertSame('2024-10-23 00:01:00.000000', $nextOpen->format('Y-m-d H:i:s.u'));

        $nextOpen = $openingHoursObject->nextOpen(
            CarbonImmutable::parse('2024-10-22 00:01:00'),
        );

        $this->assertSame('2024-10-23 00:01:00.000000', $nextOpen->format('Y-m-d H:i:s.u'));

        $nextOpen = $openingHoursObject->nextOpen(
            CarbonImmutable::parse('2024-10-22 23:59:59.999999'),
        );

        $this->assertSame('2024-10-23 00:01:00.000000', $nextOpen->format('Y-m-d H:i:s.u'));
    }
}
