<?php

declare(strict_types=1);

namespace Upp\Config;

final class Config
{
    public const DEFAULT_TARGET_DOMAIN = 'https://dinamic.hr';

    public function __construct(private readonly array $values)
    {
    }

    public static function load(string $root): self
    {
        $values = [];
        $example = $root . '/config.example.php';
        $local = $root . '/config.local.php';
        $dotEnv = self::loadDotEnv($root . '/.env');
        if (is_file($example)) {
            $loaded = require $example;
            $values = is_array($loaded) ? $loaded : [];
        }
        if (is_file($local)) {
            $loaded = require $local;
            if (is_array($loaded)) {
                $values = array_replace($values, $loaded);
            }
        }

        foreach ([
            // WOO_URL ostaje prijelazni alias; UPP_TARGET_DOMAIN ima prednost.
            'WOO_URL' => 'target_domain',
            'UPP_TARGET_DOMAIN' => 'target_domain',
            'WOO_CONSUMER_KEY' => 'woocommerce_consumer_key',
            'WOO_CONSUMER_SECRET' => 'woocommerce_consumer_secret',
            'WOO_VERIFY_SSL' => 'woocommerce_verify_ssl',
        ] as $environment => $key) {
            $value = getenv($environment);
            if (($value === false || $value === '') && isset($dotEnv[$environment])) {
                $value = $dotEnv[$environment];
            }
            if ($value !== false && $value !== '') {
                if ($environment === 'WOO_VERIFY_SSL') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        throw new \RuntimeException('WOO_VERIFY_SSL mora biti true ili false.');
                    }
                }
                $values[$key] = $value;
            }
        }

        $targetDomain = rtrim((string) ($values['target_domain'] ?? self::DEFAULT_TARGET_DOMAIN), '/');
        if (!preg_match('~^https://[^/]+(?::\d+)?$~i', $targetDomain)) {
            throw new \RuntimeException('UPP_TARGET_DOMAIN mora biti valjana HTTPS domena bez putanje.');
        }
        if (!defined('UPP_TARGET_DOMAIN')) {
            define('UPP_TARGET_DOMAIN', $targetDomain);
        }
        // Konstanta je autoritativna i za stari konfiguracijski ključ.
        $values['target_domain'] = UPP_TARGET_DOMAIN;
        $values['woocommerce_url'] = UPP_TARGET_DOMAIN;

        return new self($values);
    }

    /** @return array<string, string> */
    private static function loadDotEnv(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Nije moguće učitati .env konfiguraciju.');
        }

        $values = [];
        foreach ($lines as $lineNumber => $line) {
            $line = trim($lineNumber === 0 ? ltrim($line, "\xEF\xBB\xBF") : $line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new \RuntimeException(sprintf('Neispravan .env zapis u retku %d.', $lineNumber + 1));
            }

            $value = trim($matches[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if ($value[strlen($value) - 1] !== $quote) {
                    throw new \RuntimeException(sprintf('Nezatvoreni navodnik u .env retku %d.', $lineNumber + 1));
                }
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value);
                }
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            $values[$matches[1]] = $value;
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->values;
    }
}
