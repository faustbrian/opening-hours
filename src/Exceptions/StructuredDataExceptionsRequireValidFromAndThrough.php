<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org exception entry is missing validity dates.
 *
 * Entries without `dayOfWeek` are treated as date-bound overrides, so both
 * `validFrom` and `validThrough` are required to define their scope.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StructuredDataExceptionsRequireValidFromAndThrough extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for missing Schema.org validity dates.
     */
    public static function structuredDataExceptionsRequireValidFromAndThrough(): self
    {
        return new self('Structured-data exceptions require validFrom and validThrough.');
    }
}
