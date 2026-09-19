<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AppSettingService
{
    private array $defaults = [
        'company_name' => 'Mon entreprise',
        'support_email' => '',
        'email_notifications_enabled' => '0',
        'manager_reminder_hours' => '24',
        'resolution_reminder_hours' => '24',
        'auto_close_enabled' => '0',
        'auto_close_hours' => '72',
        'daily_digest_enabled' => '0',
        'daily_digest_hour' => '8',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $values = $this->defaults;
        try {
            $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM app_settings');
            foreach ($stmt->fetchAll() as $row) {
                $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable) {
            // La migration v0.14 n'est peut-être pas encore appliquée.
        }
        return $values;
    }

    public function get(string $key, ?string $fallback = null): string
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return (string) $all[$key];
        }
        return $fallback ?? '';
    }

    public function bool(string $key, bool $fallback = false): bool
    {
        $value = strtolower($this->get($key, $fallback ? '1' : '0'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $fallback = 0): int
    {
        $value = filter_var($this->get($key, (string) $fallback), FILTER_VALIDATE_INT);
        return $value === false ? $fallback : (int) $value;
    }

    public function setMany(array $settings): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($settings as $key => $value) {
            $stmt->execute([
                'setting_key' => (string) $key,
                'setting_value' => (string) $value,
            ]);
        }
    }
}
