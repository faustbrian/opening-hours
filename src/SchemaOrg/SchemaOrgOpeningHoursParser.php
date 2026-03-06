<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\SchemaOrg;

use Cline\OpeningHours\Exceptions\InvalidOpeningHoursSpecification;
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
use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Rule\DateRangeOverrideRule;
use Cline\OpeningHours\Rule\MonthDayOverrideRule;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\Schedule;
use Cline\OpeningHours\Schedule\WeeklySchedule;
use Cline\OpeningHours\Value\LocalTimeRange;
use JsonException;

use const JSON_THROW_ON_ERROR;

use function array_merge;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_replace;

/**
 * @author Brian Faust <brian@cline.sh>
 * Parses Schema.org `OpeningHoursSpecification` data into package schedules.
 */
final class SchemaOrgOpeningHoursParser
{
    /**
     * @param array<array-key, mixed>|string $structuredData JSON string or
     *                                                       array of Schema.org `OpeningHoursSpecification` items.
     *
     * @throws InvalidOpeningHoursSpecification
     */
    public static function parse(array|string $structuredData): Schedule
    {
        if (is_string($structuredData)) {
            try {
                $structuredData = json_decode($structuredData, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw InvalidOpeningHoursSpecificationJson::invalidJson($exception);
            }
        }

        if (!is_array($structuredData)) {
            throw StructuredDataMustDecodeToArray::structuredDataMustDecodeToArray();
        }

        $weekly = [];
        $rules = [];

        foreach ($structuredData as $item) {
            if (!is_array($item)) {
                throw OpeningHoursSpecificationItemsMustBeArrays::openingHoursSpecificationItemsMustBeArrays();
            }

            [
                'dayOfWeek' => $dayOfWeek,
                'validFrom' => $validFrom,
                'validThrough' => $validThrough,
                'opens' => $opens,
                'closes' => $closes,
            ] = array_merge([
                'dayOfWeek' => null,
                'validFrom' => null,
                'validThrough' => null,
                'opens' => null,
                'closes' => null,
            ], $item);

            $schedule = self::toDaySchedule($opens, $closes);

            if ($dayOfWeek !== null) {
                $days = is_array($dayOfWeek) ? $dayOfWeek : [$dayOfWeek];

                foreach ($days as $day) {
                    $dayName = self::schemaOrgDayToString($day);
                    $existing = $weekly[$dayName] ?? DaySchedule::closed();
                    $weekly[$dayName] = self::mergeDaySchedules($existing, $schedule);
                }

                continue;
            }

            if (!is_string($validFrom) || !is_string($validThrough)) {
                throw StructuredDataExceptionsRequireValidFromAndThrough::structuredDataExceptionsRequireValidFromAndThrough();
            }

            if (!preg_match('/^(?:\d{4}-)?\d{2}-\d{2}$/', $validFrom)) {
                throw InvalidStructuredDataValidFromDate::invalidStructuredDataValidFromDate();
            }

            if (!preg_match('/^(?:\d{4}-)?\d{2}-\d{2}$/', $validThrough)) {
                throw InvalidStructuredDataValidThroughDate::invalidStructuredDataValidThroughDate();
            }

            $rules[] = $validFrom === $validThrough
                ? (preg_match('/^\d{2}-\d{2}$/', $validFrom)
                    ? new MonthDayOverrideRule($validFrom, $schedule)
                    : new DateOverrideRule($validFrom, $schedule))
                : new DateRangeOverrideRule($validFrom, $validThrough, $schedule);
        }

        return new Schedule(
            WeeklySchedule::fromDaySchedules($weekly),
            $rules,
        );
    }

    private static function toDaySchedule(mixed $opens, mixed $closes): DaySchedule
    {
        if ($opens === null) {
            if ($closes !== null) {
                throw StructuredDataOpensAndClosesMustBothBeNullOrStrings::structuredDataOpensAndClosesMustBothBeNullOrStrings();
            }

            return DaySchedule::closed();
        }

        if ($opens === '00:00' && $closes === '00:00') {
            return DaySchedule::closed();
        }

        if (!is_string($opens) || !is_string($closes)) {
            throw StructuredDataEntriesRequireStringOpensAndCloses::structuredDataEntriesRequireStringOpensAndCloses();
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $opens)) {
            throw InvalidStructuredDataOpensHour::invalidStructuredDataOpensHour();
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $closes)) {
            throw InvalidStructuredDataClosesHour::invalidStructuredDataClosesHour();
        }

        $opens = preg_replace('/^(\d{2}:\d{2})(:\d{2})?$/', '$1', $opens);
        $closes = preg_replace('/^(\d{2}:\d{2})(:\d{2})?$/', '$1', $closes);

        return DaySchedule::fromRanges(
            LocalTimeRange::fromString($opens.'-'.($closes === '23:59' ? '24:00' : $closes)),
        );
    }

    private static function mergeDaySchedules(DaySchedule $left, DaySchedule $right): DaySchedule
    {
        return DaySchedule::fromRanges(
            ...[...$left->ranges(), ...$right->ranges()],
        );
    }

    private static function schemaOrgDayToString(mixed $schemaOrgDaySpec): string
    {
        if (!is_string($schemaOrgDaySpec)) {
            throw InvalidOpeningHoursSpecificationDayOfWeek::invalidOpeningHoursSpecificationDayOfWeek();
        }

        return match ($schemaOrgDaySpec) {
            'Monday', 'https://schema.org/Monday' => 'monday',
            'Tuesday', 'https://schema.org/Tuesday' => 'tuesday',
            'Wednesday', 'https://schema.org/Wednesday' => 'wednesday',
            'Thursday', 'https://schema.org/Thursday' => 'thursday',
            'Friday', 'https://schema.org/Friday' => 'friday',
            'Saturday', 'https://schema.org/Saturday' => 'saturday',
            'Sunday', 'https://schema.org/Sunday' => 'sunday',
            'PublicHolidays', 'https://schema.org/PublicHolidays' => throw PublicHolidaysNotSupported::publicHolidaysNotSupported(),
            default => throw InvalidSchemaOrgDaySpecification::invalidSchemaOrgDaySpecification(),
        };
    }
}
