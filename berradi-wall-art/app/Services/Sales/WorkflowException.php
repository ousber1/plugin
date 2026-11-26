<?php

declare(strict_types=1);

namespace App\Services\Sales;

/** A business rule refused the requested change (message is user-facing). */
final class WorkflowException extends \RuntimeException
{
}
