<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Custom exception to mark a message handler error as recoverable,
 * signaling to Messenger that it should be retried.
 */
class RecoverableMessageException extends \RuntimeException
{
}
