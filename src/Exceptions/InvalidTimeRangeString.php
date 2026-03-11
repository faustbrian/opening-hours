<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;

use function sprintf;

/**
 * Thrown when a local range string cannot be parsed into opening boundaries.
 *
 * This error is raised before individual endpoint times are resolved, so it
 * specifically signals that the native `HH:MM-HH:MM` wrapper format is broken
 * rather than that one endpoint merely contains an invalid time value.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTimeRangeString extends Exception implements OpeningHoursException
{
    /**
     * Create an exception for a malformed native range definition.
     *
     * The message includes the original value so callers can report which
     * opening-hours segment failed validation.
     */
    public static function forString(string $string): self
    {
        return new self(sprintf("The string `%s` isn't a valid time range string. A time string must be a formatted as `H:i-H:i`, e.g. `09:00-18:00`.", $string));
    }
}
