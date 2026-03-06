<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use DateTime;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomDate extends DateTime
{
    public function foo(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
}
