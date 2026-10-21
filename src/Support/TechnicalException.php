<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Throwable;

final class TechnicalException extends RuntimeException
{
    public function __construct(private readonly string $technicalCode, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : ErrorCatalog::get($technicalCode)['public'], 0, $previous);
    }
    public function technicalCode(): string { return $this->technicalCode; }
}
