<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours;

/**
 * @author Brian Faust <brian@cline.sh>
 */
interface TimeDataContainer
{
    public const TIME_FORMAT = 'H:i';

    public const MIDNIGHT = '00:00'; // Midnight represented in the TIME_FORMAT

    public function __toString(): string;

    public static function fromString(string $string): self;
}
