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
 * Immutable weekly schedule keyed by lowercase English day names.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WeeklySchedule
{
    /**
     * @param array<string, DaySchedule> $days
     */
    private function __construct(
        private array $days,
    ) {}

    /**
     * Build a complete weekly schedule, defaulting omitted days to closed.
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
     * Get the schedule for a specific day of the week.
     */
    public function forDay(Day $day): DaySchedule
    {
        return $this->days[$day->value];
    }

    /**
     * Get all day schedules keyed by lowercase English day names.
     *
     * @return array<string, DaySchedule>
     */
    public function days(): array
    {
        return $this->days;
    }
}
