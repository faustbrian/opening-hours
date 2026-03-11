<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Rule;

use Cline\OpeningHours\Schedule\DaySchedule;
use DateTimeInterface;

/**
 * Applies a replacement day schedule to the same month and day every year.
 *
 * This rule exists for recurring events such as annual holidays where the year is not
 * part of the scheduling key. Once the resolver has normalized the query date, the rule
 * compares only the `m-d` portion and replaces the weekly baseline when it matches.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class MonthDayOverrideRule implements ScheduleRule
{
    /**
     * @param string      $monthDay Month and day in `m-d` format.
     * @param DaySchedule $schedule Replacement schedule to apply every year on that month-day.
     */
    public function __construct(
        private string $monthDay,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     *
     * Matching is based on the normalized month-day key only, which intentionally ignores
     * the year so the same exception can recur indefinitely.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        return $date->format('m-d') === $this->monthDay;
    }

    /**
     * Return the schedule that replaces the baseline schedule for matching dates.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Return the recurring month-day key in `m-d` format.
     */
    public function monthDay(): string
    {
        return $this->monthDay;
    }
}
