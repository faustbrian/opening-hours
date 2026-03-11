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
 * Immutable local opening window between two wall-clock times.
 *
 * This value object is the canonical representation used by day schedules,
 * schedule resolution, and Schema.org parsing. A range may either stay within
 * a single calendar day or wrap past midnight into the following day. The
 * package treats the start as inclusive and the end as exclusive so adjacent
 * ranges can touch without overlapping.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class LocalTimeRange implements Stringable
{
    private function __construct(
        private LocalTime $start,
        private LocalTime $end,
    ) {}

    /**
     * Format the range for string contexts.
     *
     * This delegates to {@see self::format()} so the textual representation
     * remains identical anywhere a range is cast to a string.
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Create a range from the package's native `HH:MM-HH:MM` definition format.
     *
     * Both endpoints are parsed through {@see LocalTime::fromString()}, which
     * means `24:00` is accepted for end-of-day boundaries. Validation is kept
     * strict here so malformed definitions fail before they reach schedule
     * normalization or overlap detection.
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
     * Get the inclusive opening boundary for the range.
     *
     * Consumers use this when anchoring the range onto a concrete schedule date
     * or when sorting multiple ranges into stable start-time order.
     */
    public function start(): LocalTime
    {
        return $this->start;
    }

    /**
     * Get the exclusive closing boundary for the range.
     *
     * The end may be earlier than the start when the range carries past
     * midnight, and `24:00` is preserved so full-day boundaries can be
     * represented without truncation.
     */
    public function end(): LocalTime
    {
        return $this->end;
    }

    /**
     * Determine whether the range extends into the following calendar day.
     *
     * Opening-hours definitions model overnight service by keeping a single
     * logical range whose end time is less than or equal to its start time.
     * Schedule queries use this flag to include the previous day's carry-over
     * availability when answering "is open now" style lookups.
     */
    public function wrapsToNextDay(): bool
    {
        return $this->end->minutesSinceMidnight() <= $this->start->minutesSinceMidnight();
    }

    /**
     * Determine whether a local time falls within this opening window.
     *
     * The start is inclusive and the end is exclusive. Overnight ranges are
     * treated as two logical segments split around midnight so a single range
     * can answer containment checks for both the opening day and the carried
     * portion after midnight.
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
     * Determine whether this range shares any real opening time with another.
     *
     * Both ranges are reduced to one-day or two-day numeric segments before
     * comparison. This keeps overlap detection correct even when one or both
     * ranges wrap across midnight.
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
     * Format the range using the package's stable definition syntax.
     *
     * The returned value is suitable for debugging, serialization helpers, and
     * round-tripping through {@see self::fromString()}.
     */
    public function format(): string
    {
        return sprintf('%s-%s', $this->start->format(), $this->end->format());
    }

    /**
     * Split the range into comparable minute segments inside a 24-hour frame.
     *
     * Non-overnight ranges yield a single segment. Overnight ranges are split
     * at midnight so overlap checks can remain a straightforward interval
     * comparison instead of carrying special-case branching in callers.
     *
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
