<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Location: ' . ($auth->check() ? 'dashboard.php' : 'login.php'));
exit;
