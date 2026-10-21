<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SavedTicketViewService
{
    private const MAX_VIEWS_PER_USER = 12;

    private const ALLOWED_SCOPES = ['mine', 'unassigned', 'team', 'all', 'archive'];
    private const ALLOWED_SLA = ['', 'response', 'resolution', 'ok'];
    private const ALLOWED_PER_PAGE = [15, 30, 50];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, filters_json, created_at, updated_at
             FROM saved_ticket_views
             WHERE user_id = :user_id
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['filters_json'], true);
            $row['filters'] = is_array($decoded) ? $decoded : [];
            $row['url'] = $this->buildUrl($row['filters']);
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $filters */
    public function create(int $userId, string $name, array $filters, string $role): int
    {
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            throw new RuntimeException('Le nom de la vue doit contenir entre 2 et 60 caractères.');
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM saved_ticket_views WHERE user_id = :user_id');
        $countStmt->execute(['user_id' => $userId]);
        if ((int) $countStmt->fetchColumn() >= self::MAX_VIEWS_PER_USER) {
            throw new RuntimeException('Vous pouvez enregistrer au maximum ' . self::MAX_VIEWS_PER_USER . ' vues.');
        }

        $clean = $this->sanitizeFilters($filters, $role);
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Impossible d’enregistrer cette vue.');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO saved_ticket_views (user_id, name, filters_json)
                 VALUES (:user_id, :name, :filters_json)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'name' => $name,
                'filters_json' => $json,
            ]);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Vous avez déjà une vue enregistrée avec ce nom.');
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $userId, int $viewId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM saved_ticket_views WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $viewId, 'user_id' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Vue enregistrée introuvable.');
        }
    }

    /** @param array<string,mixed> $filters
     *  @return array<string,mixed>
     */
    public function sanitizeFilters(array $filters, string $role): array
    {
        $scope = (string) ($filters['scope'] ?? 'mine');
        if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
            $scope = 'mine';
        }
        if ($role === 'IT' && $scope === 'all') {
            $scope = 'mine';
        }

        $clean = ['scope' => $scope];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') $clean['q'] = mb_substr($q, 0, 120);

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') $clean['status'] = mb_substr($status, 0, 50);

        $priority = (int) ($filters['priority'] ?? 0);
        if ($priority > 0) $clean['priority'] = $priority;

        foreach (['type_id', 'category_id', 'group_id', 'manager_id'] as $key) {
            $value = (int) ($filters[$key] ?? 0);
            if ($value > 0) $clean[$key] = $value;
        }

        $assignedIt = trim((string) ($filters['assigned_it'] ?? ''));
        if ($assignedIt === 'unassigned' || (ctype_digit($assignedIt) && (int) $assignedIt > 0)) {
            $clean['assigned_it'] = $assignedIt;
        }

        foreach (['date_from', 'date_to'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $clean[$key] = $value;
            }
        }

        if ($role === 'Administrateur') {
            $sla = trim((string) ($filters['sla'] ?? ''));
            if (in_array($sla, self::ALLOWED_SLA, true) && $sla !== '') {
                $clean['sla'] = $sla;
            }
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $clean['per_page'] = in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : 15;

        return $clean;
    }

    /** @param array<string,mixed> $filters */
    public function buildUrl(array $filters): string
    {
        return 'it-tickets.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    }
}
