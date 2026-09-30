<?php

declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "[ERREUR] config/config.php introuvable.\n");
    exit(1);
}

$config = require $configFile;
$pdo = (new Database($config['database'] ?? []))->pdo();

$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() === 1;
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int)$stmt->fetchColumn() === 1;
};

$indexExists = static function (PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :idx'
    );
    $stmt->execute(['table' => $table, 'idx' => $index]);
    return (int)$stmt->fetchColumn() > 0;
};

$constraintExists = static function (PDO $pdo, string $table, string $constraint): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.table_constraints
         WHERE constraint_schema = DATABASE() AND table_name = :table AND constraint_name = :constraint'
    );
    $stmt->execute(['table' => $table, 'constraint' => $constraint]);
    return (int)$stmt->fetchColumn() > 0;
};

try {
    if (!$tableExists($pdo, 'user_preferences')) {
        throw new RuntimeException('Table user_preferences introuvable. Exécutez d’abord les migrations des versions précédentes.');
    }
    if (!$tableExists($pdo, 'manager_approvals')) {
        throw new RuntimeException('Table manager_approvals introuvable. Exécutez d’abord les migrations des versions précédentes.');
    }

    // Préférences utilisateur : thème clair par défaut et langue FR/EN.
    $pdo->exec("ALTER TABLE user_preferences MODIFY theme ENUM('light','dark','system') NOT NULL DEFAULT 'light'");
    if (!$columnExists($pdo, 'user_preferences', 'language')) {
        $pdo->exec("ALTER TABLE user_preferences ADD COLUMN language ENUM('fr','en') NOT NULL DEFAULT 'fr' AFTER sidebar_mode");
        echo "[OK] Colonne user_preferences.language ajoutée.\n";
    } else {
        echo "[OK] Colonne user_preferences.language déjà présente.\n";
    }

    if (!$tableExists($pdo, 'app_settings')) {
        throw new RuntimeException('Table app_settings introuvable.');
    }
    $pdo->exec(
        "INSERT INTO app_settings (setting_key, setting_value)
         VALUES ('default_language', 'fr')
         ON DUPLICATE KEY UPDATE setting_value = setting_value"
    );
    echo "[OK] Paramètre default_language prêt.\n";

    // Validation Manager à deux niveaux. Les validations historiques restent
    // des validations finales afin de conserver le comportement v1.0/v1.1.
    $stageWasAdded = false;
    if (!$columnExists($pdo, 'manager_approvals', 'stage')) {
        $pdo->exec("ALTER TABLE manager_approvals ADD COLUMN stage ENUM('n1','target') NOT NULL DEFAULT 'n1' AFTER requested_by");
        $stageWasAdded = true;
    }
    if (!$columnExists($pdo, 'manager_approvals', 'target_manager_id')) {
        $pdo->exec('ALTER TABLE manager_approvals ADD COLUMN target_manager_id INT UNSIGNED NULL AFTER stage');
    }
    if (!$columnExists($pdo, 'manager_approvals', 'parent_approval_id')) {
        $pdo->exec('ALTER TABLE manager_approvals ADD COLUMN parent_approval_id BIGINT UNSIGNED NULL AFTER target_manager_id');
    }
    if ($stageWasAdded) {
        $pdo->exec("UPDATE manager_approvals SET stage='target'");
        echo "[OK] Validations Manager historiques conservées comme validations finales.\n";
    }

    if (!$indexExists($pdo, 'manager_approvals', 'idx_manager_approvals_ticket_status')) {
        $pdo->exec('ALTER TABLE manager_approvals ADD INDEX idx_manager_approvals_ticket_status (ticket_id, status)');
    }
    if (!$indexExists($pdo, 'manager_approvals', 'idx_manager_approvals_target')) {
        $pdo->exec('ALTER TABLE manager_approvals ADD INDEX idx_manager_approvals_target (target_manager_id)');
    }
    if (!$constraintExists($pdo, 'manager_approvals', 'fk_approvals_target_manager')) {
        $pdo->exec(
            'ALTER TABLE manager_approvals
             ADD CONSTRAINT fk_approvals_target_manager FOREIGN KEY (target_manager_id) REFERENCES users(id)'
        );
    }
    if (!$constraintExists($pdo, 'manager_approvals', 'fk_approvals_parent')) {
        $pdo->exec(
            'ALTER TABLE manager_approvals
             ADD CONSTRAINT fk_approvals_parent FOREIGN KEY (parent_approval_id) REFERENCES manager_approvals(id) ON DELETE SET NULL'
        );
    }

    echo "[OK] Migration TicketFlow v1.2.0 appliquée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERREUR] Migration v1.2.0 impossible : ' . $e->getMessage() . "\n");
    exit(1);
}
