<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Config\QueryOptions;
use Cline\OpeningHours\OpeningHours;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\WeeklySchedule;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTimeRange;

test('it can be built from typed schedules', function (): void {
    $openingHours = OpeningHours::fromWeeklySchedule(
        WeeklySchedule::fromDaySchedules([
            Day::MONDAY->value => DaySchedule::fromRanges(
                LocalTimeRange::fromString('09:00-17:00'),
            ),
        ]),
    );

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-09 10:00:00'),
    ))->toBeTrue();
});

test('it can build the new core from a legacy style array', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'exceptions' => [
            '2026-03-09' => ['12:00-14:00'],
            '12-24' => [],
        ],
    ]);

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-09 10:00:00'),
    ))->toBeFalse();
    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-09 13:00:00'),
    ))->toBeTrue();
    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-12-24 13:00:00'),
    ))->toBeFalse();
});

test('it supports date range overrides in the array adapter', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'tuesday' => ['09:00-17:00'],
        'exceptions' => [
            '2026-03-09 to 2026-03-10' => ['12:00-14:00'],
        ],
    ]);

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-10 13:00:00'),
    ))->toBeTrue();
    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-10 10:00:00'),
    ))->toBeFalse();
});

test('it returns null when no future opening exists within the search limit', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => [],
    ]);

    expect($openingHours->nextOpen(
        CarbonImmutable::parse('2026-03-09 10:00:00'),
        new QueryOptions(maxDaysToSearch: 2),
    ))->not->toBeInstanceOf(DateTimeImmutable::class);
});
