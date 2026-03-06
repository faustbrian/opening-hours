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
 * @author Brian Faust <brian@cline.sh>
 * Immutable local clock time without timezone context.
 *
 * @psalm-immutable
 */
final readonly class LocalTime implements Stringable
{
    private function __construct(
        private int $hours,
        private int $minutes,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Creates a local time from `HH:MM`, allowing `24:00` for end-of-day use.
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
     * Creates a local time using the hour and minute from a date-time value.
     *
     * Seconds and timezone offset are ignored.
     *
     * @throws InvalidTimeString
     */
    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromString($dateTime->format('H:i'));
    }

    /**
     * Returns the hour component in 24-hour format.
     */
    public function hours(): int
    {
        return $this->hours;
    }

    /**
     * Returns the minute component.
     */
    public function minutes(): int
    {
        return $this->minutes;
    }

    /**
     * Returns minutes since midnight, with `24:00` represented as `1440`.
     */
    public function minutesSinceMidnight(): int
    {
        return ($this->hours * 60) + $this->minutes;
    }

    /**
     * Returns `true` only for `24:00`.
     */
    public function isEndOfDay(): bool
    {
        return $this->minutesSinceMidnight() === 1_440;
    }

    /**
     * Compares this time with another local time.
     */
    public function isBefore(self $other): bool
    {
        return $this->minutesSinceMidnight() < $other->minutesSinceMidnight();
    }

    /**
     * Compares this time with another local time.
     */
    public function isAfter(self $other): bool
    {
        return $this->minutesSinceMidnight() > $other->minutesSinceMidnight();
    }

    /**
     * Formats the time as `HH:MM`.
     */
    public function format(): string
    {
        return sprintf('%02d:%02d', $this->hours, $this->minutes);
    }
}
