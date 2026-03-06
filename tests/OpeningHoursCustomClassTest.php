<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Exceptions\InvalidDateTimeClass;
use Cline\OpeningHours\OpeningHours;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class OpeningHoursCustomClassTest extends TestCase
{
    #[Test()]
    public function it_can_use_immutable_date_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'dateTimeClass' => DateTimeImmutable::class,
        ]);

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2021-10-11 04:30'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2021-10-11 09:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test()]
    public function it_can_use_timezones(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ]);

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 09:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 Europe/Oslo'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 09:00:00 Europe/Oslo', $date->format('Y-m-d H:i:s e'));

        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'timezone' => 'Europe/Oslo',
        ]);

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 06:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 07:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-08-01 07:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ], new DateTimeZone('Europe/Oslo'));
        $openingHours->setOutputTimezone('Europe/Oslo');

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 06:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 09:00:00 Europe/Oslo', $date->format('Y-m-d H:i:s e'));

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-08-01 09:00:00 Europe/Oslo', $date->format('Y-m-d H:i:s e'));

        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'timezone' => [
                'input' => 'Europe/Oslo',
                'output' => 'UTC',
            ],
        ]);

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 06:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 07:00:00 UTC', $date->format('Y-m-d H:i:s e'));

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-08-01 07:00:00 UTC', $date->format('Y-m-d H:i:s e'));
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
        ], 'Europe/Oslo', 'America/New_York');

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 06:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-07-25 03:00:00 America/New_York', $date->format('Y-m-d H:i:s e'));

        $date = $openingHours->nextOpen(
            CarbonImmutable::parse('2022-07-25 07:30 UTC'),
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2022-08-01 03:00:00 America/New_York', $date->format('Y-m-d H:i:s e'));
    }

    #[Test()]
    public function it_can_use_mocked_time(): void
    {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'dateTimeClass' => MockDateTimeAt0430::class,
        ]);

        $this->assertFalse($openingHours->isOpen());

        $openingHours = OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'dateTimeClass' => MockDateTimeAt0930::class,
        ]);

        $this->assertTrue($openingHours->isOpen());
    }

    #[Test()]
    public function it_should_refuse_invalid_date_time_class(): void
    {
        $this->expectException(InvalidDateTimeClass::class);
        OpeningHours::create([
            'monday' => ['09:00-18:00'],
            'dateTimeClass' => DateTimeZone::class,
        ]);
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class MockDateTimeAt0430 extends DateTimeImmutable
{
    public function __construct($datetime = 'now', ?DateTimeZone $timezone = null)
    {
        parent::__construct('2021-10-11 04:30', $timezone);
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class MockDateTimeAt0930 extends DateTimeImmutable
{
    public function __construct($datetime = 'now', ?DateTimeZone $timezone = null)
    {
        parent::__construct('2021-10-11 09:30', $timezone);
    }
}
