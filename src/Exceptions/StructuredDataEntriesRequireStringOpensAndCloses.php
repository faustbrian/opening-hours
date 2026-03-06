<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when Schema.org entries omit string `opens` or `closes` values.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StructuredDataEntriesRequireStringOpensAndCloses extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for non-string Schema.org `opens` or `closes` values.
     */
    public static function structuredDataEntriesRequireStringOpensAndCloses(): self
    {
        return new self('Structured-data entries require string opens and closes values.');
    }
}
