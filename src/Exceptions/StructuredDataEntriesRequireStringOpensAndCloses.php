<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org item is open but does not provide string hours.
 *
 * Once an entry is not considered closed, both `opens` and `closes` must be
 * present as strings so the parser can create a concrete {@see \Cline\OpeningHours\Schedule\DaySchedule}.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StructuredDataEntriesRequireStringOpensAndCloses extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for non-string Schema.org `opens` or `closes` values.
     */
    public static function structuredDataEntriesRequireStringOpensAndCloses(): self
    {
        return new self('Structured-data entries require string opens and closes values.');
    }
}
