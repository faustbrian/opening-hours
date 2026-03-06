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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DateOverrideRule implements ScheduleRule
{
    /**
     * @param string $date Calendar date in `Y-m-d` format.
     */
    public function __construct(
        private string $date,
        private DaySchedule $schedule,
    ) {}

    /**
     * Determine whether this override applies to the given date.
     */
    public function appliesTo(DateTimeInterface $date): bool
    {
        return $date->format('Y-m-d') === $this->date;
    }

    /**
     * Get the schedule that replaces the normal one for the matched date.
     */
    public function schedule(): DaySchedule
    {
        return $this->schedule;
    }

    /**
     * Get the overridden calendar date in `Y-m-d` format.
     */
    public function date(): string
    {
        return $this->date;
    }
}
