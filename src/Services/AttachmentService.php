<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use PDO;
use RuntimeException;
use Throwable;

final class AttachmentService
{
    private int $maxFileSize;
    private int $maxFilesPerUpload;
    private string $uploadRoot;

    /** @var array<string, array<int, string>> */
    private array $allowedMimeExtensions = [
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'log', 'csv'],
        'text/csv' => ['csv'],
        'application/csv' => ['csv'],
        'application/vnd.ms-excel' => ['csv'],
    ];

    public function __construct(
        private PDO $pdo,
        private TicketService $ticketService,
        array $settings = []
    ) {
        $projectRoot = dirname(__DIR__, 2);
        $this->uploadRoot = $projectRoot . '/storage/uploads/tickets';
        $this->maxFileSize = max(1, (int) ($settings['max_file_size'] ?? 10 * 1024 * 1024));
        $this->maxFilesPerUpload = max(1, min(10, (int) ($settings['max_files_per_upload'] ?? 5)));
    }

    /** @return array<int, array<string, mixed>> */
    public function listForTicket(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.original_name, a.mime_type, a.size_bytes, a.created_at, a.uploaded_by,
                    CONCAT(u.firstname, " ", u.lastname) AS uploader_name
             FROM ticket_attachments a
             INNER JOIN users u ON u.id = a.uploaded_by
             WHERE a.ticket_id = :ticket_id
             ORDER BY a.created_at DESC, a.id DESC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $user
     * @param array<string, mixed> $files
     */
    public function uploadMany(array $ticket, array $user, array $files): int
    {
        if (!$this->ticketService->canView($ticket, $user)) {
            throw new RuntimeException('Vous ne pouvez pas ajouter de pièce jointe à ce ticket.');
        }

        if (in_array((string) ($ticket['status_code'] ?? ''), ['resolved', 'closed', 'cancelled'], true)) {
            throw new RuntimeException('Aucune pièce jointe ne peut être ajoutée à un ticket terminé.');
        }

        $normalized = $this->normalizeFiles($files);
        if ($normalized === []) {
            throw new RuntimeException('Sélectionnez au moins un fichier.');
        }
        if (count($normalized) > $this->maxFilesPerUpload) {
            throw new RuntimeException('Vous pouvez envoyer au maximum ' . $this->maxFilesPerUpload . ' fichiers à la fois.');
        }

        $prepared = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($normalized as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException($this->uploadErrorMessage($error));
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $originalName = $this->sanitizeOriginalName((string) ($file['name'] ?? 'fichier'));

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Le fichier « ' . $originalName . ' » n’a pas été reçu correctement.');
            }
            if ($size <= 0) {
                throw new RuntimeException('Le fichier « ' . $originalName . ' » est vide.');
            }
            if ($size > $this->maxFileSize) {
                throw new RuntimeException('Le fichier « ' . $originalName . ' » dépasse la taille maximale de ' . $this->formatBytes($this->maxFileSize) . '.');
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $mime = (string) $finfo->file($tmpName);
            if (!$this->isAllowedType($mime, $extension)) {
                throw new RuntimeException('Le type du fichier « ' . $originalName . ' » n’est pas autorisé. Formats acceptés : PNG, JPG/JPEG, PDF, TXT, LOG et CSV.');
            }

            $storedExtension = $this->canonicalExtension($mime, $extension);
            $prepared[] = [
                'tmp_name' => $tmpName,
                'original_name' => $originalName,
                'stored_name' => bin2hex(random_bytes(24)) . '.' . $storedExtension,
                'mime_type' => $mime,
                'size_bytes' => $size,
            ];
        }

        $ticketId = (int) $ticket['id'];
        $targetDirectory = $this->uploadRoot . '/' . $ticketId;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Impossible de préparer le dossier de stockage des pièces jointes.');
        }

        $movedPaths = [];
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO ticket_attachments
                 (ticket_id, uploaded_by, original_name, stored_name, mime_type, size_bytes)
                 VALUES (:ticket_id, :uploaded_by, :original_name, :stored_name, :mime_type, :size_bytes)'
            );

            foreach ($prepared as $file) {
                $destination = $targetDirectory . '/' . $file['stored_name'];
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException('Impossible d’enregistrer le fichier « ' . $file['original_name'] . ' ».');
                }
                @chmod($destination, 0660);
                $movedPaths[] = $destination;

                $insert->execute([
                    'ticket_id' => $ticketId,
                    'uploaded_by' => (int) $user['id'],
                    'original_name' => $file['original_name'],
                    'stored_name' => $file['stored_name'],
                    'mime_type' => $file['mime_type'],
                    'size_bytes' => $file['size_bytes'],
                ]);

                $this->recordHistory(
                    $ticketId,
                    (int) $user['id'],
                    'Pièce jointe ajoutée',
                    null,
                    $file['original_name']
                );
            }

            $this->pdo->commit();
            return count($prepared);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            foreach ($movedPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function getForDownload(int $attachmentId, array $user): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, t.requester_id, t.id AS ticket_id,
                    ts.code AS status_code
             FROM ticket_attachments a
             INNER JOIN tickets t ON t.id = a.ticket_id AND t.deleted_at IS NULL
             INNER JOIN ticket_statuses ts ON ts.id = t.status_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $attachmentId]);
        $attachment = $stmt->fetch();
        if (!$attachment) {
            throw new RuntimeException('Pièce jointe introuvable.');
        }

        $ticket = [
            'id' => (int) $attachment['ticket_id'],
            'requester_id' => (int) $attachment['requester_id'],
        ];
        if (!$this->ticketService->canView($ticket, $user)) {
            throw new RuntimeException('Accès refusé à cette pièce jointe.');
        }

        $path = $this->uploadRoot . '/' . (int) $attachment['ticket_id'] . '/' . basename((string) $attachment['stored_name']);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Le fichier physique de cette pièce jointe est introuvable.');
        }

        $attachment['path'] = $path;
        return $attachment;
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $error,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }

    private function isAllowedType(string $mime, string $extension): bool
    {
        if (!isset($this->allowedMimeExtensions[$mime])) {
            return false;
        }
        return in_array($extension, $this->allowedMimeExtensions[$mime], true);
    }

    private function canonicalExtension(string $mime, string $extension): string
    {
        if ($mime === 'image/jpeg') {
            return $extension === 'jpeg' ? 'jpeg' : 'jpg';
        }
        return $this->allowedMimeExtensions[$mime][0];
    }

    private function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '') {
            $name = 'fichier';
        }
        return mb_substr($name, 0, 255);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Un fichier dépasse la taille autorisée par le serveur.',
            UPLOAD_ERR_PARTIAL => 'Le transfert d’un fichier a été interrompu.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n’a été sélectionné.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le dossier temporaire PHP est manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a interrompu le transfert.',
            default => 'Erreur inconnue pendant le transfert du fichier.',
        };
    }

    private function recordHistory(int $ticketId, int $userId, string $action, ?string $oldValue, ?string $newValue): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value)
             VALUES (:ticket_id, :user_id, :action, :old_value, :new_value)'
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
        }
        return number_format($bytes / 1024, 0, ',', ' ') . ' Ko';
    }
}
