<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;
use Throwable;

use function sprintf;

/**
 * Thrown when a day name cannot be mapped to {@see \Cline\OpeningHours\Value\Day}.
 *
 * This is primarily raised when converting formatted `DateTimeInterface`
 * weekday names or user-supplied strings into the package's lowercase weekday
 * enum values.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDayName extends Exception implements OpeningHoursException
{
    /**
     * Create an exception for a day name that does not map to the weekday enum.
     *
     * @param string     $name     The invalid day name.
     * @param ?Throwable $previous The original enum conversion failure, if available.
     */
    public static function invalidDayName(string $name, ?Throwable $previous = null): self
    {
        return new self(
            sprintf("Day `%s` isn't a valid day name. Valid day names are lowercase english words, e.g. `monday`, `thursday`.", $name),
            previous: $previous,
        );
    }
}
