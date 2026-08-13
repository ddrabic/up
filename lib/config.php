<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Upp\Config\Config;

function upp_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    $config ??= Config::load(dirname(__DIR__))->all();
    if ($key === null) {
        return $config;
    }
    return array_key_exists($key, $config) ? $config[$key] : $default;
}
