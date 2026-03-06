<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Carbon\CarbonImmutable;
use Cline\OpeningHours\Day;
use Cline\OpeningHours\Exceptions\InvalidOpeningHoursSpecification;
use Cline\OpeningHours\OpeningHours;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\TestCase;

use const JSON_ERROR_SYNTAX;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class OpeningHoursSpecificationParserTest extends TestCase
{
    public function test_create_from_structured_data(): void
    {
        $openingHoursSpecs = <<<'JSON'
            [
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "08:00",
                    "closes": "12:00",
                    "dayOfWeek": [
                        "https://schema.org/Monday",
                        "https://schema.org/Tuesday",
                        "https://schema.org/Wednesday",
                        "https://schema.org/Thursday",
                        "https://schema.org/Friday"
                    ]
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "14:00",
                    "closes": "18:00",
                    "dayOfWeek": [
                        "https://schema.org/Monday",
                        "https://schema.org/Tuesday",
                        "https://schema.org/Wednesday",
                        "https://schema.org/Thursday",
                        "https://schema.org/Friday"
                    ]
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "08:00:00",
                    "closes": "12:00:00",
                    "dayOfWeek": "https://schema.org/Saturday"
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "00:00",
                    "closes": "00:00",
                    "dayOfWeek": [
                        "Sunday"
                    ]
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "00:00",
                    "closes": "00:00",
                    "validFrom": "2023-12-25",
                    "validThrough": "2023-12-25"
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "opens": "09:00",
                    "closes": "18:00",
                    "validFrom": "2023-12-24",
                    "validThrough": "2023-12-24"
                }
            ]
            JSON;

        $openingHours = OpeningHours::createFromStructuredData($openingHoursSpecs);
        $this->assertInstanceOf(OpeningHours::class, $openingHours);

        $this->assertCount(2, $openingHours->forDay('monday'));
        $this->assertCount(2, $openingHours->forDay('tuesday'));
        $this->assertCount(2, $openingHours->forDay('wednesday'));
        $this->assertCount(2, $openingHours->forDay('thursday'));
        $this->assertCount(2, $openingHours->forDay('friday'));
        $this->assertCount(1, $openingHours->forDay('saturday'));
        $this->assertCount(0, $openingHours->forDay('sunday'));

        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-20 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-21 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-22 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-23 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-24 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-25 08:00'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            Date::parse('2023-11-25 11:59'),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            Date::parse('2023-11-25 13:00'),
        ));
        $this->assertFalse($openingHours->isOpenAt(
            Date::parse('2023-11-26 08:00'),
        ));

        // Exception Closed on Christmas day
        $this->assertTrue(
            $openingHours->isClosedAt(
                Date::parse('2023-12-25 08:00'),
            ),
            'Closed on 2023 Monday Christmas day',
        );
        // Exception Opened on Christmas Eve
        $this->assertTrue(
            $openingHours->isOpenAt(
                Date::parse('2023-12-24 10:00'),
            ),
            'Opened on 2023 Sunday before Christmas day',
        );
    }

    public function test_is_always_closed_with_undefined_opening_hours_and_undefined_exceptions(): void
    {
        $openingHours = OpeningHours::createFromStructuredData([]);

        $this->assertTrue($openingHours->isAlwaysClosed());
    }

    public function test_is_always_closed_with_empty_opening_hours_and_empty_exceptions(): void
    {
        $openingHours = OpeningHours::create([
            'Monday' => [],
            'Tuesday' => [],
            'Wednesday' => [],
            'Thursday' => [],
            'Friday' => [],
            'Saturday' => [],
            'Sunday' => [],
            'exceptions' => [
                '2023-12-24' => [],
            ],
        ]);

        $this->assertTrue($openingHours->isAlwaysClosed());
    }

    public function test_is_always_open_with_undefined_opening_hours_and_undefined_exceptions(): void
    {
        $openingHours = OpeningHours::createFromStructuredData([]);

        $this->assertFalse($openingHours->isAlwaysOpen());
    }

    public function test_is_always_open_with_filled_opening_hours_and_filled_exceptions(): void
    {
        $openingHours = OpeningHours::create([
            'Monday' => ['00:00-24:00'],
            'Tuesday' => ['00:00-24:00'],
            'Wednesday' => ['00:00-24:00'],
            'Thursday' => ['00:00-24:00'],
            'Friday' => ['00:00-24:00'],
            'Saturday' => ['00:00-24:00'],
            'Sunday' => ['00:00-24:00'],
            'exceptions' => [
                '2023-12-24' => ['00:00-24:00'],
            ],
        ]);

        $this->assertTrue($openingHours->isAlwaysOpen());
    }

    public function test_is_always_open_with_filled_opening_hours_and_undefined_exceptions(): void
    {
        $openingHours = OpeningHours::create([
            'Monday' => ['00:00-24:00'],
            'Tuesday' => ['00:00-24:00'],
            'Wednesday' => ['00:00-24:00'],
            'Thursday' => ['00:00-24:00'],
            'Friday' => ['00:00-24:00'],
            'Saturday' => ['00:00-24:00'],
            'Sunday' => ['00:00-24:00'],
        ]);

        $this->assertTrue($openingHours->isAlwaysOpen());
    }

    public function test_range_over_night(): void
    {
        $openingHours = OpeningHours::createFromStructuredData([
            [
                'dayOfWeek' => 'Monday',
                'opens' => '18:00',
                'closes' => '02:00',
            ],
        ]);

        $this->assertTrue($openingHours->isClosedAt(
            CarbonImmutable::parse('2023-11-27 17:50'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            CarbonImmutable::parse('2023-11-27 23:55'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            CarbonImmutable::parse('2023-11-27 23:59:59.99'),
        ));
        $this->assertTrue($openingHours->isOpenAt(
            CarbonImmutable::parse('2023-11-28 01:50'),
        ));
        $this->assertTrue($openingHours->isClosedAt(
            CarbonImmutable::parse('2023-11-28 19:00'),
        ));
    }

    public function test_h24_specs(): void
    {
        $openingHours = OpeningHours::createFromStructuredData([
            [
                'opens' => '00:00',
                'closes' => '23:59',
                'dayOfWeek' => [
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                ],
            ],
        ]);

        $this->assertTrue(
            $openingHours->isOpenAt(
                CarbonImmutable::parse('2023-11-27 23:59:34'),
            ),
            'As per specs, 23:59 is assumed to mean until end of day',
        );
        $this->assertFalse(
            $openingHours->isOpenAt(
                CarbonImmutable::parse('2023-11-25 23:59:34'),
            ),
            'Saturday and Sunday not specified means they are closed',
        );
        $this->assertFalse(
            $openingHours->isAlwaysOpen(),
            'Saturday and Sunday not specified means they are closed',
        );

        $openingHours = OpeningHours::createFromStructuredData([
            [
                'opens' => '00:00',
                'closes' => '23:59',
                'dayOfWeek' => [
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                    'Saturday',
                    'Sunday',
                ],
            ],
        ]);

        $this->assertTrue(
            $openingHours->isAlwaysOpen(),
            'As per specs, 23:59 is assumed to mean until end of day',
        );
    }

    public function test_closed_day(): void
    {
        $openingHours = OpeningHours::createFromStructuredData([
            ['dayOfWeek' => 'Monday'],
        ]);

        $this->assertSame('', (string) $openingHours->forDay(Day::MONDAY));
    }

    public function test_create_from_previous_export(): void
    {
        $timetable = OpeningHours::create([
            'monday' => ['09:00-12:00', '13:00-18:00'],
            'tuesday' => ['09:00-12:00', '13:00-18:00'],
            'wednesday' => ['09:00-12:00'],
            'thursday' => ['09:00-12:00', '13:00-18:00'],
            'friday' => ['09:00-12:00', '13:00-20:00'],
            'saturday' => ['09:00-12:00', '13:00-16:00'],
            'sunday' => [],
            'exceptions' => [
                '2016-11-11' => ['09:00-12:00'],
                '2016-12-25' => [],
                '01-01' => [],                // Recurring on each 1st of January
                '12-25' => ['09:00-12:00'],   // Recurring on each 25th of December
            ],
        ]);

        $array = $timetable->asStructuredData();
        $this->assertSame([
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Monday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Monday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Tuesday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Tuesday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Wednesday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Thursday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Thursday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Friday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Friday',
                'opens' => '13:00',
                'closes' => '20:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens' => '13:00',
                'closes' => '16:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '09:00',
                'closes' => '12:00',
                'validFrom' => '2016-11-11',
                'validThrough' => '2016-11-11',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '00:00',
                'closes' => '00:00',
                'validFrom' => '2016-12-25',
                'validThrough' => '2016-12-25',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '00:00',
                'closes' => '00:00',
                'validFrom' => '01-01',
                'validThrough' => '01-01',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '09:00',
                'closes' => '12:00',
                'validFrom' => '12-25',
                'validThrough' => '12-25',
            ],
        ], $array);

        $newOpeningHours = OpeningHours::createFromStructuredData($array);

        $this->assertSame([
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Monday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Monday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Tuesday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Tuesday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Wednesday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Thursday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Thursday',
                'opens' => '13:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Friday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Friday',
                'opens' => '13:00',
                'closes' => '20:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens' => '09:00',
                'closes' => '12:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens' => '13:00',
                'closes' => '16:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '09:00',
                'closes' => '12:00',
                'validFrom' => '2016-11-11',
                'validThrough' => '2016-11-11',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '00:00',
                'closes' => '00:00',
                'validFrom' => '2016-12-25',
                'validThrough' => '2016-12-25',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '00:00',
                'closes' => '00:00',
                'validFrom' => '01-01',
                'validThrough' => '01-01',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'opens' => '09:00',
                'closes' => '12:00',
                'validFrom' => '12-25',
                'validThrough' => '12-25',
            ],
        ], $newOpeningHours->asStructuredData());
    }

    public function test_invalid_json(): void
    {
        $this->expectException(InvalidOpeningHoursSpecification::class);
        $this->expectExceptionMessage('Invalid https://schema.org/OpeningHoursSpecification JSON');
        $this->expectExceptionCode(JSON_ERROR_SYNTAX);

        OpeningHours::createFromStructuredData('{');
    }

    public function test_invalid_day_of_week(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 1: Property dayOfWeek must be a string or an array of strings',
            ),
        );

        OpeningHours::createFromStructuredData([
            ['dayOfWeek' => []],
            ['dayOfWeek' => true],
        ]);
    }

    public function test_invalid_day_type(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid https://schema.org/OpeningHoursSpecification dayOfWeek',
            ),
        );

        OpeningHours::createFromStructuredData([
            ['dayOfWeek' => [true]],
        ]);
    }

    public function test_invalid_day_name(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid https://schema.org Day specification',
            ),
        );

        OpeningHours::createFromStructuredData([
            ['dayOfWeek' => ['Wedmonday']],
        ]);
    }

    public function test_unsupported_public_holidays(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: PublicHolidays not supported',
            ),
        );

        OpeningHours::createFromStructuredData([
            ['dayOfWeek' => 'PublicHolidays'],
        ]);
    }

    public function test_invalid_valid_pair(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Contains neither dayOfWeek nor validFrom and validThrough dates',
            ),
        );

        OpeningHours::createFromStructuredData([
            ['validFrom' => '2023-11-25'],
        ]);
    }

    public function test_invalid_opens(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid opens hour',
            ),
        );

        OpeningHours::createFromStructuredData([
            [
                'dayOfWeek' => 'Monday',
                'opens' => 'noon',
                'closes' => '14:00',
            ],
        ]);
    }

    public function test_invalid_closes(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid closes hour',
            ),
        );

        OpeningHours::createFromStructuredData([
            [
                'dayOfWeek' => 'Monday',
                'opens' => '10:00',
                'closes' => 'noon',
            ],
        ]);
    }

    public function test_closes_only(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Property opens and closes must be both null or both string',
            ),
        );

        OpeningHours::createFromStructuredData([
            [
                'dayOfWeek' => 'Monday',
                'closes' => '10:00',
            ],
        ]);
    }

    public function test_invalid_valid_from(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid validFrom date',
            ),
        );

        OpeningHours::createFromStructuredData([
            [
                'validFrom' => '11/11/2023',
                'validThrough' => '2023-11-25',
            ],
        ]);
    }

    public function test_invalid_valid_through(): void
    {
        self::expectExceptionObject(
            new InvalidOpeningHoursSpecification(
                'Invalid openingHoursSpecification item at index 0: Invalid validThrough date',
            ),
        );

        OpeningHours::createFromStructuredData([
            [
                'validFrom' => '2023-11-11',
                'validThrough' => '25/11/20235',
            ],
        ]);
    }
}
