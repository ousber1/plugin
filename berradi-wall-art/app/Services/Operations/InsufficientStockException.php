<?php

declare(strict_types=1);

namespace App\Services\Operations;

final class InsufficientStockException extends \RuntimeException
{
    public function __construct(public readonly string $material, public readonly string $available)
    {
        parent::__construct(__('inventory.insufficient', ['material' => $material, 'available' => $available]));
    }
}
