<?php
return [
    'app' => [
        'name' => 'TicketFlow',
        'base_url' => 'http://localhost',
        'environment' => 'development',
    ],

    'mail' => [
        // disabled | log | smtp
        // "log" écrit les e-mails dans storage/logs/mail.log pour les tests.
        'transport' => 'log',
        'host' => '127.0.0.1',
        'port' => 1025,
        // none | tls | ssl
        'encryption' => 'none',
        'username' => '',
        'password' => '',
        'from_email' => 'ticketflow@example.local',
        'from_name' => 'TicketFlow',
        'timeout' => 15,
    ],
    'uploads' => [
        // 10 Mo par fichier, 5 fichiers maximum par envoi.
        'max_file_size' => 10 * 1024 * 1024,
        'max_files_per_upload' => 5,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ticketflow',
        'user' => 'ticketflow_user',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
];
