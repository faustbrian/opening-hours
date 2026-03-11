<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org specification list contains a non-array item.
 *
 * Each item must expose named fields such as `dayOfWeek`, `opens`, and
 * `validFrom`, so scalar entries cannot be interpreted safely by the parser.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OpeningHoursSpecificationItemsMustBeArrays extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for a malformed specification list item.
     */
    public static function openingHoursSpecificationItemsMustBeArrays(): self
    {
        return new self('Each openingHoursSpecification item must be an array.');
    }
}
