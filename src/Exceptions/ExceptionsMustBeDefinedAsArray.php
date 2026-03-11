<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when the reserved `exceptions` schedule entry is not an array.
 *
 * The package interprets this section as a keyed collection of date, month-day,
 * or date-range overrides. A non-array value means those override rules cannot
 * be parsed deterministically, so definition loading fails immediately.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExceptionsMustBeDefinedAsArray extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for a malformed top-level `exceptions` definition.
     */
    public static function exceptionsMustBeDefinedAsArray(): self
    {
        return new self('Exceptions must be defined as an array.');
    }
}
