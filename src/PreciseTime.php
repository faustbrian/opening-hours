<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Helpers\DataTrait;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

use function throw_if;

/**
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PreciseTime implements ComparableTime, Stringable
{
    use DataTrait;

    private function __construct(
        private DateTimeInterface $dateTime,
        public mixed $data = null,
    ) {}

    public function __toString(): string
    {
        return $this->format();
    }

    public static function fromString(string $string, mixed $data = null, ?DateTimeInterface $date = null): self
    {
        throw_if($date instanceof DateTimeInterface, InvalidArgumentException::class, self::class.' does not support date reference point');

        return self::fromDateTime(
            new DateTimeImmutable($string),
            $data,
        );
    }

    public static function fromDateTime(DateTimeInterface $dateTime, mixed $data = null): self
    {
        return new self($dateTime, $data);
    }

    public function hours(): int
    {
        return (int) $this->dateTime->format('G');
    }

    public function minutes(): int
    {
        return (int) $this->dateTime->format('i');
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
        return $this->format('H:i:s.u') >= $time->format('H:i:s.u');
    }

    public function diff(ComparableTime $time): DateInterval
    {
        return $this->toDateTime()->diff($time->toDateTime());
    }

    public function toDateTime(?DateTimeInterface $date = null): DateTimeInterface
    {
        if (!$date instanceof DateTimeInterface) {
            return $this->dateTime;
        }

        $copiedDate = $date instanceof DateTimeImmutable ? $date : clone $date;
        $modifier = $this->format('H:i:s.u');

        if ($copiedDate instanceof DateTimeImmutable) {
            return $copiedDate->modify($modifier);
        }

        $copiedDate->modify($modifier);

        return $copiedDate;
    }

    public function format(string $format = self::TIME_FORMAT, DateTimeZone|string|null $timezone = null): string
    {
        if ($timezone === null) {
            return $this->dateTime->format($format);
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        $copiedDate = $this->dateTime instanceof DateTimeImmutable ? $this->dateTime : clone $this->dateTime;

        if ($copiedDate instanceof DateTimeImmutable) {
            return $copiedDate->setTimezone($timezone)->format($format);
        }

        $copiedDate->setTimezone($timezone);

        return $copiedDate->format($format);
    }
}
