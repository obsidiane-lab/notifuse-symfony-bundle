<?php

namespace Obsidiane\Notifuse\Exception;

use Throwable;

final class NotifuseClientException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
