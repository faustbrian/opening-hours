<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org day value is not one of the supported weekdays.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidSchemaOrgDaySpecification extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for an unsupported Schema.org day specification.
     */
    public static function invalidSchemaOrgDaySpecification(): self
    {
        return new self('Invalid https://schema.org Day specification');
    }
}
