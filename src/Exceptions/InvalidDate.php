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
final class InvalidDate extends Exception implements OpeningHoursException
{
    public static function invalidDate(string $date): self
    {
        return new self(sprintf("Date `%s` isn't a valid date. Dates should be formatted as Y-m-d, e.g. `2016-12-25`.", $date));
    }
}
