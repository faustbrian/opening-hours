<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\OpeningHours\Exceptions\InvalidOpeningHoursSpecificationDayOfWeek;
use Cline\OpeningHours\Exceptions\InvalidOpeningHoursSpecificationJson;
use Cline\OpeningHours\Exceptions\InvalidSchemaOrgDaySpecification;
use Cline\OpeningHours\Exceptions\InvalidStructuredDataClosesHour;
use Cline\OpeningHours\Exceptions\InvalidStructuredDataOpensHour;
use Cline\OpeningHours\Exceptions\InvalidStructuredDataValidFromDate;
use Cline\OpeningHours\Exceptions\InvalidStructuredDataValidThroughDate;
use Cline\OpeningHours\Exceptions\OpeningHoursSpecificationItemsMustBeArrays;
use Cline\OpeningHours\Exceptions\PublicHolidaysNotSupported;
use Cline\OpeningHours\Exceptions\StructuredDataEntriesRequireStringOpensAndCloses;
use Cline\OpeningHours\Exceptions\StructuredDataExceptionsRequireValidFromAndThrough;
use Cline\OpeningHours\Exceptions\StructuredDataMustDecodeToArray;
use Cline\OpeningHours\Exceptions\StructuredDataOpensAndClosesMustBothBeNullOrStrings;
use Cline\OpeningHours\SchemaOrg\SchemaOrgOpeningHoursParser;
use Cline\OpeningHours\Value\Day;

test('it rejects invalid json', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse('{'))
        ->toThrow(InvalidOpeningHoursSpecificationJson::class);
});

test('it rejects invalid day names', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'dayOfWeek' => 'Funday',
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(InvalidSchemaOrgDaySpecification::class);
});

test('it rejects non string day of week values', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'dayOfWeek' => [1],
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(InvalidOpeningHoursSpecificationDayOfWeek::class);
});

test('it rejects public holidays shortcuts', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'dayOfWeek' => 'PublicHolidays',
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(PublicHolidaysNotSupported::class);
});

test('it requires valid from and valid through for exceptions', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(StructuredDataExceptionsRequireValidFromAndThrough::class);
});

test('it rejects invalid exception dates', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'validFrom' => 'not-a-date',
        'validThrough' => '2026-12-24',
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(InvalidStructuredDataValidFromDate::class);
});

test('it rejects invalid valid through dates', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'validFrom' => '2026-12-24',
        'validThrough' => 'not-a-date',
        'opens' => '09:00',
        'closes' => '17:00',
    ]]))->toThrow(InvalidStructuredDataValidThroughDate::class);
});

test('it rejects non array items', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse(['nope']))
        ->toThrow(OpeningHoursSpecificationItemsMustBeArrays::class);
});

test('it rejects non array json payloads', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse('1'))
        ->toThrow(StructuredDataMustDecodeToArray::class);
});

test('it rejects partial open close pairs', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'closes' => '17:00',
    ]]))->toThrow(StructuredDataOpensAndClosesMustBothBeNullOrStrings::class);
});

test('it rejects invalid opening hours values', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'opens' => '09',
        'closes' => '17:00',
    ]]))->toThrow(InvalidStructuredDataOpensHour::class);
});

test('it rejects invalid closing hours values', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'opens' => '09:00',
        'closes' => '1700',
    ]]))->toThrow(InvalidStructuredDataClosesHour::class);
});

test('it requires string open and close values when present', function (): void {
    expect(fn (): mixed => SchemaOrgOpeningHoursParser::parse([[
        'opens' => 900,
        'closes' => '17:00',
    ]]))->toThrow(StructuredDataEntriesRequireStringOpensAndCloses::class);
});

test('it supports day arrays and schema org urls', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([[
        'dayOfWeek' => [
            'https://schema.org/Monday',
            'Tuesday',
        ],
        'opens' => '09:00:00',
        'closes' => '23:59',
    ]]);

    expect((string) $schedule->weeklySchedule()->forDay(Day::MONDAY))->toBe('09:00-24:00')
        ->and((string) $schedule->weeklySchedule()->forDay(Day::TUESDAY))->toBe('09:00-24:00');
});

test('it maps the remaining schema org weekday names', function (): void {
    $schedule = SchemaOrgOpeningHoursParser::parse([[
        'dayOfWeek' => [
            'Wednesday',
            'https://schema.org/Thursday',
            'Friday',
            'https://schema.org/Saturday',
            'Sunday',
        ],
        'opens' => '10:00',
        'closes' => '12:00',
    ]]);

    expect((string) $schedule->weeklySchedule()->forDay(Day::WEDNESDAY))->toBe('10:00-12:00')
        ->and((string) $schedule->weeklySchedule()->forDay(Day::THURSDAY))->toBe('10:00-12:00')
        ->and((string) $schedule->weeklySchedule()->forDay(Day::FRIDAY))->toBe('10:00-12:00')
        ->and((string) $schedule->weeklySchedule()->forDay(Day::SATURDAY))->toBe('10:00-12:00')
        ->and((string) $schedule->weeklySchedule()->forDay(Day::SUNDAY))->toBe('10:00-12:00');
});
