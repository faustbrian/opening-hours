<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Value;

use Cline\OpeningHours\Exceptions\InvalidTimeString;
use DateTimeInterface;
use Stringable;

use function explode;
use function preg_match;
use function sprintf;

/**
 * Immutable wall-clock time used by opening-hours definitions.
 *
 * This type deliberately omits date and timezone information. It represents
 * the local clock values stored inside weekly schedules, exception rules, and
 * Schema.org conversions. The package also permits `24:00` as a synthetic
 * end-of-day value so closing boundaries can be modeled without forcing a next
 * day date rollover at the value-object level.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class LocalTime implements Stringable
{
    private function __construct(
        private int $hours,
        private int $minutes,
    ) {}

    /**
     * Format the time for string contexts.
     *
     * The package uses the same normalized `HH:MM` representation for
     * serialization, exception messages, and schedule formatting.
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Create a local time from the package's normalized `HH:MM` format.
     *
     * Validation accepts ordinary 24-hour times plus `24:00`, which is reserved
     * for end-of-day boundaries and therefore cannot be expressed by PHP's
     * native time parsing alone.
     *
     * @throws InvalidTimeString
     */
    public static function fromString(string $time): self
    {
        if (!preg_match('/^(([0-1]\d|2[0-3]):[0-5]\d|24:00)$/', $time)) {
            throw InvalidTimeString::forString($time);
        }

        [$hours, $minutes] = explode(':', $time);

        return new self((int) $hours, (int) $minutes);
    }

    /**
     * Create a local time from the hour and minute parts of a date-time value.
     *
     * Seconds, subseconds, and any offset metadata are intentionally discarded.
     * This allows schedule resolution to compare only wall-clock values after
     * any query-time timezone normalization has already been applied upstream.
     *
     * @throws InvalidTimeString
     */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromString($dateTime->format('H:i'));
    }

    /**
     * Get the hour component in 24-hour notation.
     *
     * This returns `24` only for the synthetic `24:00` boundary.
     */
    public function hours(): int
    {
        return $this->hours;
    }

    /**
     * Get the minute component of the local time.
     */
    public function minutes(): int
    {
        return $this->minutes;
    }

    /**
     * Convert the time into minutes since midnight.
     *
     * The special `24:00` value is represented as `1440`, which lets ordering,
     * range comparisons, and interval anchoring work with ordinary integer
     * arithmetic.
     */
    public function minutesSinceMidnight(): int
    {
        return ($this->hours * 60) + $this->minutes;
    }

    /**
     * Determine whether this value is the synthetic end-of-day boundary.
     *
     * The package uses this primarily when a closing boundary should be
     * considered the end of the current service day rather than midnight at the
     * start of the day.
     */
    public function isEndOfDay(): bool
    {
        return $this->minutesSinceMidnight() === 1_440;
    }

    /**
     * Determine whether this time occurs before another local time.
     *
     * Comparison is purely numeric and does not interpret wrap-around or
     * date-specific context. That higher-level semantics is handled by ranges
     * and schedule intervals.
     */
    public function isBefore(self $other): bool
    {
        return $this->minutesSinceMidnight() < $other->minutesSinceMidnight();
    }

    /**
     * Determine whether this time occurs after another local time.
     */
    public function isAfter(self $other): bool
    {
        return $this->minutesSinceMidnight() > $other->minutesSinceMidnight();
    }

    /**
     * Format the time using the package's canonical `HH:MM` output.
     */
    public function format(): string
    {
        return sprintf('%02d:%02d', $this->hours, $this->minutes);
    }
}
