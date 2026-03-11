<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org `opens` value is malformed.
 *
 * The parser only accepts `HH:MM` or `HH:MM:SS` strings here, which are then
 * normalized into the package's local-time representation before schedule
 * construction.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStructuredDataOpensHour extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for an invalid Schema.org `opens` value.
     */
    public static function invalidStructuredDataOpensHour(): self
    {
        return new self('Invalid opens hour');
    }
}
