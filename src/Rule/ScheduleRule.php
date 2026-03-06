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
 * @author Brian Faust <brian@cline.sh>
 * Contract for date-based schedule overrides.
 */
interface ScheduleRule
{
    /**
     * Determines whether this rule applies to the provided date.
     */
    public function appliesTo(DateTimeInterface $date): bool;

    /**
     * Returns the day schedule that replaces the base weekly schedule.
     */
    public function schedule(): DaySchedule;
}
