<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when only one of Schema.org `opens` or `closes` is provided.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StructuredDataOpensAndClosesMustBothBeNullOrStrings extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for mismatched Schema.org `opens` and `closes` values.
     */
    public static function structuredDataOpensAndClosesMustBothBeNullOrStrings(): self
    {
        return new self('Property opens and closes must be both null or both string');
    }
}
