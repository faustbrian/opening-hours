<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Value;

use Cline\OpeningHours\Exceptions\InvalidTimeRangeString;
use Stringable;

use function count;
use function explode;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 * Represents a local opening-hours range between two clock times.
 *
 * @psalm-immutable
 */
final readonly class LocalTimeRange implements Stringable
{
    private function __construct(
        private LocalTime $start,
        private LocalTime $end,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Creates a range from a `HH:MM-HH:MM` string.
     *
     * @throws InvalidTimeRangeString
     */
    public static function fromString(string $range): self
    {
        $parts = explode('-', $range);

        if (count($parts) !== 2) {
            throw InvalidTimeRangeString::forString($range);
        }

        return new self(
            LocalTime::fromString($parts[0]),
            LocalTime::fromString($parts[1]),
        );
    }

    /**
     * Returns the inclusive start time of the range.
     */
    public function start(): LocalTime
    {
        return $this->start;
    }

    /**
     * Returns the exclusive end time of the range.
     */
    public function end(): LocalTime
    {
        return $this->end;
    }

    /**
     * Returns `true` when the end time falls on the following calendar day.
     */
    public function wrapsToNextDay(): bool
    {
        return $this->end->minutesSinceMidnight() <= $this->start->minutesSinceMidnight();
    }

    /**
     * Checks whether the time is inside the range.
     *
     * The start is inclusive and the end is exclusive.
     */
    public function contains(LocalTime $time): bool
    {
        $value = $time->minutesSinceMidnight();
        $start = $this->start->minutesSinceMidnight();
        $end = $this->end->minutesSinceMidnight();

        if (!$this->wrapsToNextDay()) {
            return $value >= $start && $value < $end;
        }

        return $value >= $start || $value < $end;
    }

    /**
     * Checks whether this range shares any time with another range.
     */
    public function overlaps(self $other): bool
    {
        foreach ($this->segments() as [$start, $end]) {
            foreach ($other->segments() as [$otherStart, $otherEnd]) {
                if ($start < $otherEnd && $otherStart < $end) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formats the range as `HH:MM-HH:MM`.
     */
    public function format(): string
    {
        return sprintf('%s-%s', $this->start->format(), $this->end->format());
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function segments(): array
    {
        $start = $this->start->minutesSinceMidnight();
        $end = $this->end->minutesSinceMidnight();

        if (!$this->wrapsToNextDay()) {
            return [[$start, $end]];
        }

        return [
            [$start, 1_440],
            [0, $end],
        ];
    }
}
