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
final class NonMutableOffsets extends Exception implements OpeningHoursException
{
    public static function forClass(string $className): self
    {
        return new self(sprintf('Offsets of `%s` objects are not mutable.', $className));
    }
}
