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
 * Contract for rules that can replace the weekly schedule on matching dates.
 *
 * Implementations encapsulate one date-matching strategy such as an exact date, a
 * recurring month-day, or an inclusive date range. The resolver treats them uniformly:
 * if a rule matches the queried date, the rule's schedule replaces the baseline weekly
 * schedule for that date.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ScheduleRule
{
    /**
     * Determine whether this rule applies to the provided date.
     *
     * Implementations are expected to evaluate against the already-normalized query date,
     * not to perform timezone conversion themselves.
     */
    public function appliesTo(DateTimeInterface $date): bool;

    /**
     * Return the day schedule that replaces the base weekly schedule for matching dates.
     */
    public function schedule(): DaySchedule;
}
