<?php

namespace App\Core\Domain\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class DomainException extends HttpException
{
    public function __construct(
        public readonly DomainError $error,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($this->error->status()->value, $this->error->message(), $previous);
    }
}
