<?php

declare(strict_types=1);

/**
 * @deprecated Aktivni import koristi klase iz src/. Datoteka je zadržana samo
 * radi jasne pogreške starim integracijama koje su instancirale wcImport.
 */
namespace wc;

final class wcImport
{
    public function __construct(...$unused)
    {
        throw new \RuntimeException('wcImport Legacy omotač više nije podržan; koristite Upp\\Import\\ImportService.');
    }
}
