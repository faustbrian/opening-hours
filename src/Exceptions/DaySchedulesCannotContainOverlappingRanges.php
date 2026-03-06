<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a day definition includes overlapping local time ranges.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DaySchedulesCannotContainOverlappingRanges extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for overlapping day-schedule ranges.
     */
    public static function daySchedulesCannotContainOverlappingRanges(): self
    {
        return new self('Day schedules cannot contain overlapping ranges.');
    }
}
