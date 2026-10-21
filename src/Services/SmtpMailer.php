<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpMailer
{
    public function __construct(private array $config)
    {
    }

    public function send(string $toEmail, string $toName, string $subject, string $html): void
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 587);
        $encryption = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');
        $fromEmail = (string) ($this->config['from_email'] ?? '');
        $fromName = (string) ($this->config['from_name'] ?? 'TicketFlow');
        $timeout = max(3, (int) ($this->config['timeout'] ?? 15));

        if ($host === '' || $fromEmail === '') {
            throw new RuntimeException('Configuration SMTP incomplète.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('Connexion SMTP impossible : ' . $errstr . ' (' . $errno . ').');
        }
        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);
            $hostname = gethostname() ?: 'ticketflow.local';
            $this->command($socket, 'EHLO ' . $hostname, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Impossible d’activer TLS pour SMTP.');
                }
                $this->command($socket, 'EHLO ' . $hostname, [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
                'To: ' . $this->encodeHeader($toName !== '' ? $toName : $toEmail) . ' <' . $toEmail . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'X-Mailer: TicketFlow',
            ];
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $html;
            $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;
            fwrite($socket, $payload . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 4096);
            if ($line === false) {
                throw new RuntimeException('Réponse SMTP invalide ou connexion interrompue.');
            }
            $response .= $line;
            $code = (int) substr($line, 0, 3);
            $continue = isset($line[3]) && $line[3] === '-';
        } while ($continue);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Erreur SMTP ' . $code . ' : ' . trim($response));
        }
        return $response;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
