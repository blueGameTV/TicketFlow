<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class TicketService
{
    public function __construct(private PDO $pdo, private NotificationService $notifications, private SlaService $sla)
    {
    }

    public function create(array $data, int $requesterId): string
    {
        $requesterStmt = $this->pdo->prepare('SELECT id, active FROM users WHERE id = :id LIMIT 1');
        $requesterStmt->execute(['id' => $requesterId]);
        $requester = $requesterStmt->fetch();
        if (!$requester || (int) $requester['active'] !== 1) {
            throw new RuntimeException('Le compte demandeur est introuvable ou désactivé.');
        }

        $typeId = (int) ($data['type_id'] ?? 0);
        $priorityId = (int) ($data['priority_id'] ?? 0);
        $categoryId = isset($data['category_id']) && $data['category_id'] !== null && $data['category_id'] !== ''
            ? (int) $data['category_id']
            : null;
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        $typeStmt = $this->pdo->prepare('SELECT id, code FROM ticket_types WHERE id = :id LIMIT 1');
        $typeStmt->execute(['id' => $typeId]);
        $type = $typeStmt->fetch();
        if (!$type) {
            throw new RuntimeException('Type de ticket invalide.');
        }

        $priorityStmt = $this->pdo->prepare('SELECT id FROM priorities WHERE id = :id LIMIT 1');
        $priorityStmt->execute(['id' => $priorityId]);
        if (!$priorityStmt->fetchColumn()) {
            throw new RuntimeException('Importance invalide.');
        }

        if ($categoryId !== null) {
            $categoryStmt = $this->pdo->prepare('SELECT id FROM ticket_categories WHERE id = :id AND active = 1 LIMIT 1');
            $categoryStmt->execute(['id' => $categoryId]);
            if (!$categoryStmt->fetchColumn()) {
                throw new RuntimeException('Catégorie invalide ou désactivée.');
            }
        }

        if (mb_strlen($title) < 5 || mb_strlen($title) > 80) {
            throw new RuntimeException('Le titre doit contenir entre 5 et 80 caractères.');
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 2000) {
            throw new RuntimeException('La description doit contenir entre 10 et 2000 caractères.');
        }

        $statusId = $this->statusId('new');

        $this->pdo->beginTransaction();
        try {
            $temporaryNumber = 'TMP-' . bin2hex(random_bytes(12));

            $insert = $this->pdo->prepare(
                'INSERT INTO tickets
                (ticket_number, requester_id, type_id, category_id, priority_id, status_id, title, description)
                VALUES
                (:ticket_number, :requester_id, :type_id, :category_id, :priority_id, :status_id, :title, :description)'
            );
            $insert->bindValue(':ticket_number', $temporaryNumber, PDO::PARAM_STR);
            $insert->bindValue(':requester_id', $requesterId, PDO::PARAM_INT);
            $insert->bindValue(':type_id', $typeId, PDO::PARAM_INT);
            if ($categoryId === null) {
                $insert->bindValue(':category_id', null, PDO::PARAM_NULL);
            } else {
                $insert->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
            }
            $insert->bindValue(':priority_id', $priorityId, PDO::PARAM_INT);
            $insert->bindValue(':status_id', $statusId, PDO::PARAM_INT);
            $insert->bindValue(':title', $title, PDO::PARAM_STR);
            $insert->bindValue(':description', $description, PDO::PARAM_STR);
            $insert->execute();

            $ticketId = (int) $this->pdo->lastInsertId();
            if ($ticketId <= 0) {
                throw new RuntimeException('La base de données n’a pas retourné l’identifiant du ticket.');
            }

            $ticketNumber = sprintf('%s-%s-%06d', strtoupper((string) $type['code']), date('Y'), $ticketId);
            $update = $this->pdo->prepare('UPDATE tickets SET ticket_number = :number WHERE id = :id');
            $update->execute(['number' => $ticketNumber, 'id' => $ticketId]);
            $this->sla->initializeTicket($ticketId, $priorityId);

            $this->addHistory($ticketId, $requesterId, 'Ticket créé', null, 'Statut : Nouveau');

            $itIds = $this->activeUserIdsByRole('IT');
            $this->notifications->notifyMany(
                $itIds,
                'new_ticket',
                'Nouveau ticket non attribué',
                $ticketNumber . ' — ' . $title,
                $ticketId,
                'ticket.php?number=' . rawurlencode($ticketNumber),
                $requesterId
            );
            $this->pdo->commit();

            return $ticketNumber;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function canView(array $ticket, array $user): bool
    {
        if (in_array($user['role'], ['Administrateur', 'IT'], true)) {
            return true;
        }

        if ((int) $ticket['requester_id'] === (int) $user['id']) {
            return true;
        }

        if (($user['role'] ?? null) === 'Manager') {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM manager_approvals WHERE ticket_id = :ticket_id AND manager_id = :manager_id LIMIT 1'
            );
            $stmt->execute([
                'ticket_id' => $ticket['id'],
                'manager_id' => $user['id'],
            ]);
            return (bool) $stmt->fetchColumn();
        }

        return false;
    }

    public function takeOwnership(int $ticketId, array $user): void
    {
        if (($user['role'] ?? null) !== 'IT') {
            throw new RuntimeException('Action réservée à IT.');
        }

        $this->pdo->beginTransaction();
        try {
            $ticket = $this->lockTicket($ticketId);
            if ($ticket['assigned_it_id'] !== null && (int) $ticket['assigned_it_id'] !== (int) $user['id']) {
                throw new RuntimeException('Ce ticket est déjà attribué à un autre technicien.');
            }

            $statusId = $this->statusId('assigned');
            $update = $this->pdo->prepare('UPDATE tickets SET assigned_it_id = :user_id, status_id = :status_id, assigned_at = COALESCE(assigned_at, NOW()) WHERE id = :id');
            $update->execute(['user_id' => $user['id'], 'status_id' => $statusId, 'id' => $ticketId]);

            $this->addHistory($ticketId, (int) $user['id'], 'Ticket pris en charge', null, $user['firstname'] . ' ' . $user['lastname']);
            $meta = $this->ticketMeta($ticketId);
            $this->notifications->notify(
                (int) $meta['requester_id'],
                'assignment',
                'Votre ticket a été pris en charge',
                $meta['ticket_number'] . ' est maintenant pris en charge par ' . $user['firstname'] . ' ' . $user['lastname'] . '.',
                $ticketId,
                'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updatePriority(int $ticketId, int $priorityId, array $user): void
    {
        $this->assertItOrAdmin($user);

        $priorityStmt = $this->pdo->prepare('SELECT id, name FROM priorities WHERE id = :id LIMIT 1');
        $priorityStmt->execute(['id' => $priorityId]);
        $priority = $priorityStmt->fetch();
        if (!$priority) {
            throw new RuntimeException('Importance invalide.');
        }

        $ticket = $this->ticketForUpdate($ticketId);
        $oldStmt = $this->pdo->prepare('SELECT name FROM priorities WHERE id = :id LIMIT 1');
        $oldStmt->execute(['id' => $ticket['priority_id']]);
        $oldName = (string) $oldStmt->fetchColumn();

        $update = $this->pdo->prepare('UPDATE tickets SET priority_id = :priority_id WHERE id = :id');
        $update->execute(['priority_id' => $priorityId, 'id' => $ticketId]);
        $this->sla->recalculateTicket($ticketId, $priorityId);
        $this->addHistory($ticketId, (int) $user['id'], 'Importance modifiée', $oldName, (string) $priority['name']);
        $meta = $this->ticketMeta($ticketId);
        if ((int) $meta['requester_id'] !== (int) $user['id']) {
            $this->notifications->notify(
                (int) $meta['requester_id'],
                'priority',
                'Importance du ticket modifiée',
                $meta['ticket_number'] . ' : ' . $oldName . ' → ' . $priority['name'],
                $ticketId,
                'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
            );
        }
    }

    public function updateStatus(int $ticketId, string $statusCode, array $user): void
    {
        $this->assertItOrAdmin($user);

        // "Résolu" n'est jamais sélectionné manuellement par IT :
        // il est appliqué automatiquement lorsque le demandeur confirme la solution.
        $allowedForIt = ['assigned', 'in_progress', 'waiting_user', 'cancelled'];
        $allowedForAdmin = ['new', 'assigned', 'in_progress', 'waiting_user', 'waiting_manager', 'waiting_confirmation', 'closed', 'cancelled'];
        $allowed = ($user['role'] ?? null) === 'IT' ? $allowedForIt : $allowedForAdmin;
        if (!in_array($statusCode, $allowed, true)) {
            throw new RuntimeException('Ce changement de statut n\'est pas autorisé.');
        }

        $ticket = $this->ticketForUpdate($ticketId);
        $oldStmt = $this->pdo->prepare('SELECT code, name FROM ticket_statuses WHERE id = :id LIMIT 1');
        $oldStmt->execute(['id' => $ticket['status_id']]);
        $old = $oldStmt->fetch();

        $newStmt = $this->pdo->prepare('SELECT id, code, name FROM ticket_statuses WHERE code = :code LIMIT 1');
        $newStmt->execute(['code' => $statusCode]);
        $new = $newStmt->fetch();
        if (!$new) {
            throw new RuntimeException('Statut invalide.');
        }

        $sql = 'UPDATE tickets SET status_id = :status_id';
        if ($statusCode === 'closed') {
            $sql .= ', closed_at = NOW()';
        } elseif ($statusCode === 'cancelled') {
            $sql .= ', closed_at = NOW()';
        } else {
            $sql .= ', closed_at = NULL';
            if ($statusCode !== 'waiting_confirmation') {
                $sql .= ', resolved_at = NULL';
            }
        }
        $sql .= ' WHERE id = :id';

        $update = $this->pdo->prepare($sql);
        $update->execute(['status_id' => $new['id'], 'id' => $ticketId]);

        $this->addHistory(
            $ticketId,
            (int) $user['id'],
            'Statut modifié',
            (string) ($old['name'] ?? ''),
            (string) $new['name']
        );
        $meta = $this->ticketMeta($ticketId);
        if ((int) $meta['requester_id'] !== (int) $user['id']) {
            $this->notifications->notify(
                (int) $meta['requester_id'],
                'status',
                'Statut du ticket mis à jour',
                $meta['ticket_number'] . ' : ' . ($old['name'] ?? '') . ' → ' . $new['name'],
                $ticketId,
                'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
            );
        }
    }

    public function transfer(int $ticketId, int $newItId, array $user): void
    {
        $this->assertItOrAdmin($user);

        $itStmt = $this->pdo->prepare(
            "SELECT u.id, u.firstname, u.lastname, u.group_id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.active = 1 AND r.name = 'IT'
             LIMIT 1"
        );
        $itStmt->execute(['id' => $newItId]);
        $target = $itStmt->fetch();
        if (!$target) {
            throw new RuntimeException('Technicien IT invalide ou inactif.');
        }

        if (($user['role'] ?? null) === 'IT' && $user['group_id'] !== null && (int) $target['group_id'] !== (int) $user['group_id']) {
            throw new RuntimeException('Le transfert est limité aux membres de votre groupe IT.');
        }

        $ticket = $this->ticketForUpdate($ticketId);
        $oldName = 'Non assigné';
        if ($ticket['assigned_it_id'] !== null) {
            $oldStmt = $this->pdo->prepare("SELECT CONCAT(firstname, ' ', lastname) FROM users WHERE id = :id");
            $oldStmt->execute(['id' => $ticket['assigned_it_id']]);
            $oldName = (string) ($oldStmt->fetchColumn() ?: 'Technicien inconnu');
        }

        $statusId = $this->statusId('assigned');
        $update = $this->pdo->prepare('UPDATE tickets SET assigned_it_id = :assigned_it_id, status_id = :status_id, assigned_at = COALESCE(assigned_at, NOW()) WHERE id = :id');
        $update->execute(['assigned_it_id' => $newItId, 'status_id' => $statusId, 'id' => $ticketId]);

        $this->addHistory(
            $ticketId,
            (int) $user['id'],
            'Ticket transféré',
            $oldName,
            $target['firstname'] . ' ' . $target['lastname']
        );
        $meta = $this->ticketMeta($ticketId);
        $this->notifications->notify(
            $newItId,
            'transfer',
            'Un ticket vous a été transféré',
            $meta['ticket_number'] . ' — ' . $meta['title'],
            $ticketId,
            'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
        );
    }

    public function addMessage(int $ticketId, int $authorId, string $message, bool $internal): void
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 4000) {
            throw new RuntimeException('Le message doit contenir entre 1 et 4000 caractères.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, author_id, message, internal)
             VALUES (:ticket_id, :author_id, :message, :internal)'
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'author_id' => $authorId,
            'message' => $message,
            'internal' => $internal ? 1 : 0,
        ]);
        $this->addHistory($ticketId, $authorId, $internal ? 'Note interne ajoutée' : 'Message ajouté', null, null);

        $meta = $this->ticketMeta($ticketId);
        if (!$internal) {
            if ($authorId === (int) $meta['requester_id']) {
                if (!empty($meta['assigned_it_id']) && (int) $meta['assigned_it_id'] !== $authorId) {
                    $this->notifications->notify(
                        (int) $meta['assigned_it_id'],
                        'message',
                        'Nouveau message du demandeur',
                        $meta['ticket_number'] . ' — ' . $meta['title'],
                        $ticketId,
                        'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
                    );
                }
            } else {
                if ((int) $meta['requester_id'] !== $authorId) {
                    $this->notifications->notify(
                        (int) $meta['requester_id'],
                        'message',
                        'Nouveau message sur votre ticket',
                        $meta['ticket_number'] . ' — ' . $meta['title'],
                        $ticketId,
                        'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
                    );
                }

                $authorRoleStmt = $this->pdo->prepare('SELECT r.name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
                $authorRoleStmt->execute(['id' => $authorId]);
                $authorRole = (string) ($authorRoleStmt->fetchColumn() ?: '');
                if ($authorRole === 'Manager' && !empty($meta['assigned_it_id']) && (int) $meta['assigned_it_id'] !== $authorId) {
                    $this->notifications->notify(
                        (int) $meta['assigned_it_id'],
                        'message',
                        'Nouveau message du Manager',
                        $meta['ticket_number'] . ' — ' . $meta['title'],
                        $ticketId,
                        'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
                    );
                }
            }
        } elseif (!empty($meta['assigned_it_id']) && (int) $meta['assigned_it_id'] !== $authorId) {
            $this->notifications->notify(
                (int) $meta['assigned_it_id'],
                'internal_note',
                'Nouvelle note interne',
                $meta['ticket_number'] . ' — ' . $meta['title'],
                $ticketId,
                'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
            );
        }
    }

    public function proposeResolution(int $ticketId, string $resolution, array $user): void
    {
        $this->assertItOrAdmin($user);
        $ticket = $this->ticketForUpdate($ticketId);
        $statusCode = $this->statusCode((int) $ticket['status_id']);
        if (in_array($statusCode, ['closed', 'cancelled', 'waiting_manager'], true)) {
            throw new RuntimeException('Une résolution ne peut pas être proposée dans l’état actuel du ticket.');
        }
        $resolution = trim($resolution);
        if (mb_strlen($resolution) < 5 || mb_strlen($resolution) > 4000) {
            throw new RuntimeException('La résolution doit contenir entre 5 et 4000 caractères.');
        }

        $statusId = $this->statusId('waiting_confirmation');
        $update = $this->pdo->prepare(
            'UPDATE tickets
             SET resolution = :resolution, resolved_at = NULL, closed_at = NULL, status_id = :status_id
             WHERE id = :id'
        );
        $update->execute(['resolution' => $resolution, 'status_id' => $statusId, 'id' => $ticketId]);
        $this->addHistory($ticketId, (int) $user['id'], 'Résolution proposée', null, 'En attente de validation du demandeur');
        $meta = $this->ticketMeta($ticketId);
        $this->notifications->notify(
            (int) $meta['requester_id'],
            'resolution',
            'Votre confirmation est demandée',
            'Une résolution a été proposée pour ' . $meta['ticket_number'] . '. Merci de confirmer si le problème est résolu.',
            $ticketId,
            'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
        );
    }

    public function requestManagerApproval(int $ticketId, array $user): void
    {
        $this->assertItOrAdmin($user);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.id, t.requester_id, ts.code AS status_code, g.manager_id,
                        CONCAT(m.firstname, ' ', m.lastname) AS manager_name,
                        m.active AS manager_active, r.name AS manager_role
                 FROM tickets t
                 INNER JOIN ticket_statuses ts ON ts.id = t.status_id
                 INNER JOIN users requester ON requester.id = t.requester_id
                 LEFT JOIN groups_company g ON g.id = requester.group_id
                 LEFT JOIN users m ON m.id = g.manager_id
                 LEFT JOIN roles r ON r.id = m.role_id
                 WHERE t.id = :id AND t.deleted_at IS NULL
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $ticketId]);
            $ticket = $stmt->fetch();
            if (!$ticket) {
                throw new RuntimeException('Ticket introuvable.');
            }
            if (in_array($ticket['status_code'], ['closed', 'cancelled', 'waiting_confirmation'], true)) {
                throw new RuntimeException('Une validation Manager ne peut pas être demandée dans l’état actuel du ticket.');
            }
            if (empty($ticket['manager_id'])) {
                throw new RuntimeException('Le demandeur n\'a aucun Manager défini pour son groupe.');
            }
            if ((int) $ticket['manager_active'] !== 1 || $ticket['manager_role'] !== 'Manager') {
                throw new RuntimeException('Le Manager associé au groupe est invalide ou inactif.');
            }

            $pending = $this->pdo->prepare(
                "SELECT id FROM manager_approvals
                 WHERE ticket_id = :ticket_id AND manager_id = :manager_id AND status = 'pending'
                 LIMIT 1"
            );
            $pending->execute([
                'ticket_id' => $ticketId,
                'manager_id' => $ticket['manager_id'],
            ]);
            if ($pending->fetchColumn()) {
                throw new RuntimeException('Une validation de ce Manager est déjà en attente.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO manager_approvals (ticket_id, manager_id, requested_by)
                 VALUES (:ticket_id, :manager_id, :requested_by)'
            );
            $insert->execute([
                'ticket_id' => $ticketId,
                'manager_id' => $ticket['manager_id'],
                'requested_by' => $user['id'],
            ]);

            $statusId = $this->statusId('waiting_manager');
            $update = $this->pdo->prepare('UPDATE tickets SET status_id = :status_id WHERE id = :id');
            $update->execute(['status_id' => $statusId, 'id' => $ticketId]);

            $this->addHistory(
                $ticketId,
                (int) $user['id'],
                'Validation Manager demandée',
                null,
                (string) $ticket['manager_name']
            );
            $meta = $this->ticketMeta($ticketId);
            $this->notifications->notify(
                (int) $ticket['manager_id'],
                'manager_approval',
                'Validation Manager requise',
                'Une validation est demandée pour ' . $meta['ticket_number'] . ' — ' . $meta['title'],
                $ticketId,
                'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function respondManagerApproval(int $approvalId, string $decision, string $comment, array $user): void
    {
        if (($user['role'] ?? null) !== 'Manager') {
            throw new RuntimeException('Action réservée au Manager concerné.');
        }

        $allowed = ['approved', 'rejected', 'more_info'];
        if (!in_array($decision, $allowed, true)) {
            throw new RuntimeException('Décision de validation invalide.');
        }

        $comment = trim($comment);
        if (mb_strlen($comment) > 2000) {
            throw new RuntimeException('Le commentaire ne peut pas dépasser 2000 caractères.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ma.id, ma.ticket_id, ma.manager_id, ma.requested_by, ma.status, t.ticket_number, t.assigned_it_id, t.title
                 FROM manager_approvals ma
                 INNER JOIN tickets t ON t.id = ma.ticket_id
                 WHERE ma.id = :id
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $approvalId]);
            $approval = $stmt->fetch();
            if (!$approval || (int) $approval['manager_id'] !== (int) $user['id']) {
                throw new RuntimeException('Demande de validation introuvable.');
            }
            if ($approval['status'] !== 'pending') {
                throw new RuntimeException('Cette demande de validation a déjà reçu une réponse.');
            }

            $updateApproval = $this->pdo->prepare(
                'UPDATE manager_approvals
                 SET status = :status, comment = :comment, responded_at = NOW()
                 WHERE id = :id'
            );
            $updateApproval->execute([
                'status' => $decision,
                'comment' => $comment !== '' ? $comment : null,
                'id' => $approvalId,
            ]);

            $statusId = $this->statusId('in_progress');
            $updateTicket = $this->pdo->prepare('UPDATE tickets SET status_id = :status_id WHERE id = :id');
            $updateTicket->execute(['status_id' => $statusId, 'id' => $approval['ticket_id']]);

            $label = match ($decision) {
                'approved' => 'Validée',
                'rejected' => 'Refusée',
                'more_info' => 'Informations supplémentaires demandées',
            };
            $newValue = $label . ($comment !== '' ? ' — ' . $comment : '');
            $this->addHistory(
                (int) $approval['ticket_id'],
                (int) $user['id'],
                'Réponse du Manager',
                'En attente',
                $newValue
            );

            $recipients = [(int) $approval['requested_by']];
            if (!empty($approval['assigned_it_id'])) {
                $recipients[] = (int) $approval['assigned_it_id'];
            }
            $this->notifications->notifyMany(
                $recipients,
                'manager_response',
                'Réponse du Manager',
                $approval['ticket_number'] . ' : ' . $label,
                (int) $approval['ticket_id'],
                'ticket.php?number=' . rawurlencode((string) $approval['ticket_number']),
                (int) $user['id']
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function confirmResolution(int $ticketId, array $user): void
    {
        $this->pdo->beginTransaction();
        try {
            $ticket = $this->lockTicket($ticketId);
            if ((int) $this->requesterId($ticketId) !== (int) $user['id']) {
                throw new RuntimeException('Seul le demandeur peut confirmer la résolution.');
            }

            $currentStatus = $this->statusCode((int) $ticket['status_id']);
            if ($currentStatus !== 'waiting_confirmation') {
                throw new RuntimeException('Ce ticket n\'est pas en attente de votre confirmation.');
            }

            $resolvedId = $this->statusId('resolved');
            $update = $this->pdo->prepare(
                'UPDATE tickets
                 SET status_id = :status_id, resolved_at = NOW(), closed_at = NULL
                 WHERE id = :id'
            );
            $update->execute(['status_id' => $resolvedId, 'id' => $ticketId]);
            $this->addHistory(
                $ticketId,
                (int) $user['id'],
                'Résolution confirmée par le demandeur',
                'Attente validation utilisateur',
                'Résolu'
            );
            $meta = $this->ticketMeta($ticketId);
            if (!empty($meta['assigned_it_id'])) {
                $this->notifications->notify(
                    (int) $meta['assigned_it_id'],
                    'resolution_confirmed',
                    'Résolution confirmée',
                    $meta['ticket_number'] . ' a été confirmé comme résolu par le demandeur.',
                    $ticketId,
                    'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
                );
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function rejectResolution(int $ticketId, string $comment, array $user): void
    {
        $comment = trim($comment);
        if (mb_strlen($comment) > 2000) {
            throw new RuntimeException('Le commentaire ne peut pas dépasser 2000 caractères.');
        }

        $this->pdo->beginTransaction();
        try {
            $ticket = $this->lockTicket($ticketId);
            if ((int) $this->requesterId($ticketId) !== (int) $user['id']) {
                throw new RuntimeException('Seul le demandeur peut refuser la résolution.');
            }

            $currentStatus = $this->statusCode((int) $ticket['status_id']);
            if ($currentStatus !== 'waiting_confirmation') {
                throw new RuntimeException('Ce ticket n\'est pas en attente de votre confirmation.');
            }

            $inProgressId = $this->statusId('in_progress');
            $update = $this->pdo->prepare(
                'UPDATE tickets
                 SET status_id = :status_id, resolution = NULL, resolved_at = NULL, closed_at = NULL
                 WHERE id = :id'
            );
            $update->execute(['status_id' => $inProgressId, 'id' => $ticketId]);

            if ($comment !== '') {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO ticket_messages (ticket_id, author_id, message, internal)
                     VALUES (:ticket_id, :author_id, :message, 0)'
                );
                $stmt->execute([
                    'ticket_id' => $ticketId,
                    'author_id' => $user['id'],
                    'message' => $comment,
                ]);
            }

            $this->addHistory(
                $ticketId,
                (int) $user['id'],
                'Résolution refusée par le demandeur',
                'Attente validation utilisateur',
                'En cours'
            );
            $meta = $this->ticketMeta($ticketId);
            if (!empty($meta['assigned_it_id'])) {
                $this->notifications->notify(
                    (int) $meta['assigned_it_id'],
                    'resolution_rejected',
                    'Le problème persiste',
                    $meta['ticket_number'] . ' a été rouvert par le demandeur.',
                    $ticketId,
                    'ticket.php?number=' . rawurlencode((string) $meta['ticket_number'])
                );
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function ticketMeta(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, ticket_number, title, requester_id, assigned_it_id
             FROM tickets WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new RuntimeException('Ticket introuvable.');
        }
        return $ticket;
    }

    private function activeUserIdsByRole(string $roleName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.active = 1 AND r.name = :role_name'
        );
        $stmt->execute(['role_name' => $roleName]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function requesterId(int $ticketId): int
    {
        $stmt = $this->pdo->prepare('SELECT requester_id FROM tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        return (int) $stmt->fetchColumn();
    }

    private function statusCode(int $statusId): string
    {
        $stmt = $this->pdo->prepare('SELECT code FROM ticket_statuses WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $statusId]);
        return (string) $stmt->fetchColumn();
    }

    private function addHistory(int $ticketId, ?int $userId, string $action, ?string $oldValue, ?string $newValue): void
    {
        $history = $this->pdo->prepare(
            'INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
             VALUES (:ticket_id, :user_id, :action, :old_value, :new_value)'
        );
        $history->execute([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    private function statusId(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ticket_statuses WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $id = (int) $stmt->fetchColumn();
        if ($id <= 0) {
            throw new RuntimeException('Statut introuvable : ' . $code);
        }
        return $id;
    }

    private function lockTicket(int $ticketId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, assigned_it_id, status_id, priority_id FROM tickets WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new RuntimeException('Ticket introuvable.');
        }
        return $ticket;
    }

    private function ticketForUpdate(int $ticketId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, assigned_it_id, status_id, priority_id FROM tickets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            throw new RuntimeException('Ticket introuvable.');
        }
        return $ticket;
    }

    private function assertItOrAdmin(array $user): void
    {
        if (!in_array($user['role'] ?? null, ['IT', 'Administrateur'], true)) {
            throw new RuntimeException('Action réservée à IT ou Administrateur.');
        }
    }
}
