<?php

declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function __construct(public readonly array $errors, public readonly array $old = [])
    {
        parent::__construct('The given data was invalid.');
    }
}
