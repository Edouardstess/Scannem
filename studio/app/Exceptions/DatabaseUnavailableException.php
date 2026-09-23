<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The database could not be reached.
 *
 * Distinct from a generic failure so the kernel can answer with a page that
 * says what to check, rather than a blank 500 that says nothing. The driver's
 * own message is never carried into it: it routinely contains the connection
 * credentials.
 */
final class DatabaseUnavailableException extends RuntimeException
{
}
