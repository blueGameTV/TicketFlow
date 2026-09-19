<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
$result = $mailQueueService->process(100);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
