<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Exceptions\InvalidTimeString;
use Cline\OpeningHours\Value\LocalTime;

test('it creates a local time from a string', function (): void {
    $time = LocalTime::fromString('09:30');

    expect($time->hours())->toBe(9)
        ->and($time->minutes())->toBe(30)
        ->and($time->minutesSinceMidnight())->toBe(570)
        ->and($time->format())->toBe('09:30');
});

test('it supports the end of day marker', function (): void {
    $time = LocalTime::fromString('24:00');

    expect($time->minutesSinceMidnight())->toBe(1_440)
        ->and($time->isEndOfDay())->toBeTrue()
        ->and($time->format())->toBe('24:00');
});

test('it can be created from a date time', function (): void {
    $time = LocalTime::fromDateTime(
        CarbonImmutable::parse('2026-03-06 17:45:00'),
    );

    expect($time->format())->toBe('17:45');
});

test('it compares local times', function (): void {
    $earlier = LocalTime::fromString('09:30');
    $later = LocalTime::fromString('17:45');

    expect($earlier->isBefore($later))->toBeTrue()
        ->and($later->isAfter($earlier))->toBeTrue()
        ->and((string) $earlier)->toBe('09:30');
});

test('it rejects invalid time strings', function (): void {
    expect(fn (): LocalTime => LocalTime::fromString('25:00'))
        ->toThrow(InvalidTimeString::class, '25:00');
});
