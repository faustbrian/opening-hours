<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Thrown when a Schema.org `closes` value cannot be normalized into local time.
 *
 * Schema.org entries are validated before they are converted into internal day
 * schedules. This exception isolates the failure to the `closes` field so
 * consumers can distinguish malformed closing boundaries from other structured
 * data problems.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStructuredDataClosesHour extends InvalidOpeningHoursSpecification
{
    /**
     * Create an exception for a malformed Schema.org `closes` field.
     *
     * The parser accepts `HH:MM` and `HH:MM:SS` inputs, so this failure means
     * the value was present but still could not be interpreted as a valid local
     * closing time.
     */
    public static function invalidStructuredDataClosesHour(): self
    {
        return new self('Invalid closes hours');
    }
}
