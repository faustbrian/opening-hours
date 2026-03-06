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
 * Thrown when Schema.org opening-hours JSON cannot be decoded.
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
