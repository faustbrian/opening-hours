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
 * Applies a replacement day schedule to one exact calendar date.
 *
 * This rule is used for one-off closures or special hours such as holidays, events, or
 * ad-hoc exceptions. When the queried date exactly matches the stored `Y-m-d` key, the
 * schedule resolver uses this rule's schedule instead of the weekly baseline.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DateOverrideRule implements ScheduleRule
{
    /**
     * @param string      $date     Exact calendar date in `Y-m-d` format.
     * @param DaySchedule $schedule Replacement schedule to apply on that date.
     */
    public function __construct(
        private string $date,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     *
     * Matching is string-based against the normalized `Y-m-d` representation so the rule
     * remains timezone-agnostic once the resolver has prepared the query date.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        return $date->format('Y-m-d') === $this->date;
    }

    /**
     * Return the schedule that replaces the baseline schedule for the matched date.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Return the overridden calendar date in `Y-m-d` format.
     */
    public function date(): string
    {
        return $this->date;
    }
}
