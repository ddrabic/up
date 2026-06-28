<?php

function upp_config($key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $exampleFile = __DIR__ . '/../config.example.php';
        $localFile = __DIR__ . '/../config.local.php';

        $config = file_exists($exampleFile) ? require $exampleFile : [];
        if (file_exists($localFile)) {
            $localConfig = require $localFile;
            if (is_array($localConfig)) {
                $config = array_merge($config, $localConfig);
            }
        }

        $envMap = [
            'WOO_URL' => 'woocommerce_url',
            'WOO_CONSUMER_KEY' => 'woocommerce_consumer_key',
            'WOO_CONSUMER_SECRET' => 'woocommerce_consumer_secret',
        ];

        foreach ($envMap as $envName => $configKey) {
            $value = getenv($envName);
            if ($value !== false && $value !== '') {
                $config[$configKey] = $value;
            }
        }
    }

    if ($key === null) {
        return $config;
    }

    return array_key_exists($key, $config) ? $config[$key] : $default;
}
