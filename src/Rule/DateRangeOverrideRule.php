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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DateRangeOverrideRule implements ScheduleRule
{
    /**
     * @param string $from    Inclusive range start in `Y-m-d` format.
     * @param string $through Inclusive range end in `Y-m-d` format.
     */
    public function __construct(
        private string $from,
        private string $through,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        $value = $date->format('Y-m-d');

        return $value >= $this->from && $value <= $this->through;
    }

    /**
     * Get the schedule that replaces the normal one for matching dates.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Get the inclusive range start in `Y-m-d` format.
     */
    public function from(): string
    {
        return $this->from;
    }

    /**
     * Get the inclusive range end in `Y-m-d` format.
     */
    public function through(): string
    {
        return $this->through;
    }
}
