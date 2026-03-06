<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Value;

use Cline\OpeningHours\Exceptions\InvalidDayName;
use DateTimeInterface;
use ValueError;

use function array_search;
use function mb_strtolower;

/**
 * Day-of-week enum used throughout weekly schedules and overrides.
 *
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

    /**
     * Resolve the day of week for a calendar date or date-time.
     *
     * @throws InvalidDayName When the formatted day name cannot be resolved.
     */
    public static function onDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromName($dateTime->format('l'));
    }

    /**
     * Create a day enum from an English day name, case-insensitively.
     *
     * @throws InvalidDayName When the provided name is not a valid English weekday.
     */
    public static function fromName(string $day): self
    {
        try {
            return self::from(mb_strtolower($day));
        } catch (ValueError $valueError) {
            throw InvalidDayName::invalidDayName($day, $valueError);
        }
    }

    /**
     * Convert the enum to its ISO-8601 day number, where Monday is 1 and Sunday is 7.
     */
    public function toISO(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
