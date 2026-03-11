<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

use JsonException;

/**
 * Thrown when raw Schema.org JSON cannot be decoded into an array payload.
 *
 * The original {@see JsonException} is preserved as the previous exception so
 * callers can inspect the lower-level parse failure when they need exact JSON
 * diagnostics.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidOpeningHoursSpecificationJson extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception from the underlying JSON decoding failure.
     */
    public static function invalidJson(JsonException $previous): self
    {
        return new self(
            'Invalid https://schema.org/OpeningHoursSpecification JSON',
            $previous->getCode(),
            $previous,
        );
    }
}
