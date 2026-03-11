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
 * Thrown when an exception override key uses an unsupported date syntax.
 *
 * Native exception keys must be exact dates, recurring month/day pairs, or
 * inclusive date ranges. Anything else is rejected to avoid ambiguous override
 * semantics.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedExceptionKey extends InvalidOpeningHoursDefinition
{
    /**
     * Create an exception for an unsupported exception override key.
     */
    public static function withKey(string $key): self
    {
        return new self(sprintf('Unsupported exception key [%s].', $key));
    }
}
