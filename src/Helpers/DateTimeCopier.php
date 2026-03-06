<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Helpers;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait DateTimeCopier
{
    protected function copyDateTime(DateTimeInterface $date): DateTimeInterface
    {
        return $date instanceof DateTimeImmutable ? $date : clone $date;
    }

    protected function copyAndModify(DateTimeInterface $date, string $modifier): DateTimeInterface
    {
        return $this->copyDateTime($date)->modify($modifier);
    }

    protected function yesterday(DateTimeInterface $date): DateTimeInterface
    {
        return $this->copyAndModify($date, '-1 day');
    }
}
