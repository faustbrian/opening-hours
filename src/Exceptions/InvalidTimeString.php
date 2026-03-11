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
 * Thrown when a local clock time does not match the package's accepted format.
 *
 * `LocalTime` accepts 24-hour values plus the special sentinel `24:00`, so
 * this exception identifies strings that cannot participate in schedule math
 * or be used as range boundaries.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTimeString extends Exception implements OpeningHoursException
{
    /**
     * Create an exception for a malformed local time string.
     *
     * @param string $string The invalid value that failed time parsing.
     */
    public static function forString(string $string): self
    {
        return new self(sprintf("The string `%s` isn't a valid time string. A time string must be a formatted as `H:i`, e.g. `06:00`, `18:00`.", $string));
    }
}
