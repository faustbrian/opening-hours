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
final class InvalidDateRange extends Exception implements OpeningHoursException
{
    public static function invalidDateRange(string $entry, string $date): self
    {
        return new self(sprintf('Unable to record `%s` as it would override `%s`.', $entry, $date));
    }
}
