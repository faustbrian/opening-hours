<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org `validThrough` date is malformed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStructuredDataValidThroughDate extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for an invalid Schema.org `validThrough` date.
     */
    public static function invalidStructuredDataValidThroughDate(): self
    {
        return new self('Invalid validThrough date');
    }
}
