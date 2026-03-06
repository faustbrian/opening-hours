<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Config;

use DateTimeZone;
use Exception;

/**
 * Immutable query-time overrides for timezone conversion and search depth.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class QueryOptions
{
    /**
     * Source timezone used to interpret queried dates and times.
     */
    public ?DateTimeZone $timezone;

    /**
     * Timezone used when formatting returned dates and times.
     */
    public ?DateTimeZone $outputTimezone;

    /**
     * @param null|DateTimeZone|string $timezone        Source timezone object or timezone identifier.
     * @param null|DateTimeZone|string $outputTimezone  Output timezone object or timezone identifier.
     * @param int                      $maxDaysToSearch Maximum number of days to inspect when resolving matches.
     *
     * @throws Exception If either timezone identifier is invalid.
     */
    public function __construct(
        DateTimeZone|string|null $timezone = null,
        DateTimeZone|string|null $outputTimezone = null,
        /**
         * Maximum number of days to inspect when resolving future matches.
         */
        public int $maxDaysToSearch = 8,
    ) {
        $this->timezone = match (true) {
            $timezone instanceof DateTimeZone => $timezone,
            $timezone === null => null,
            default => new DateTimeZone($timezone),
        };
        $this->outputTimezone = match (true) {
            $outputTimezone instanceof DateTimeZone => $outputTimezone,
            $outputTimezone === null => null,
            default => new DateTimeZone($outputTimezone),
        };
    }

    /**
     * Creates a new instance with non-null values from the given overrides.
     *
     * @param null|self $options Override values to merge into the current options.
     *
     * @throws Exception If override timezone identifiers are invalid during construction.
     * @return self      Merged query options, or the current instance when no overrides are given.
     */
    public function withOverrides(?self $options = null): self
    {
        if (!$options instanceof self) {
            return $this;
        }

        return new self(
            $options->timezone ?? $this->timezone,
            $options->outputTimezone ?? $this->outputTimezone,
            $options->maxDaysToSearch,
        );
    }
}
