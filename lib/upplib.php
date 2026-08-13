<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/** @deprecated Koristite Upp\Import\JsonFileReader i JsonValidator. */
function validate_json_file(string $file): array
{
    $records = (new Upp\Import\JsonFileReader())->read($file);
    (new Upp\Import\JsonValidator())->validate($records);
    return $records;
}

/** @deprecated Kompatibilni read-only alias. */
function parse_json(string $file): array
{
    return validate_json_file($file);
}
