<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a native range definition cannot be normalized into hours.
 *
 * Range entries may be provided directly as strings or wrapped in arrays that
 * expose an `hours` key for backward compatibility. Any other shape is
 * ambiguous and therefore rejected.
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
