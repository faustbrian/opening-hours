<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when the `exceptions` definition is not an array.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExceptionsMustBeDefinedAsArray extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for a non-array `exceptions` definition.
     */
    public static function exceptionsMustBeDefinedAsArray(): self
    {
        return new self('Exceptions must be defined as an array.');
    }
}
