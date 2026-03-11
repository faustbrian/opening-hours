<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when Schema.org `PublicHolidays` is provided.
 *
 * The package models explicit weekdays and explicit date-based exceptions only.
 * Public-holiday calendars depend on locale and are therefore not inferred.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PublicHolidaysNotSupported extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for unsupported `PublicHolidays` values.
     */
    public static function publicHolidaysNotSupported(): self
    {
        return new self('PublicHolidays not supported');
    }
}
