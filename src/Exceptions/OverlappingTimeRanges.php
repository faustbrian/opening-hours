<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use Exception;
use Stringable;

use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class OverlappingTimeRanges extends Exception implements OpeningHoursException
{
    public static function forRanges(Stringable|string $rangeA, Stringable|string $rangeB): self
    {
        return new self(sprintf('Time ranges %s and %s overlap.', $rangeA, $rangeB));
    }
}
