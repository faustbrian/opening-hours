<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a day definition is not expressed as an array of ranges.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DaySchedulesMustBeDefinedAsArrays extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for a day schedule with an invalid container type.
     */
    public static function daySchedulesMustBeDefinedAsArrays(): self
    {
        return new self('Day schedules must be defined as arrays.');
    }
}
