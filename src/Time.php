<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Exceptions\InvalidTimeString;
use Cline\OpeningHours\Helpers\DataTrait;
use Cline\OpeningHours\Helpers\DateTimeCopier;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Date;
use Stringable;

use function explode;
use function mb_strlen;
use function mb_substr;
use function preg_match;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Time implements ComparableTime, Stringable
{
    use DataTrait;
    use DateTimeCopier;

    protected function __construct(
        private int $hours,
        private int $minutes,
        public mixed $data = null,
        private ?DateTimeInterface $date = null,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    public static function fromString(string $string, mixed $data = null, ?DateTimeInterface $date = null): self
    {
        if (!preg_match('/^(([0-1]\d|2[0-3]):[0-5]\d|24:00)$/', $string)) {
            throw InvalidTimeString::forString($string);
        }

        [$hours, $minutes] = explode(':', $string);

        return new self((int) $hours, (int) $minutes, $data, $date);
    }

    public static function fromDateTime(DateTimeInterface $dateTime, mixed $data = null): self
    {
        return self::fromString($dateTime->format(self::TIME_FORMAT), $data);
    }

    public function hours(): int
    {
        return $this->hours;
    }

    public function minutes(): int
    {
        return $this->minutes;
    }

    public function date(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function isSame(ComparableTime $time): bool
    {
        return $this->format('H:i:s.u') === $time->format('H:i:s.u');
    }

    public function isAfter(ComparableTime $time): bool
    {
        return $this->format('H:i:s.u') > $time->format('H:i:s.u');
    }

    public function isBefore(ComparableTime $time): bool
    {
        return $this->format('H:i:s.u') < $time->format('H:i:s.u');
    }

    public function isSameOrAfter(ComparableTime $time): bool
    {
        if ($this->isSame($time)) {
            return true;
        }

        return $this->isAfter($time);
    }

    public function diff(ComparableTime $time): DateInterval
    {
        return $this->toDateTime()->diff($time->toDateTime());
    }

    public function toDateTime(?DateTimeInterface $date = null): DateTimeInterface
    {
        $date = $date instanceof DateTimeInterface ? $this->copyDateTime($date) : Date::parse('1970-01-01 00:00:00');

        return $date->setTime($this->hours, $this->minutes);
    }

    public function format(string $format = self::TIME_FORMAT, DateTimeZone|string|null $timezone = null): string
    {
        $date = $this->date ?: (
            $timezone
            ? new DateTimeImmutable(
                '1970-01-01 00:00:00',
                $timezone instanceof DateTimeZone
                ? $timezone
                : new DateTimeZone($timezone),
            )
            : null
        );

        if ($this->hours === 24 && $this->minutes === 0 && mb_substr($format, 0, 3) === self::TIME_FORMAT) {
            return '24:00'.$this->formatSecond($format, $date);
        }

        return $this->toDateTime($date)->format($format);
    }

    private function formatSecond(string $format, ?DateTimeImmutable $date = null): string
    {
        return mb_strlen($format) > 3
            ? ($date ?? CarbonImmutable::parse('1970-01-01 00:00:00'))->format(mb_substr($format, 3))
            : '';
    }
}
