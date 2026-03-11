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
 * Applies a replacement day schedule to every date in an inclusive calendar range.
 *
 * This rule models temporary seasons or multi-day closure windows where the same daily
 * schedule should be used for each date in the range. The comparison is inclusive on
 * both ends and relies on canonical `Y-m-d` strings so lexical ordering matches
 * chronological ordering.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DateRangeOverrideRule implements ScheduleRule
{
    /**
     * @param string      $from     Inclusive range start in `Y-m-d` format.
     * @param string      $through  Inclusive range end in `Y-m-d` format.
     * @param DaySchedule $schedule Replacement schedule used for every matching date.
     */
    public function __construct(
        private string $from,
        private string $through,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     *
     * The comparison uses normalized `Y-m-d` strings so the rule remains simple and does
     * not depend on mutable date arithmetic during lookup.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        $value = $date->format('Y-m-d');

        return $value >= $this->from && $value <= $this->through;
    }

    /**
     * Return the schedule that replaces the baseline schedule for matching dates.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Return the inclusive range start in `Y-m-d` format.
     */
    public function from(): string
    {
        return $this->from;
    }

    /**
     * Return the inclusive range end in `Y-m-d` format.
     */
    public function through(): string
    {
        return $this->through;
    }
}
