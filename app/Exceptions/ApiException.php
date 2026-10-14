<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule error with its own code, e.g. PROJECT_INACTIVE or
 * INVALID_STATUS_TRANSITION. Rendered as { code, message } (PRD §8).
 *
 *   throw new ApiException(409, 'MOM_NOT_EDITABLE', 'MoM berstatus FINAL tidak dapat diubah');
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
