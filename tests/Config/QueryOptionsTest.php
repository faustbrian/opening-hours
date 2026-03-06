<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\OpeningHours\Config\QueryOptions;
use DateTimeZone;
use Exception;

use function expect;
use function test;

test('it normalizes string timezones and max days', function (): void {
    $options = new QueryOptions(
        'Europe/Helsinki',
        'UTC',
        3,
    );

    expect($options->timezone?->getName())->toBe('Europe/Helsinki')
        ->and($options->outputTimezone?->getName())->toBe('UTC')
        ->and($options->maxDaysToSearch)->toBe(3);
});

test('it keeps timezone instances and allows nulls', function (): void {
    $timezone = new DateTimeZone('Europe/Helsinki');
    $outputTimezone = new DateTimeZone('UTC');
    $options = new QueryOptions($timezone, $outputTimezone);
    $empty = new QueryOptions();

    expect($options->timezone)->toBe($timezone)
        ->and($options->outputTimezone)->toBe($outputTimezone)
        ->and($empty->timezone)->not->toBeInstanceOf(DateTimeZone::class)
        ->and($empty->outputTimezone)->not->toBeInstanceOf(DateTimeZone::class)
        ->and($empty->maxDaysToSearch)->toBe(8);
});

test('it merges non null override values', function (): void {
    $base = new QueryOptions('Europe/Helsinki', 'UTC', 8);
    $merged = $base->withOverrides(
        new QueryOptions(
            null,
            'America/New_York',
            2,
        ),
    );

    expect($merged->timezone?->getName())->toBe('Europe/Helsinki')
        ->and($merged->outputTimezone?->getName())->toBe('America/New_York')
        ->and($merged->maxDaysToSearch)->toBe(2);
});

test('it returns the same instance when no overrides are given', function (): void {
    $options = new QueryOptions('Europe/Helsinki');

    expect($options->withOverrides())->toBe($options);
});

test('it rejects invalid timezone identifiers', function (): void {
    expect(fn (): QueryOptions => new QueryOptions('Nope/Invalid'))
        ->toThrow(Exception::class);
});
