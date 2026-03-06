<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Rule\DateRangeOverrideRule;
use Cline\OpeningHours\Rule\MonthDayOverrideRule;
use Cline\OpeningHours\Rule\ScheduleRule;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\Schedule;
use Cline\OpeningHours\Schedule\WeeklySchedule;
use Cline\OpeningHours\SchemaOrg\SchemaOrgOpeningHoursFormatter;
use Cline\OpeningHours\SchemaOrg\SchemaOrgOpeningHoursParser;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTimeRange;
use DateTimeInterface;

use function expect;
use function test;

test('it parses schema org structured data into a typed schedule', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Monday',
            'opens' => '09:00',
            'closes' => '17:00',
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'validFrom' => '2026-12-24',
            'validThrough' => '2026-12-24',
            'opens' => '00:00',
            'closes' => '00:00',
        ],
    ]);

    expect((string) $schedule->weeklySchedule()->forDay(Day::MONDAY))->toBe('09:00-17:00')
        ->and($schedule->rules())->toHaveCount(1);
});

test('it merges weekly entries and creates all override rule types', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([
        [
            'dayOfWeek' => 'Monday',
            'opens' => '09:00',
            'closes' => '12:00',
        ],
        [
            'dayOfWeek' => 'Monday',
            'opens' => '13:00',
            'closes' => '17:00',
        ],
        [
            'validFrom' => '2026-12-24',
            'validThrough' => '2026-12-24',
            'opens' => '10:00',
            'closes' => '12:00',
        ],
        [
            'validFrom' => '12-31',
            'validThrough' => '12-31',
            'opens' => '11:00',
            'closes' => '13:00',
        ],
        [
            'validFrom' => '2026-12-25',
            'validThrough' => '2026-12-26',
            'opens' => '14:00',
            'closes' => '16:00',
        ],
    ]);

    expect((string) $schedule->weeklySchedule()->forDay(Day::MONDAY))->toBe('09:00-12:00,13:00-17:00')
        ->and($schedule->rules())->toHaveCount(3)
        ->and($schedule->rules()[0])->toBeInstanceOf(DateOverrideRule::class)
        ->and($schedule->rules()[1])->toBeInstanceOf(MonthDayOverrideRule::class)
        ->and($schedule->rules()[2])->toBeInstanceOf(DateRangeOverrideRule::class);
});

test('it treats null open and close values as a closed schedule', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([
        [
            'dayOfWeek' => 'Monday',
            'opens' => null,
            'closes' => null,
        ],
    ]);

    expect($schedule->weeklySchedule()->forDay(Day::MONDAY)->isClosed())->toBeTrue();
});

test('it formats a typed schedule as schema org structured data', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Monday',
            'opens' => '09:00',
            'closes' => '17:00',
        ],
    ]);

    $formatted = SchemaOrgOpeningHoursFormatter::format($schedule);

    expect($formatted[0]['dayOfWeek'])->toBe('Monday')
        ->and($formatted[0]['opens'])->toBe('09:00')
        ->and($formatted[0]['closes'])->toBe('17:00');
});

test('it formats rule overrides and closed days for schema org', function (): void {
    $schedule = new Schedule(
        WeeklySchedule::fromDaySchedules([
            Day::MONDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('09:00-17:00'),
            ),
        ]),
        [
            new DateOverrideRule('2026-12-24', DaySchedule::closed()),
            new DateRangeOverrideRule(
                '2026-12-25',
                '2026-12-26',
                DaySchedule::fromRanges(LocalTimeRange::fromString('10:00-12:00')),
            ),
            new MonthDayOverrideRule(
                '12-31',
                DaySchedule::fromRanges(LocalTimeRange::fromString('11:00-13:00')),
            ),
        ],
    );

    $formatted = SchemaOrgOpeningHoursFormatter::format($schedule);

    expect($formatted[1]['opens'])->toBe('00:00')
        ->and($formatted[1]['closes'])->toBe('00:00')
        ->and($formatted[1]['validFrom'])->toBe('2026-12-24')
        ->and($formatted[2]['validThrough'])->toBe('2026-12-26')
        ->and($formatted[3]['validFrom'])->toBe('12-31')
        ->and($formatted[3]['closes'])->toBe('13:00');
});

test('it ignores unsupported rule types when formatting schema org', function (): void {
    $schedule = new Schedule(
        WeeklySchedule::fromDaySchedules([
            Day::MONDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('09:00-17:00'),
            ),
        ]),
        [
            new class() implements ScheduleRule
            {
                public function appliesTo(DateTimeInterface $date): bool
                {
                    return false;
                }

                public function schedule(): DaySchedule
                {
                    return DaySchedule::closed();
                }
            },
        ],
    );

    $formatted = SchemaOrgOpeningHoursFormatter::format($schedule);

    expect($formatted)->toHaveCount(1)
        ->and($formatted[0]['dayOfWeek'])->toBe('Monday');
});
