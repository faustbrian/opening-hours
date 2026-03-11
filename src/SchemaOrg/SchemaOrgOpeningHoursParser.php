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
 * Translates Schema.org `OpeningHoursSpecification` payloads into package
 * schedules.
 *
 * This adapter is responsible for accepting either decoded arrays or raw JSON,
 * validating the subset of Schema.org fields supported by the package, and
 * converting those items into a {@see WeeklySchedule} plus explicit override
 * rules. Weekly `dayOfWeek` entries are merged by weekday, while items with
 * validity dates become exact-date, month-day, or date-range rules.
 *
 * Validation here is intentionally strict. Unsupported day tokens, malformed
 * time strings, partially-specified closures, and missing validity windows are
 * rejected before schedule construction so callers do not accidentally persist
 * ambiguous third-party data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SchemaOrgOpeningHoursParser
{
    /**
     * Parse Schema.org structured data into the package's immutable schedule
     * model.
     *
     * JSON input is decoded first, then each item is classified as either a
     * weekly schedule entry or a date-bound override. Weekly entries sharing the
     * same day are merged into one {@see DaySchedule}; overlapping ranges still
     * fail later through the normal schedule validation path.
     *
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

    /**
     * Convert `opens` and `closes` fields into a normalized {@see DaySchedule}.
     *
     * `null`/`null` is treated as an explicit closure. The special
     * `00:00`-`00:00` case is also normalized to closed because that is how
     * Schema.org commonly represents a day with no opening hours. A closing time
     * of `23:59` is promoted to `24:00` so the package can represent full
     * end-of-day coverage without losing its exclusive-end invariant.
     */
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

    /**
     * Merge two day schedules while preserving the package's overlap checks.
     *
     * The combined ranges are revalidated through {@see DaySchedule::fromRanges}
     * instead of being concatenated blindly.
     */
    private static function mergeDaySchedules(DaySchedule $left, DaySchedule $right): DaySchedule
    {
        return DaySchedule::fromRanges(
            ...[...$left->ranges(), ...$right->ranges()],
        );
    }

    /**
     * Map one Schema.org day token to the package's lowercase weekday keys.
     *
     * Both short tokens such as `Monday` and full schema URLs are accepted.
     * `PublicHolidays` is rejected explicitly because the package models holiday
     * behavior through concrete date overrides rather than special weekday
     * groups.
     */
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
