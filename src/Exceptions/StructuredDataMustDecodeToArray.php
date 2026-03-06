<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when decoded Schema.org data does not produce an array.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StructuredDataMustDecodeToArray extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for decoded structured data with an unexpected type.
     */
    public static function structuredDataMustDecodeToArray(): self
    {
        return new self('Structured data must decode to an array.');
    }
}
