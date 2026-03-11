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
 * Thrown when a top-level array definition contains an unsupported schedule key.
 *
 * Native schedule definitions only accept weekday names plus the reserved
 * `exceptions` key. Rejecting unknown keys early helps surface typos and avoids
 * silently ignoring configuration that the resolver would never consult.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedScheduleKey extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for a top-level key outside the supported vocabulary.
     */
    public static function withKey(string $key): self
    {
        return new self(sprintf('Unsupported schedule key [%s].', $key));
    }
}
