<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use InvalidArgumentException;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTimezone extends InvalidArgumentException implements OpeningHoursException
{
    public static function create(): self
    {
        return new self('Invalid Timezone');
    }
}
