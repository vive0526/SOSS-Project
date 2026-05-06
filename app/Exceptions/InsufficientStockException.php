<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public string $productId;

    public function __construct(string $productId, string $message)
    {
        parent::__construct($message);
        $this->productId = $productId;
    }
}

