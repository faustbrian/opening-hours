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
 * Immutable query-time overrides for schedule resolution and formatting.
 *
 * Opening-hours definitions are stored in local schedule terms, but callers may
 * need to ask questions in a different timezone or look ahead a bounded number
 * of days when searching for the next opening. This value object carries those
 * per-query overrides without mutating the underlying `OpeningHours` instance.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class QueryOptions
{
    /**
     * Timezone used to reinterpret incoming query dates and times before lookup.
     *
     * When present, schedules are evaluated against the local wall-clock time in
     * this timezone rather than the timezone attached to the original input
     * value.
     */
    public ?DateTimeZone $timezone;

    /**
     * Timezone applied when returning resolved datetimes to callers.
     *
     * This is separate from `$timezone` so consumers can query in one timezone
     * and present results in another.
     */
    public ?DateTimeZone $outputTimezone;

    /**
     * Normalize timezone overrides into concrete `DateTimeZone` instances.
     *
     * String identifiers are resolved eagerly so invalid configuration fails at
     * construction time instead of much later during schedule lookup.
     *
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
     * Merge explicit override values onto the current query options.
     *
     * A `null` override means "keep the existing value" for the timezone
     * fields, while `maxDaysToSearch` is always taken from the override object
     * once one is provided. This mirrors how package entry points combine
     * default query options with call-specific overrides.
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
