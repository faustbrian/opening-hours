<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Exceptions\InvalidDayName;
use Cline\OpeningHours\Value\Day;

test('it can resolve days from names and dates', function (): void {
    expect(Day::fromName('Monday'))->toBe(Day::MONDAY)
        ->and(Day::onDateTime(
            CarbonImmutable::parse('2026-03-06 10:00:00'),
        ))->toBe(Day::FRIDAY)
        ->and(Day::MONDAY->toISO())->toBe(1)
        ->and(Day::SUNDAY->toISO())->toBe(7);
});

test('it rejects invalid day names', function (): void {
    expect(fn (): Day => Day::fromName('Funday'))
        ->toThrow(InvalidDayName::class, 'Funday');
});
