<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\OpeningHours\Exceptions\DaySchedulesCannotContainOverlappingRanges;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Value\LocalTime;
use Cline\OpeningHours\Value\LocalTimeRange;

test('it rejects overlapping ranges', function (): void {
    expect(fn (): DaySchedule => DaySchedule::fromRanges(
        LocalTimeRange::fromString('09:00-18:00'),
        LocalTimeRange::fromString('10:00-12:00'),
    ))->toThrow(DaySchedulesCannotContainOverlappingRanges::class);
});

test('it returns an empty schedule when no ranges are given', function (): void {
    $schedule = DaySchedule::closed();

    expect($schedule->isClosed())->toBeTrue()
        ->and($schedule->ranges())->toHaveCount(0);
});

test('it can detect always open schedules and render ranges', function (): void {
    $schedule = DaySchedule::fromRanges(
        LocalTimeRange::fromString('00:00-24:00'),
    );

    expect($schedule->isAlwaysOpen())->toBeTrue()
        ->and((string) $schedule)->toBe('00:00-24:00');
});

test('it sorts ranges and checks membership and day overflow', function (): void {
    $schedule = DaySchedule::fromRanges(
        LocalTimeRange::fromString('18:00-20:00'),
        LocalTimeRange::fromString('09:00-12:00'),
        LocalTimeRange::fromString('22:00-02:00'),
    );

    expect(array_map(static fn (LocalTimeRange $range): string => $range->format(), $schedule->ranges()))
        ->toBe(['09:00-12:00', '18:00-20:00', '22:00-02:00'])
        ->and($schedule->contains(LocalTime::fromString('09:30')))->toBeTrue()
        ->and($schedule->contains(LocalTime::fromString('12:00')))->toBeFalse()
        ->and($schedule->carriesIntoNextDay())->toBeTrue()
        ->and($schedule->isClosed())->toBeFalse()
        ->and($schedule->isAlwaysOpen())->toBeFalse();
});

test('it reports when a schedule does not carry into the next day', function (): void {
    $schedule = DaySchedule::fromRanges(
        LocalTimeRange::fromString('09:00-12:00'),
    );

    expect($schedule->carriesIntoNextDay())->toBeFalse()
        ->and($schedule->contains(LocalTime::fromString('08:59')))->toBeFalse();
});
