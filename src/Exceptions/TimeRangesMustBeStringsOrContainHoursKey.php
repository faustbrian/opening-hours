<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a range definition is neither a string nor an array with `hours`.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TimeRangesMustBeStringsOrContainHoursKey extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for an invalid range definition structure.
     */
    public static function timeRangesMustBeStringsOrContainHoursKey(): self
    {
        return new self('Time ranges must be strings or arrays with an hours key.');
    }
}
