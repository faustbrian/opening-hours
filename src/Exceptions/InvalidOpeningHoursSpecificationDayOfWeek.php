<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org `dayOfWeek` value is not a string.
 *
 * The parser supports canonical weekday names and fully-qualified schema.org
 * URLs, but both supported variants still need to arrive as strings.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidOpeningHoursSpecificationDayOfWeek extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for a non-string Schema.org `dayOfWeek` value.
     */
    public static function invalidOpeningHoursSpecificationDayOfWeek(): self
    {
        return new self('Invalid https://schema.org/OpeningHoursSpecification dayOfWeek');
    }
}
