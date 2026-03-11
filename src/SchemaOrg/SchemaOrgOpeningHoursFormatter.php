<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\SchemaOrg;

use Cline\OpeningHours\Rule\DateOverrideRule;
use Cline\OpeningHours\Rule\DateRangeOverrideRule;
use Cline\OpeningHours\Rule\MonthDayOverrideRule;
use Cline\OpeningHours\Schedule\DaySchedule;
use Cline\OpeningHours\Schedule\Schedule;
use Cline\OpeningHours\Value\Day;

use function ucfirst;

/**
 * Serializes normalized schedules as Schema.org `OpeningHoursSpecification`
 * items.
 *
 * The formatter emits one item per local range. Weekly schedule entries use
 * `dayOfWeek`, while override rules become validity-bound items with
 * `validFrom` and `validThrough`. Closed exceptions are represented using
 * `00:00`/`00:00` because that is the same convention the parser recognizes
 * when converting structured data back into package schedules.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SchemaOrgOpeningHoursFormatter
{
    /**
     * Format both the weekly fallback schedule and explicit override rules as
     * Schema.org items.
     *
     * Rules are emitted after the weekly schedule because they represent
     * exceptional validity windows rather than default weekday behavior.
     *
     * @return list<array<string, string>>
     */
    public static function format(Schedule $schedule): array
    {
        $items = [];

        foreach (Day::cases() as $day) {
            foreach ($schedule->weeklySchedule()->forDay($day)->ranges() as $range) {
                $items[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ucfirst($day->value),
                    'opens' => $range->start()->format(),
                    'closes' => $range->end()->format(),
                ];
            }
        }

        foreach ($schedule->rules() as $rule) {
            if ($rule instanceof DateOverrideRule) {
                $items = [...$items, ...self::formatDateRule($rule)];

                continue;
            }

            if ($rule instanceof DateRangeOverrideRule) {
                $items = [...$items, ...self::formatDateRangeRule($rule)];

                continue;
            }

            if (!$rule instanceof MonthDayOverrideRule) {
                continue;
            }

            $items = [...$items, ...self::formatMonthDayRule($rule)];
        }

        return $items;
    }

    /**
     * Format a single-date override as Schema.org validity-bound items.
     *
     * @return list<array<string, string>>
     */
    private static function formatDateRule(DateOverrideRule $rule): array
    {
        return self::formatScheduleWithValidity(
            $rule->schedule(),
            $rule->date(),
            $rule->date(),
        );
    }

    /**
     * Format an inclusive date-range override as Schema.org validity-bound
     * items.
     *
     * @return list<array<string, string>>
     */
    private static function formatDateRangeRule(DateRangeOverrideRule $rule): array
    {
        return self::formatScheduleWithValidity(
            $rule->schedule(),
            $rule->from(),
            $rule->through(),
        );
    }

    /**
     * Format a recurring month-day override using partial-date validity keys.
     *
     * @return list<array<string, string>>
     */
    private static function formatMonthDayRule(MonthDayOverrideRule $rule): array
    {
        return self::formatScheduleWithValidity(
            $rule->schedule(),
            $rule->monthDay(),
            $rule->monthDay(),
        );
    }

    /**
     * Format a day schedule inside a fixed validity window.
     *
     * Closed schedules are emitted as a single `00:00`/`00:00` item so the
     * parser can round-trip them back to {@see DaySchedule::closed()}.
     *
     * @return list<array<string, string>>
     */
    private static function formatScheduleWithValidity(
        DaySchedule $schedule,
        string $from,
        string $through,
    ): array {
        if ($schedule->isClosed()) {
            return [[
                '@type' => 'OpeningHoursSpecification',
                'opens' => '00:00',
                'closes' => '00:00',
                'validFrom' => $from,
                'validThrough' => $through,
            ]];
        }

        $items = [];

        foreach ($schedule->ranges() as $range) {
            $items[] = [
                '@type' => 'OpeningHoursSpecification',
                'opens' => $range->start()->format(),
                'closes' => $range->end()->format(),
                'validFrom' => $from,
                'validThrough' => $through,
            ];
        }

        return $items;
    }
}
