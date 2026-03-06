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
final class InvalidTimeRangeString extends Exception implements OpeningHoursException
{
    public static function forString(string $string): self
    {
        return new self(sprintf("The string `%s` isn't a valid time range string. A time string must be a formatted as `H:i-H:i`, e.g. `09:00-18:00`.", $string));
    }
}
