<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\OpeningHours\Helpers\Arr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_int;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ArrTest extends TestCase
{
    #[Test()]
    public function it_can_flat_and_map_array(): void
    {
        $this->assertSame([-1, 2, [3, 4], -5, 6], Arr::flatMap([1, [2, [3, 4]], 5, [6]], fn ($value) => is_int($value) ? -$value : $value));
    }
}
