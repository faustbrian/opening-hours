<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Helpers;

use Deprecated;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait DataTrait
{
    #[Deprecated(message: 'Use ->data readonly property instead')]
    public function getData(): mixed
    {
        return $this->data;
    }
}
