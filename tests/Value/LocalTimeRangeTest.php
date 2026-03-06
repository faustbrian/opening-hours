<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\OpeningHours\Exceptions\InvalidTimeRangeString;
use Cline\OpeningHours\Value\LocalTime;
use Cline\OpeningHours\Value\LocalTimeRange;

test('it treats ranges as half open intervals', function (): void {
    $range = LocalTimeRange::fromString('09:00-17:00');

    expect($range->contains(LocalTime::fromString('09:00')))->toBeTrue()
        ->and($range->contains(LocalTime::fromString('16:59')))->toBeTrue()
        ->and($range->contains(LocalTime::fromString('17:00')))->toBeFalse();
});

test('it detects overlap symmetrically', function (): void {
    $containing = LocalTimeRange::fromString('09:00-18:00');
    $contained = LocalTimeRange::fromString('10:00-12:00');

    expect($containing->overlaps($contained))->toBeTrue()
        ->and($contained->overlaps($containing))->toBeTrue();
});

test('it does not treat touching ranges as overlapping', function (): void {
    $first = LocalTimeRange::fromString('09:00-12:00');
    $second = LocalTimeRange::fromString('12:00-17:00');

    expect($first->overlaps($second))->toBeFalse()
        ->and($second->overlaps($first))->toBeFalse();
});

test('it can represent ranges that wrap into the next day', function (): void {
    $range = LocalTimeRange::fromString('22:00-02:00');

    expect($range->wrapsToNextDay())->toBeTrue()
        ->and($range->contains(LocalTime::fromString('23:30')))->toBeTrue()
        ->and($range->contains(LocalTime::fromString('01:30')))->toBeTrue()
        ->and($range->contains(LocalTime::fromString('12:00')))->toBeFalse();
});

test('it can be formatted as a string', function (): void {
    $range = LocalTimeRange::fromString('09:00-17:00');

    expect($range->format())->toBe('09:00-17:00')
        ->and((string) $range)->toBe('09:00-17:00');
});

test('it rejects invalid time range strings', function (): void {
    expect(fn (): LocalTimeRange => LocalTimeRange::fromString('09:00'))
        ->toThrow(InvalidTimeRangeString::class, '09:00');
});

test('it distinguishes non overlapping and wrapping overlap cases', function (): void {
    $morning = LocalTimeRange::fromString('09:00-11:00');
    $afternoon = LocalTimeRange::fromString('12:00-14:00');
    $overnight = LocalTimeRange::fromString('22:00-02:00');
    $afterMidnight = LocalTimeRange::fromString('01:00-03:00');

    expect($morning->wrapsToNextDay())->toBeFalse()
        ->and($morning->overlaps($afternoon))->toBeFalse()
        ->and($overnight->overlaps($afterMidnight))->toBeTrue()
        ->and($afterMidnight->overlaps($overnight))->toBeTrue();
});
