<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

use Cline\OpeningHours\Exceptions\InvalidDayName;
use DateTimeInterface;
use ValueError;

use function array_search;
use function mb_strtolower;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum Day: string
{
    case MONDAY = 'monday';
    case TUESDAY = 'tuesday';
    case WEDNESDAY = 'wednesday';
    case THURSDAY = 'thursday';
    case FRIDAY = 'friday';
    case SATURDAY = 'saturday';
    case SUNDAY = 'sunday';

    public static function onDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromName($dateTime->format('l'));
    }

    public static function fromName(string $day): self
    {
        try {
            return self::from(mb_strtolower($day));
        } catch (ValueError $valueError) {
            throw InvalidDayName::invalidDayName($day, $valueError);
        }
    }

    public function toISO(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
