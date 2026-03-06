<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use function sprintf;

/**
 * Thrown when the top-level schedule definition contains an unknown key.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedScheduleKey extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for an unsupported top-level schedule key.
     */
    public static function withKey(string $key): self
    {
        return new self(sprintf('Unsupported schedule key [%s].', $key));
    }
}
