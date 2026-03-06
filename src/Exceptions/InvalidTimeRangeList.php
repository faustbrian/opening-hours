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
final class InvalidTimeRangeList extends Exception implements OpeningHoursException
{
    public static function create(): self
    {
        return new self('The given list is not a valid list of TimeRange instance containing at least one range.');
    }
}
