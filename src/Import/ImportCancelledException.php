<?php

declare(strict_types=1);

namespace Upp\Import;

use RuntimeException;

final class ImportCancelledException extends RuntimeException
{
    public function __construct(public readonly int $processed)
    {
        parent::__construct('Import je zaustavljen na zahtjev korisnika.');
    }
}
