<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Config\QueryOptions;
use Cline\OpeningHours\Exceptions\DaySchedulesMustBeDefinedAsArrays;
use Cline\OpeningHours\Exceptions\ExceptionKeysMustBeStrings;
use Cline\OpeningHours\Exceptions\ExceptionsMustBeDefinedAsArray;
use Cline\OpeningHours\Exceptions\TimeRangesMustBeStringsOrContainHoursKey;
use Cline\OpeningHours\Exceptions\UnsupportedExceptionKey;
use Cline\OpeningHours\Exceptions\UnsupportedScheduleKey;
use Cline\OpeningHours\OpeningHours;
use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Rule\DateRangeOverrideRule;
use Cline\OpeningHours\Rule\MonthDayOverrideRule;
use Cline\OpeningHours\Value\Day;
use Cline\OpeningHours\Value\LocalTime;

test('it exposes the v2 schedule accessors', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => [['hours' => '09:00-17:00']],
        'exceptions' => [
            '2026-12-24' => [],
            '2026-12-25 to 2026-12-26' => ['10:00-12:00'],
            '12-31' => ['11:00-13:00'],
        ],
    ]);

    expect((string) $openingHours->weeklySchedule()->forDay(Day::MONDAY))
        ->toBe('09:00-17:00');
    expect((string) $openingHours->schedule()->weeklySchedule()->forDay(Day::MONDAY))
        ->toBe('09:00-17:00');
    expect($openingHours->rules())->toHaveCount(3);
    expect($openingHours->rules()[0])->toBeInstanceOf(DateOverrideRule::class);
    expect($openingHours->rules()[1])->toBeInstanceOf(DateRangeOverrideRule::class);
    expect($openingHours->rules()[2])->toBeInstanceOf(MonthDayOverrideRule::class);
    expect($openingHours->forDate(
        CarbonImmutable::parse('2026-12-25 11:00:00'),
    )->contains(LocalTime::fromString('11:30')))->toBeTrue();
});

test('it rejects unsupported top level keys', function (): void {
    $this->expectException(UnsupportedScheduleKey::class);
    $this->expectExceptionMessage('Unsupported schedule key [foo]');

    OpeningHours::fromArray([
        'foo' => ['09:00-17:00'],
    ]);
});

test('it rejects non string exception keys', function (): void {
    $this->expectException(ExceptionKeysMustBeStrings::class);
    $this->expectExceptionMessage('Exception keys must be strings');

    OpeningHours::fromArray([
        'exceptions' => [
            1 => ['09:00-17:00'],
        ],
    ]);
});

test('it rejects non array exceptions definitions', function (): void {
    $this->expectException(ExceptionsMustBeDefinedAsArray::class);
    $this->expectExceptionMessage('Exceptions must be defined as an array.');

    OpeningHours::fromArray([
        'exceptions' => '2026-12-24',
    ]);
});

test('it rejects invalid exception formats', function (): void {
    $this->expectException(UnsupportedExceptionKey::class);
    $this->expectExceptionMessage('Unsupported exception key [2026/12/24]');

    OpeningHours::fromArray([
        'exceptions' => [
            '2026/12/24' => ['09:00-17:00'],
        ],
    ]);
});

test('it rejects invalid day schedule definitions', function (): void {
    $this->expectException(DaySchedulesMustBeDefinedAsArrays::class);
    $this->expectExceptionMessage('Day schedules must be defined as arrays');

    OpeningHours::fromArray([
        'monday' => '09:00-17:00',
    ]);
});

test('it rejects invalid time range definitions', function (): void {
    $this->expectException(TimeRangesMustBeStringsOrContainHoursKey::class);
    $this->expectExceptionMessage(
        'Time ranges must be strings or arrays with an hours key.',
    );

    OpeningHours::fromArray([
        'monday' => [['foo' => '09:00-17:00']],
    ]);
});

test('it can report closed queries and apply output timezones', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
    ], new QueryOptions(
        timezone: 'Europe/Helsinki',
        outputTimezone: new DateTimeZone('UTC'),
    ));

    expect($openingHours->isClosedAt(
        new DateTimeImmutable('2026-03-09 18:00:00', new DateTimeZone('Europe/Helsinki')),
    ))->toBeTrue();
    expect($openingHours->nextClose(
        new DateTimeImmutable('2026-03-09 11:00:00', new DateTimeZone('Europe/Helsinki')),
    )?->format('Y-m-d H:i'))->toBe('2026-03-09 15:00');
});

test('it returns null for missing close transitions', function (): void {
    $openingHours = OpeningHours::fromArray([]);

    expect($openingHours->nextClose(
        CarbonImmutable::parse('2026-03-09 11:00:00'),
    ))->not->toBeInstanceOf(DateTimeImmutable::class);
    expect($openingHours->previousClose(
        CarbonImmutable::parse('2026-03-09 11:00:00'),
    ))->not->toBeInstanceOf(DateTimeImmutable::class);
});

test('it uses overflow ranges from the previous day when querying boundaries', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['22:00-02:00'],
    ]);

    expect($openingHours->previousOpen(
        CarbonImmutable::parse('2026-03-10 01:00:00'),
    )?->format('Y-m-d H:i'))->toBe('2026-03-09 22:00');
    expect($openingHours->previousClose(
        CarbonImmutable::parse('2026-03-10 01:00:00'),
    )?->format('Y-m-d H:i'))->toBe('2026-03-09 22:00');
});

test('it allows non string definition keys and day level hours wrappers', function (): void {
    $openingHours = OpeningHours::fromArray([
        0 => ['ignored'],
        'monday' => ['hours' => ['09:00-17:00']],
        'tuesday' => null,
        'wednesday' => ['hours' => []],
    ]);

    expect((string) $openingHours->weeklySchedule()->forDay(Day::MONDAY))
        ->toBe('09:00-17:00');
    expect($openingHours->weeklySchedule()->forDay(Day::TUESDAY)->isClosed())
        ->toBeTrue();
    expect($openingHours->weeklySchedule()->forDay(Day::WEDNESDAY)->isClosed())
        ->toBeTrue();
});

test('it ignores previous day overflow ranges that end at midnight', function (): void {
    $openingHours = OpeningHours::fromArray([
        'monday' => ['09:00-17:00'],
        'sunday' => ['22:00-00:00'],
    ]);

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-09 00:00:00'),
    ))->toBeFalse();
    expect($openingHours->nextOpen(
        CarbonImmutable::parse('2026-03-09 00:00:00'),
    )?->format('Y-m-d H:i'))->toBe('2026-03-09 09:00');
});

test('it can merge overlapping ranges in a legacy definition', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'monday' => ['09:00-12:00', '11:00-13:00', '15:00-17:00'],
        'exceptions' => [
            '2026-12-24' => ['08:00-10:00', '09:30-11:00'],
        ],
    ]))->toBe([
        'monday' => ['09:00-13:00', '15:00-17:00'],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
        'exceptions' => [
            '2026-12-24' => ['08:00-11:00'],
        ],
    ]);
});

test('it can normalize nested legacy day ranges before building details', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'monday' => [['08:00-20:00']],
    ]))->toBe([
        'monday' => ['08:00-20:00'],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
        'exceptions' => [],
    ]);
});

test('it can build from merged overlapping legacy ranges', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'saturday' => ['09:00-20:00', '10:00-23:59'],
    ]))->toBe([
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => ['09:00-23:59'],
        'sunday' => [],
        'exceptions' => [],
    ]);

    $openingHours = OpeningHours::fromArrayAndMergeOverlappingRanges([
        'saturday' => ['09:00-20:00', '10:00-23:59'],
    ]);

    expect($openingHours->isOpenAt(
        CarbonImmutable::parse('2026-03-07 22:00:00'),
    ))->toBeTrue();
});

test('it preserves invalid exception payloads for strict validation later', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'exceptions' => '2026-12-24',
    ]))->toBe([
        'monday' => [],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
        'exceptions' => '2026-12-24',
    ]);
});

test('it rejects invalid nested merged day definitions', function (): void {
    $this->expectException(DaySchedulesMustBeDefinedAsArrays::class);
    $this->expectExceptionMessage('Day schedules must be defined as arrays');

    OpeningHours::mergeOverlappingRanges([
        'monday' => [false],
    ]);
});

test('it merges overlapping ranges when the wider range appears later', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'monday' => [['hours' => ['11:00-13:00', '09:00-12:00']]],
    ]))->toBe([
        'monday' => ['09:00-13:00'],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
        'exceptions' => [],
    ]);
});

test('it keeps the earlier closing time source when it already covers the overlap', function (): void {
    expect(OpeningHours::mergeOverlappingRanges([
        'monday' => ['09:00-13:00', '11:00-12:00'],
    ]))->toBe([
        'monday' => ['09:00-13:00'],
        'tuesday' => [],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
        'exceptions' => [],
    ]);
});
