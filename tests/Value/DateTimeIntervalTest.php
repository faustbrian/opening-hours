<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Value\DateTimeInterval;
use Cline\OpeningHours\Value\LocalTimeRange;

test('it anchors a wrapping local range to concrete dates', function (): void {
    $interval = DateTimeInterval::fromLocalTimeRange(
        CarbonImmutable::parse('2026-03-06 23:30:00'),
        LocalTimeRange::fromString('22:00-02:00'),
    );

    expect($interval->start()->format('Y-m-d H:i'))->toBe('2026-03-06 22:00')
        ->and($interval->end()->format('Y-m-d H:i'))->toBe('2026-03-07 02:00');
});

test('it anchors schedule ranges to the schedule day', function (): void {
    $interval = DateTimeInterval::fromScheduleDate(
        CarbonImmutable::parse('2026-03-06 00:00:00'),
        LocalTimeRange::fromString('09:00-17:00'),
    );

    expect($interval->start()->format('Y-m-d H:i'))->toBe('2026-03-06 09:00')
        ->and($interval->end()->format('Y-m-d H:i'))->toBe('2026-03-06 17:00')
        ->and($interval->contains(
            CarbonImmutable::parse('2026-03-06 11:00:00'),
        ))->toBeTrue()
        ->and($interval->contains(
            CarbonImmutable::parse('2026-03-06 17:00:00'),
        ))->toBeFalse();
});

test('it can anchor wrapping ranges after midnight', function (): void {
    $interval = DateTimeInterval::fromLocalTimeRange(
        CarbonImmutable::parse('2026-03-07 01:30:00'),
        LocalTimeRange::fromString('22:00-02:00'),
    );

    expect($interval->start()->format('Y-m-d H:i'))->toBe('2026-03-06 22:00')
        ->and($interval->end()->format('Y-m-d H:i'))->toBe('2026-03-07 02:00');
});
