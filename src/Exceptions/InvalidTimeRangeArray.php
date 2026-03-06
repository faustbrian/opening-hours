<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTimeRangeArray extends Exception implements OpeningHoursException
{
    public static function create(): self
    {
        return new self('TimeRange array definition must at least contains an "hours" property.');
    }
}
