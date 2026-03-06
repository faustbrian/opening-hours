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

test('it finds the next open transition', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'tuesday' => ['10:00-12:00'],
    ]);

    $nextOpen = $openingHours->nextOpen(
        CarbonImmutable::parse('2026-03-09 18:00:00'),
    );

    expect($nextOpen?->format('Y-m-d H:i'))->toBe('2026-03-10 10:00');
});

test('it finds the current close when already open', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
    ]);

    $nextClose = $openingHours->nextClose(
        CarbonImmutable::parse('2026-03-09 11:00:00'),
    );

    expect($nextClose?->format('Y-m-d H:i'))->toBe('2026-03-09 17:00');
});

test('it finds the previous open transition', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'tuesday' => ['10:00-12:00'],
    ]);

    $previousOpen = $openingHours->previousOpen(
        CarbonImmutable::parse('2026-03-10 09:30:00'),
    );

    expect($previousOpen?->format('Y-m-d H:i'))->toBe('2026-03-09 09:00');
});

test('it finds the previous close transition', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'tuesday' => ['10:00-12:00'],
    ]);

    $previousClose = $openingHours->previousClose(
        CarbonImmutable::parse('2026-03-10 09:30:00'),
    );

    expect($previousClose?->format('Y-m-d H:i'))->toBe('2026-03-09 17:00');
});

test('it applies default query options to instance queries', function (): void {
    $openingHours = OpeningHours::fromArray([
        'saturday' => ['10:00-12:00'],
    ], new QueryOptions(
        timezone: new DateTimeZone('Europe/Helsinki'),
    ));

    expect($openingHours->isOpenAt(
        new DateTimeImmutable('2026-03-07 08:30:00', new DateTimeZone('UTC')),
    ))->toBeTrue();
});

test('it reports when a schedule is always closed', function (): void {
    $openingHours = OpeningHours::fromArray([]);

    expect($openingHours->isAlwaysClosed())->toBeTrue();
    expect($openingHours->isAlwaysOpen())->toBeFalse();
});

test('it reports when a schedule is always open', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['00:00-24:00'],
        'tuesday' => ['00:00-24:00'],
        'wednesday' => ['00:00-24:00'],
        'thursday' => ['00:00-24:00'],
        'friday' => ['00:00-24:00'],
        'saturday' => ['00:00-24:00'],
        'sunday' => ['00:00-24:00'],
    ]);

    expect($openingHours->isAlwaysOpen())->toBeTrue();
    expect($openingHours->isAlwaysClosed())->toBeFalse();
});

test('it does not report always open or closed when rules exist', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['00:00-24:00'],
        'tuesday' => ['00:00-24:00'],
        'wednesday' => ['00:00-24:00'],
        'thursday' => ['00:00-24:00'],
        'friday' => ['00:00-24:00'],
        'saturday' => ['00:00-24:00'],
        'sunday' => ['00:00-24:00'],
        'exceptions' => [
            '2026-03-09' => [],
        ],
    ]);

    expect($openingHours->isAlwaysOpen())->toBeFalse();
    expect($openingHours->isAlwaysClosed())->toBeFalse();
});

test('it returns the latest previous close boundary before the cursor', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => [
            '09:00-10:00',
            '12:00-13:00',
        ],
    ]);

    $previousClose = $openingHours->previousClose(
        CarbonImmutable::parse('2026-03-09 14:00:00'),
    );

    expect($previousClose?->format('Y-m-d H:i'))->toBe('2026-03-09 13:00');
});
