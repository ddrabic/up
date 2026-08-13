<?php

declare(strict_types=1);

namespace Upp\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Upp\Config\Config;

final class TargetDomainTest extends TestCase
{
    public function testProductionDomainIsTheDefaultConstant(): void
    {
        self::assertSame('https://dinamic.hr', Config::DEFAULT_TARGET_DOMAIN);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDockerDomainOverridesTheTargetConstant(): void
    {
        putenv('UPP_TARGET_DOMAIN=https://dinamic.loc');
        $root = sys_get_temp_dir() . '/upp-config-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        file_put_contents($root . '/config.example.php', '<?php return [];');
        try {
            $config = Config::load($root);
            self::assertTrue(defined('UPP_TARGET_DOMAIN'));
            self::assertSame('https://dinamic.loc', UPP_TARGET_DOMAIN);
            self::assertSame(UPP_TARGET_DOMAIN, $config->get('target_domain'));
            self::assertSame(UPP_TARGET_DOMAIN, $config->get('woocommerce_url'));
        } finally {
            unlink($root . '/config.example.php');
            rmdir($root);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDotEnvProvidesWooCommerceConfiguration(): void
    {
        putenv('UPP_TARGET_DOMAIN');
        putenv('WOO_CONSUMER_KEY');
        putenv('WOO_CONSUMER_SECRET');
        $root = sys_get_temp_dir() . '/upp-config-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        file_put_contents($root . '/config.example.php', '<?php return [];');
        file_put_contents($root . '/.env', implode("\n", [
            '# Local WordPress (Docker test)',
            'UPP_TARGET_DOMAIN="https://wordpress.test"',
            'WOO_CONSUMER_KEY="ck_from_dotenv"',
            'WOO_CONSUMER_SECRET="cs_from_dotenv"',
            'WOO_VERIFY_SSL=false',
        ]));

        try {
            $config = Config::load($root);
            self::assertSame('https://wordpress.test', $config->get('target_domain'));
            self::assertSame('ck_from_dotenv', $config->get('woocommerce_consumer_key'));
            self::assertSame('cs_from_dotenv', $config->get('woocommerce_consumer_secret'));
            self::assertFalse($config->get('woocommerce_verify_ssl'));
        } finally {
            unlink($root . '/.env');
            unlink($root . '/config.example.php');
            rmdir($root);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProcessEnvironmentOverridesDotEnv(): void
    {
        putenv('UPP_TARGET_DOMAIN=https://process.test');
        $root = sys_get_temp_dir() . '/upp-config-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        file_put_contents($root . '/config.example.php', '<?php return [];');
        file_put_contents($root . '/.env', 'UPP_TARGET_DOMAIN=https://dotenv.test');

        try {
            $config = Config::load($root);
            self::assertSame('https://process.test', $config->get('target_domain'));
        } finally {
            unlink($root . '/.env');
            unlink($root . '/config.example.php');
            rmdir($root);
        }
    }
}
