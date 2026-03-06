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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class MonthDayOverrideRule implements ScheduleRule
{
    /**
     * @param string $monthDay Month and day in `m-d` format.
     */
    public function __construct(
        private string $monthDay,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        return $date->format('m-d') === $this->monthDay;
    }

    /**
     * Get the schedule that replaces the normal one for matching dates.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Get the recurring month-day key in `m-d` format.
     */
    public function monthDay(): string
    {
        return $this->monthDay;
    }
}
