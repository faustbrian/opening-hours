<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Schedule;

use Cline\OpeningHours\Value\Day;

/**
 * Immutable baseline schedule for a seven-day week.
 *
 * This type holds the package's fallback schedule before any date-based override rules
 * are applied. Every {@see Day} is always present in the stored map, which lets the rest
 * of the package treat missing day definitions as explicit closures rather than dealing
 * with optional lookups.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WeeklySchedule
{
    /**
     * @param array<string, DaySchedule> $days Complete weekday map keyed by lowercase English day name.
     */
    private function __construct(
        private array $days,
    ) {}

    /**
     * Build a complete weekly schedule, defaulting omitted days to closed.
     *
     * Callers may pass only the days they explicitly define. Any omitted weekday is
     * normalized to {@see DaySchedule::closed()} so the internal representation is always
     * complete and deterministic.
     *
     * @param array<string, DaySchedule> $days
     */
    public static function fromDaySchedules(array $days): self
    {
        $resolved = [];

        foreach (Day::cases() as $day) {
            $resolved[$day->value] = $days[$day->value] ?? DaySchedule::closed();
        }

        return new self($resolved);
    }

    /**
     * Return the schedule for one specific day of the week.
     */
    public function forDay(Day $day): DaySchedule
    {
        return $this->days[$day->value];
    }

    /**
     * Return all day schedules keyed by lowercase English day names.
     *
     * @return array<string, DaySchedule>
     */
    public function days(): array
    {
        return $this->days;
    }
}
