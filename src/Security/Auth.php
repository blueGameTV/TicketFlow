<?php

declare(strict_types=1);

namespace App\Security;

use App\Services\AuditService;
use PDO;

final class Auth
{
    private const SESSION_KEY = 'auth_user';
    private const IDLE_TIMEOUT = 1800; // 30 minutes
    private const MAX_FAILURES = 5;
    private const FAILURE_WINDOW_MINUTES = 15;
    private const LOCK_MINUTES = 15;

    private ?string $lastError = null;

    public function __construct(private PDO $pdo, private AuditService $audit)
    {
    }

    public function attempt(string $identifier, string $password): bool
    {
        $this->lastError = null;
        $identifier = mb_strtolower(trim($identifier));
        $ip = $this->audit->clientIp();

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.profile_photo, u.password_hash, u.active,
                    u.must_change_password, u.locked_until,
                    r.id AS role_id, r.name AS role_name,
                    u.group_id, g.name AS group_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN groups_company g ON g.id = u.group_id
             WHERE LOWER(u.email) = :identifier_email OR LOWER(u.username) = :identifier_username
             LIMIT 1'
        );
        $stmt->execute(['identifier_email' => $identifier, 'identifier_username' => $identifier]);
        $user = $stmt->fetch();

        if ($user && !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $this->recordAttempt($identifier, (int) $user['id'], $ip, false);
            $this->lastError = 'Compte temporairement verrouillé après plusieurs tentatives. Réessayez plus tard.';
            $this->audit->log((int) $user['id'], 'login_blocked', 'user', (int) $user['id']);
            return false;
        }

        $valid = $user && (bool) $user['active'] && password_verify($password, (string) $user['password_hash']);
        $this->recordAttempt($identifier, $user ? (int) $user['id'] : null, $ip, $valid);

        if (!$valid) {
            if ($user) {
                $failures = $this->recentFailures($identifier, $ip);
                if ($failures >= self::MAX_FAILURES) {
                    $lock = $this->pdo->prepare('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = :id');
                    $lock->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
                    $lock->execute();
                    $this->lastError = 'Trop de tentatives. Le compte est verrouillé temporairement.';
                }
            }
            $this->audit->log($user ? (int) $user['id'] : null, 'login_failed', 'user', $user['id'] ?? null, ['identifier' => $identifier]);
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }

        $this->pdo->prepare('UPDATE users SET last_login_at = NOW(), locked_until = NULL WHERE id = :id')->execute(['id' => $user['id']]);
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id' => (int) $user['id'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'username' => $user['username'],
            'email' => $user['email'],
            'profile_photo' => $user['profile_photo'],
            'role_id' => (int) $user['role_id'],
            'role' => $user['role_name'],
            'group_id' => $user['group_id'] !== null ? (int) $user['group_id'] : null,
            'group' => $user['group_name'],
            'must_change_password' => (bool) $user['must_change_password'],
            'last_activity' => time(),
        ];

        $this->registerSession((int) $user['id']);
        $this->audit->log((int) $user['id'], 'login_success', 'user', (int) $user['id']);
        return true;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function check(): bool
    {
        if (!isset($_SESSION[self::SESSION_KEY]['id'])) {
            return false;
        }

        $last = (int) ($_SESSION[self::SESSION_KEY]['last_activity'] ?? time());
        if (time() - $last > self::IDLE_TIMEOUT) {
            $this->logout('session_timeout');
            return false;
        }

        if (!$this->sessionIsValid()) {
            $this->logout('session_revoked');
            return false;
        }

        $_SESSION[self::SESSION_KEY]['last_activity'] = time();
        $this->touchSession();
        return true;
    }

    public function user(): ?array { return $this->check() ? $_SESSION[self::SESSION_KEY] : null; }
    public function id(): ?int { return $this->check() ? (int) $_SESSION[self::SESSION_KEY]['id'] : null; }
    public function role(): ?string { return $this->check() ? (string) $_SESSION[self::SESSION_KEY]['role'] : null; }
    public function hasRole(string ...$roles): bool { return $this->check() && in_array($this->role(), $roles, true); }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            header('Location: login.php');
            exit;
        }
        if (!empty($_SESSION[self::SESSION_KEY]['must_change_password']) && basename((string) ($_SERVER['PHP_SELF'] ?? '')) !== 'change-password.php') {
            header('Location: change-password.php?required=1');
            exit;
        }
    }

    public function requireRole(string ...$roles): void
    {
        $this->requireLogin();
        if (!$this->hasRole(...$roles)) {
            http_response_code(403);
            exit('Accès refusé.');
        }
    }

    public function refreshUser(): void
    {
        $id = isset($_SESSION[self::SESSION_KEY]['id']) ? (int) $_SESSION[self::SESSION_KEY]['id'] : 0;
        if ($id <= 0) return;
        $stmt = $this->pdo->prepare('SELECT u.firstname,u.lastname,u.username,u.email,u.profile_photo,u.group_id,u.must_change_password,r.id role_id,r.name role_name,g.name group_name FROM users u INNER JOIN roles r ON r.id=u.role_id LEFT JOIN groups_company g ON g.id=u.group_id WHERE u.id=:id');
        $stmt->execute(['id' => $id]);
        $u = $stmt->fetch();
        if (!$u) return;
        $_SESSION[self::SESSION_KEY] = array_merge($_SESSION[self::SESSION_KEY], [
            'firstname'=>$u['firstname'],'lastname'=>$u['lastname'],'username'=>$u['username'],'email'=>$u['email'],'profile_photo'=>$u['profile_photo'],'role_id'=>(int)$u['role_id'],'role'=>$u['role_name'],
            'group_id'=>$u['group_id'] !== null ? (int)$u['group_id'] : null,'group'=>$u['group_name'],'must_change_password'=>(bool)$u['must_change_password']
        ]);
    }

    public function logout(string $reason = 'logout'): void
    {
        $uid = isset($_SESSION[self::SESSION_KEY]['id']) ? (int) $_SESSION[self::SESSION_KEY]['id'] : null;
        if ($uid) {
            $this->pdo->prepare('UPDATE user_sessions SET revoked_at = COALESCE(revoked_at,NOW()) WHERE session_id = :sid')->execute(['sid' => session_id()]);
            $this->audit->log($uid, $reason, 'user', $uid);
        }
        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    private function recordAttempt(string $email, ?int $userId, string $ip, bool $success): void
    {
        $s = $this->pdo->prepare('INSERT INTO login_attempts (email,user_id,ip_address,success) VALUES (:email,:user_id,:ip,:success)');
        $s->execute(['email'=>$email,'user_id'=>$userId,'ip'=>$ip,'success'=>$success ? 1 : 0]);
    }

    private function recentFailures(string $email, string $ip): int
    {
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE success=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (email=:email OR ip_address=:ip)');
        $s->bindValue(':email', $email);
        $s->bindValue(':ip', $ip);
        $s->execute();
        return (int) $s->fetchColumn();
    }

    private function registerSession(int $userId): void
    {
        $s = $this->pdo->prepare('INSERT INTO user_sessions (session_id,user_id,ip_address,user_agent,last_seen_at,expires_at) VALUES (:sid,:uid,:ip,:ua,NOW(),DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
        $s->execute(['sid'=>session_id(),'uid'=>$userId,'ip'=>$this->audit->clientIp(),'ua'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)]);
    }

    private function sessionIsValid(): bool
    {
        $s = $this->pdo->prepare(
            'SELECT us.id
             FROM user_sessions us
             INNER JOIN users u ON u.id = us.user_id
             WHERE us.session_id = :sid
               AND us.revoked_at IS NULL
               AND us.expires_at > NOW()
               AND u.active = 1
             LIMIT 1'
        );
        $s->execute(['sid' => session_id()]);
        return (bool) $s->fetchColumn();
    }

    private function touchSession(): void
    {
        $s = $this->pdo->prepare('UPDATE user_sessions SET last_seen_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE session_id=:sid AND revoked_at IS NULL');
        $s->execute(['sid'=>session_id()]);
    }
}
