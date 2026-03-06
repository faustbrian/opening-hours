<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;
use Throwable;

use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDayName extends Exception implements OpeningHoursException
{
    public static function invalidDayName(string $name, ?Throwable $previous = null): self
    {
        return new self(
            sprintf("Day `%s` isn't a valid day name. Valid day names are lowercase english words, e.g. `monday`, `thursday`.", $name),
            previous: $previous,
        );
    }
}
