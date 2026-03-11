<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\OpeningHours\Exceptions;

/**
 * Base runtime exception for package-specific failures outside input validation.
 *
 * Most errors in this package are raised as more specific invalid-definition or
 * invalid-structured-data exceptions. This concrete base remains available for
 * runtime failures that still belong to the package's public exception
 * hierarchy.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Exception extends \Exception implements OpeningHoursException {}
