<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when exception override keys are not strings.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExceptionKeysMustBeStrings extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for non-string exception override keys.
     */
    public static function exceptionKeysMustBeStrings(): self
    {
        return new self('Exception keys must be strings.');
    }
}
