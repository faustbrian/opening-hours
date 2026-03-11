<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org `validFrom` selector has an unsupported shape.
 *
 * The parser accepts either full dates (`Y-m-d`) or recurring month-day values
 * (`m-d`) so it can build exact-date, range, or annual override rules.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStructuredDataValidFromDate extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for an invalid Schema.org `validFrom` date.
     */
    public static function invalidStructuredDataValidFromDate(): self
    {
        return new self('Invalid validFrom date');
    }
}
