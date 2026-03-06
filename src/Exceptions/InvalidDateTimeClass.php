<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;

use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDateTimeClass extends Exception implements OpeningHoursException
{
    public static function forString(string $string): self
    {
        return new self(sprintf("The string `%s` isn't a valid class implementing DateTimeInterface.", $string));
    }
}
