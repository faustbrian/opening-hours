<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\OpeningHours;

test('it can build the v2 facade from structured data', function (): void {
    $openingHours = OpeningHours::createFromStructuredData([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Monday',
            'opens' => '09:00',
            'closes' => '17:00',
        ],
    ]);

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-09 10:00:00'),
    ))->toBeTrue();
});

test('it can export the v2 facade as structured data', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'exceptions' => [
            '2026-12-24' => [],
        ],
    ]);

    $structuredData = $openingHours->asStructuredData();

    expect($structuredData[0]['dayOfWeek'])->toBe('Monday');
    expect($structuredData[0]['opens'])->toBe('09:00');
    expect($structuredData[1]['validFrom'])->toBe('2026-12-24');
    expect($structuredData[1]['closes'])->toBe('00:00');
});
