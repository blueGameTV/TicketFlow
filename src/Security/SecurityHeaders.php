<?php

declare(strict_types=1);

namespace App\Security;

final class SecurityHeaders
{
    public static function apply(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');

        // TicketFlow utilise encore quelques scripts/styles inline et Font Awesome via cdnjs.
        // Cette politique reste compatible avec l'interface actuelle tout en bloquant les
        // origines non prévues. Elle pourra être durcie avant la v1.0 en supprimant l'inline.
        header(
            "Content-Security-Policy: default-src 'self'; " .
            "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; " .
            "img-src 'self' data: blob:; " .
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
            "font-src 'self' data: https://cdnjs.cloudflare.com; " .
            "script-src 'self' 'unsafe-inline'; " .
            "connect-src 'self'"
        );
    }
}
