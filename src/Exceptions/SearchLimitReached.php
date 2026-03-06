<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use DateTimeInterface;
use Exception;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class SearchLimitReached extends Exception implements OpeningHoursException
{
    public static function forDate(DateTimeInterface $dateTime): self
    {
        return new self('Search reached the limit: '.$dateTime->format('Y-m-d H:i:s.u e'));
    }
}
