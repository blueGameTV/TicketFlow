<?php

declare(strict_types=1);

namespace App\Support;

final class Runtime
{
    public static function configure(array $config): void
    {
        error_reporting(E_ALL);

        $environment = strtolower((string) ($config['app']['environment'] ?? 'production'));
        ini_set('display_errors', $environment === 'development' ? '1' : '0');
        ini_set('display_startup_errors', $environment === 'development' ? '1' : '0');
        ini_set('log_errors', '1');

        $root = dirname(__DIR__, 2);
        $logDir = $root . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0770, true);
        }
        if (is_dir($logDir) && is_writable($logDir)) {
            ini_set('error_log', $logDir . '/php-error.log');
        }
    }
}
