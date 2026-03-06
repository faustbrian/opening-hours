<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Exceptions\InvalidTimeRangeArray;
use Cline\OpeningHours\Exceptions\InvalidTimeRangeList;
use Cline\OpeningHours\Exceptions\InvalidTimeRangeString;
use Cline\OpeningHours\Helpers\DataTrait;
use Cline\OpeningHours\Helpers\DateTimeCopier;
use DateTimeZone;
use Stringable;

use function array_shift;
use function array_slice;
use function count;
use function explode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TimeRange implements Stringable, TimeDataContainer
{
    use DataTrait;
    use DateTimeCopier;

    protected function __construct(
        private Time $start,
        private Time $end,
        public mixed $data = null,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    public static function fromString(string $string, $data = null): self
    {
        $times = explode('-', $string);

        if (count($times) !== 2) {
            throw InvalidTimeRangeString::forString($string);
        }

        return new self(Time::fromString($times[0]), Time::fromString($times[1]), $data);
    }

    public static function fromArray(array $array): self
    {
        $values = [];
        $keys = ['hours', 'data'];

        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $values[$key] = $array[$key];
                unset($array[$key]);

                continue;
            }
        }

        foreach ($keys as $key) {
            if (isset($values[$key])) {
                continue;
            }

            $values[$key] = array_shift($array);
        }

        if (!$values['hours']) {
            throw InvalidTimeRangeArray::create();
        }

        if ($values['hours'] instanceof self) {
            if ($values['data'] === null) {
                return $values['hours'];
            }

            return new self($values['hours']->start, $values['hours']->end, $values['data']);
        }

        if (is_string($values['hours'])) {
            return self::fromString($values['hours'], $values['data']);
        }

        throw InvalidTimeRangeArray::create();
    }

    public static function fromDefinition($value): self
    {
        return is_array($value) ? self::fromArray($value) : self::fromString($value);
    }

    public static function fromList(array $ranges, $data = null): self
    {
        if ($ranges === []) {
            throw InvalidTimeRangeList::create();
        }

        foreach ($ranges as $range) {
            if (!$range instanceof self) {
                throw InvalidTimeRangeList::create();
            }
        }

        $start = $ranges[0]->start;
        $end = $ranges[0]->end;

        foreach (array_slice($ranges, 1) as $range) {
            if ($range->start->isBefore($start)) {
                $start = $range->start;
            }

            if (!$range->end->isAfter($end)) {
                continue;
            }

            $end = $range->end;
        }

        return new self($start, $end, $data);
    }

    public static function fromMidnight(Time $end, $data = null): self
    {
        return new self(Time::fromString(self::MIDNIGHT), $end, $data);
    }

    public static function fromTimes(Time $start, Time $end, mixed $data = null): self
    {
        return new self($start, $end, $data);
    }

    public function start(): Time
    {
        return $this->start;
    }

    public function end(): Time
    {
        return $this->end;
    }

    public function isReversed(): bool
    {
        return $this->start->isAfter($this->end);
    }

    public function overflowsNextDay(): bool
    {
        return $this->isReversed();
    }

    public function spillsOverToNextDay(): bool
    {
        return $this->isReversed();
    }

    public function containsTime(ComparableTime $time): bool
    {
        return $time->isSameOrAfter($this->start) && ($this->overflowsNextDay() || $time->isBefore($this->end));
    }

    public function containsNightTime(ComparableTime $time): bool
    {
        return $this->overflowsNextDay() && self::fromMidnight($this->end)->containsTime($time);
    }

    public function overlaps(self $timeRange): bool
    {
        if ($this->containsTime($timeRange->start)) {
            return true;
        }

        return $this->containsTime($timeRange->end);
    }

    public function format(string $timeFormat = self::TIME_FORMAT, string $rangeFormat = '%s-%s', DateTimeZone|string|null $timezone = null): string
    {
        return sprintf($rangeFormat, $this->start->format($timeFormat, $timezone), $this->end->format($timeFormat, $timezone));
    }
}
