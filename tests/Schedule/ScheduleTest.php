<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Config\QueryOptions;
use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\Schedule;
use Cline\OpeningHours\Schedule\WeeklySchedule;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTimeRange;
use DateTimeImmutable;
use DateTimeZone;

use function expect;
use function test;

test('it resolves a date override before weekly hours', function (): void {
    $schedule = new Schedule(
        WeeklySchedule::fromDaySchedules([
            Day::MONDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('09:00-17:00'),
            ),
        ]),
        [
            new DateOverrideRule(
                '2026-03-09',
                DaySchedule::fromRanges(LocalTimeRange::fromString('12:00-14:00')),
            ),
        ],
    );

    $daySchedule = $schedule->forDate(
        CarbonImmutable::parse('2026-03-09'),
    );

    expect((string) $daySchedule)->toBe('12:00-14:00');
});

test('it uses query options to resolve timezone sensitive queries', function (): void {
    $schedule = new Schedule(
        WeeklySchedule::fromDaySchedules([
            Day::SATURDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('10:00-12:00'),
            ),
        ]),
    );

    $options = new QueryOptions('Europe/Helsinki');

    expect($schedule->isOpenAt(
        new DateTimeImmutable('2026-03-07 08:30:00', new DateTimeZone('UTC')),
        $options,
    ))->toBeTrue();
});

test('it treats wrapping ranges from the previous day as open', function (): void {
    $schedule = new Schedule(
        WeeklySchedule::fromDaySchedules([
            Day::FRIDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('22:00-02:00'),
            ),
        ]),
    );

    expect($schedule->isOpenAt(
        CarbonImmutable::parse('2026-03-07 01:30:00'),
    ))->toBeTrue();
});
