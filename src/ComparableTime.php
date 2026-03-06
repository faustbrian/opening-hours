<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface ComparableTime extends TimeDataContainer
{
    public function hours(): int;

    public function minutes(): int;

    public function isSame(self $time): bool;

    public function isAfter(self $time): bool;

    public function isBefore(self $time): bool;

    public function isSameOrAfter(self $time): bool;

    public function diff(self $time): DateInterval;

    public function toDateTime(?DateTimeInterface $date = null): DateTimeInterface;

    public function format(string $format = self::TIME_FORMAT, DateTimeZone|string|null $timezone = null): string;
}
