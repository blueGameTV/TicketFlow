<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class UserImportService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const MAX_ROWS = 500;

    public function __construct(
        private PDO $pdo,
        private AuditService $auditService,
    ) {
    }

    /**
     * @return list<array<string,string>>
     */
    public function parseUpload(array $file): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Le fichier envoyé n’est pas valide.');
        }
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Le fichier doit faire au maximum 5 Mo.');
        }

        $extension = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match ($extension) {
            'csv' => $this->parseCsv($tmp),
            'xlsx' => $this->parseXlsx($tmp),
            default => throw new RuntimeException('Format non pris en charge. Utilisez un fichier CSV ou XLSX.'),
        };
    }

    /**
     * @param list<array<string,string>> $rows
     * @return array{rows:list<array<string,mixed>>,valid:int,invalid:int}
     */
    public function validateRows(array $rows): array
    {
        $roles = $this->loadRoles();
        $groups = $this->loadGroups();
        $managers = $this->loadManagers();
        $existing = $this->loadExistingUsers();

        $seenUsernames = [];
        $seenEmails = [];
        $result = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $index => $raw) {
            $line = $index + 2;
            $errors = [];
            $firstname = trim((string)($raw['prenom'] ?? ''));
            $lastname = trim((string)($raw['nom'] ?? ''));
            $username = mb_strtolower(trim((string)($raw['identifiant'] ?? '')));
            $email = mb_strtolower(trim((string)($raw['email'] ?? '')));
            $roleInput = trim((string)($raw['role'] ?? ''));
            $groupInput = trim((string)($raw['groupe'] ?? ''));
            $managerInput = trim((string)($raw['manager'] ?? ''));
            $arrivalInput = trim((string)($raw['date_arrivee'] ?? ''));
            $activeInput = trim((string)($raw['actif'] ?? '1'));
            $password = (string)($raw['mot_de_passe'] ?? '');
            $forceInput = trim((string)($raw['forcer_changement_mot_de_passe'] ?? '1'));

            if (mb_strlen($firstname) < 2 || mb_strlen($firstname) > 80) {
                $errors[] = 'Prénom invalide (2 à 80 caractères).';
            }
            if (mb_strlen($lastname) < 2 || mb_strlen($lastname) > 80) {
                $errors[] = 'Nom invalide (2 à 80 caractères).';
            }
            if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
                $errors[] = 'Identifiant invalide.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse e-mail invalide.';
            }

            $roleKey = $this->normalizeLookup($roleInput);
            $role = $roles[$roleKey] ?? null;
            if ($role === null) {
                $errors[] = 'Rôle inconnu. Valeurs : Administrateur, Support IT, Manager, Collaborateur.';
            }

            $group = null;
            if ($groupInput !== '') {
                $group = $groups[$this->normalizeLookup($groupInput)] ?? null;
                if ($group === null) {
                    $errors[] = 'Groupe introuvable.';
                } elseif (!(bool)$group['active']) {
                    $errors[] = 'Le groupe est désactivé.';
                }
            }

            $manager = null;
            if ($managerInput !== '') {
                $manager = $this->resolveManager($managerInput, $managers);
                if ($manager === null) {
                    $errors[] = 'Manager introuvable ou inactif.';
                } elseif ($group === null) {
                    $errors[] = 'Un groupe doit être renseigné lorsqu’un Manager est indiqué.';
                } elseif ((int)($group['manager_id'] ?? 0) !== (int)$manager['id']) {
                    $errors[] = 'Le Manager indiqué ne correspond pas au Manager du groupe.';
                }
            } elseif ($group !== null && !empty($group['manager_id'])) {
                $manager = [
                    'id' => (int)$group['manager_id'],
                    'firstname' => (string)$group['manager_firstname'],
                    'lastname' => (string)$group['manager_lastname'],
                    'username' => (string)$group['manager_username'],
                    'email' => (string)$group['manager_email'],
                ];
            }

            $arrivalDate = null;
            if ($arrivalInput !== '') {
                $arrivalDate = $this->normalizeDate($arrivalInput);
                if ($arrivalDate === null) {
                    $errors[] = 'Date d’arrivée invalide (AAAA-MM-JJ ou JJ/MM/AAAA).';
                }
            }

            $active = $this->normalizeBoolean($activeInput);
            if ($active === null) {
                $errors[] = 'Valeur « actif » invalide (1/0, oui/non, true/false).';
                $active = true;
            }
            $mustChange = $this->normalizeBoolean($forceInput);
            if ($mustChange === null) {
                $errors[] = 'Valeur « forcer_changement_mot_de_passe » invalide.';
                $mustChange = true;
            }

            if ($password !== '' && mb_strlen($password) < 12) {
                $errors[] = 'Le mot de passe fourni doit contenir au moins 12 caractères.';
            }

            if ($username !== '') {
                if (isset($existing['usernames'][$username])) {
                    $errors[] = 'Identifiant déjà utilisé dans TicketFlow.';
                }
                if (isset($seenUsernames[$username])) {
                    $errors[] = 'Identifiant présent plusieurs fois dans le fichier.';
                }
                $seenUsernames[$username] = true;
            }
            if ($email !== '') {
                if (isset($existing['emails'][$email])) {
                    $errors[] = 'E-mail déjà utilisé dans TicketFlow.';
                }
                if (isset($seenEmails[$email])) {
                    $errors[] = 'E-mail présent plusieurs fois dans le fichier.';
                }
                $seenEmails[$email] = true;
            }

            $valid = $errors === [];
            $valid ? $validCount++ : $invalidCount++;
            $result[] = [
                'line' => $line,
                'valid' => $valid,
                'errors' => $errors,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
                'email' => $email,
                'role_id' => $role !== null ? (int)$role['id'] : null,
                'role_name' => $role !== null ? (string)$role['display'] : $roleInput,
                'group_id' => $group !== null ? (int)$group['id'] : null,
                'group_name' => $group !== null ? (string)$group['name'] : ($groupInput !== '' ? $groupInput : 'Non attribué'),
                'manager_name' => $manager !== null ? trim($manager['firstname'] . ' ' . $manager['lastname']) : '—',
                'arrival_date' => $arrivalDate,
                'active' => (bool)$active,
                'password' => $password,
                'must_change_password' => (bool)$mustChange,
            ];
        }

        return ['rows' => $result, 'valid' => $validCount, 'invalid' => $invalidCount];
    }

    /**
     * @param list<array<string,mixed>> $validatedRows
     * @return array{created:int,credentials:list<array{username:string,password:string,generated:bool}>}
     */
    public function import(array $validatedRows, int $actorId): array
    {
        $validRows = array_values(array_filter($validatedRows, static fn(array $row): bool => !empty($row['valid'])));
        if ($validRows === []) {
            throw new RuntimeException('Aucune ligne valide à importer.');
        }

        $this->pdo->beginTransaction();
        $credentials = [];
        try {
            $checkUsername = $this->pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $checkEmail = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $insert = $this->pdo->prepare(
                'INSERT INTO users (firstname, lastname, username, email, password_hash, role_id, group_id, arrival_date, active, must_change_password, password_changed_at)
                 VALUES (:firstname, :lastname, :username, :email, :password_hash, :role_id, :group_id, :arrival_date, :active, :must_change_password, NOW())'
            );

            foreach ($validRows as $row) {
                $checkUsername->execute(['username' => $row['username']]);
                if ($checkUsername->fetch()) {
                    throw new RuntimeException('L’identifiant « ' . $row['username'] . ' » vient d’être créé. Relancez la prévisualisation.');
                }
                $checkEmail->execute(['email' => $row['email']]);
                if ($checkEmail->fetch()) {
                    throw new RuntimeException('L’e-mail « ' . $row['email'] . ' » vient d’être utilisé. Relancez la prévisualisation.');
                }

                $providedPassword = (string)($row['password'] ?? '');
                $password = $providedPassword !== '' ? $providedPassword : $this->generatePassword();
                $insert->execute([
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role_id' => $row['role_id'],
                    'group_id' => $row['group_id'],
                    'arrival_date' => $row['arrival_date'] ?: null,
                    'active' => !empty($row['active']) ? 1 : 0,
                    'must_change_password' => !empty($row['must_change_password']) ? 1 : 0,
                ]);
                $newId = (int)$this->pdo->lastInsertId();
                $this->auditService->log($actorId, 'user_import_created', 'user', $newId, [
                    'email' => $row['email'],
                    'role_id' => $row['role_id'],
                    'group_id' => $row['group_id'],
                    'import_line' => $row['line'],
                ]);
                $credentials[] = [
                    'username' => (string)$row['username'],
                    'password' => $password,
                    'generated' => $providedPassword === '',
                ];
            }

            $this->auditService->log($actorId, 'users_imported', 'user_import', null, [
                'created' => count($credentials),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['created' => count($credentials), 'credentials' => $credentials];
    }

    /** @return list<array<string,string>> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Impossible de lire le fichier CSV.');
        }
        $sample = fgets($handle);
        rewind($handle);
        if ($sample === false) {
            fclose($handle);
            throw new RuntimeException('Le fichier CSV est vide.');
        }
        $delimiter = substr_count($sample, ';') >= substr_count($sample, ',') ? ';' : ',';
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('En-tête CSV introuvable.');
        }
        $headers = $this->normalizeHeaders($header);
        $this->assertRequiredHeaders($headers);
        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isEmptyRow($values)) {
                continue;
            }
            $rows[] = $this->combineRow($headers, $values);
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw new RuntimeException('L’import est limité à ' . self::MAX_ROWS . ' utilisateurs par fichier.');
            }
        }
        fclose($handle);
        if ($rows === []) {
            throw new RuntimeException('Le fichier ne contient aucun utilisateur.');
        }
        return $rows;
    }

    /** @return list<array<string,string>> */
    private function parseXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est requise pour lire les fichiers XLSX.');
        }
        if (!class_exists(\DOMDocument::class)) {
            throw new RuntimeException('L’extension PHP XML/DOM est requise pour lire les fichiers XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Le fichier XLSX est invalide ou endommagé.');
        }

        try {
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false || strlen($sheetXml) > 5 * 1024 * 1024) {
                throw new RuntimeException('La première feuille Excel est absente ou trop volumineuse.');
            }

            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                if (strlen($sharedXml) > 5 * 1024 * 1024) {
                    throw new RuntimeException('Le dictionnaire Excel est trop volumineux.');
                }
                $shared = $this->readSharedStringsDom($sharedXml);
            }
        } finally {
            $zip->close();
        }

        $dom = $this->loadDom($sheetXml, 'Impossible de lire la première feuille Excel.');
        $xpath = new \DOMXPath($dom);
        $rowNodes = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($rowNodes === false) {
            throw new RuntimeException('Impossible de lire les lignes du fichier Excel.');
        }

        $matrix = [];
        foreach ($rowNodes as $rowNode) {
            if (!$rowNode instanceof \DOMElement) {
                continue;
            }
            $cells = [];
            $cellNodes = $xpath->query('./*[local-name()="c"]', $rowNode);
            if ($cellNodes === false) {
                continue;
            }
            foreach ($cellNodes as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }
                $ref = strtoupper(trim($cell->getAttribute('r')));
                $column = $this->columnIndex($ref);
                $type = trim($cell->getAttribute('t'));
                $cells[$column] = $this->xlsxCellValueDom($xpath, $cell, $type, $shared);
            }

            if ($cells !== []) {
                $max = max(array_keys($cells));
                $values = [];
                for ($i = 0; $i <= $max; $i++) {
                    $values[] = (string)($cells[$i] ?? '');
                }
                if (!$this->isEmptyRow($values)) {
                    $matrix[] = $values;
                }
            }

            if (count($matrix) > self::MAX_ROWS + 1) {
                throw new RuntimeException('L’import est limité à ' . self::MAX_ROWS . ' utilisateurs par fichier.');
            }
        }

        if (count($matrix) < 2) {
            throw new RuntimeException('Le fichier Excel ne contient aucun utilisateur.');
        }

        $headers = $this->normalizeHeaders(array_shift($matrix));
        $this->assertRequiredHeaders($headers);

        $rows = [];
        foreach ($matrix as $values) {
            if ($this->isEmptyRow($values)) {
                continue;
            }
            $rows[] = $this->combineRow($headers, $values);
        }
        if ($rows === []) {
            throw new RuntimeException('Le fichier Excel ne contient aucun utilisateur.');
        }
        return $rows;
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return $fallback;
        }

        try {
            $workbook = $this->loadDom($workbookXml, 'Classeur Excel illisible.');
            $workbookXpath = new \DOMXPath($workbook);
            $sheet = $workbookXpath->query('//*[local-name()="sheets"]/*[local-name()="sheet"][1]')?->item(0);
            if (!$sheet instanceof \DOMElement) {
                return $fallback;
            }

            $relationId = '';
            foreach ($sheet->attributes as $attribute) {
                if ($attribute->localName === 'id') {
                    $relationId = trim($attribute->nodeValue ?? '');
                    break;
                }
            }
            if ($relationId === '') {
                return $fallback;
            }

            $rels = $this->loadDom($relsXml, 'Relations du classeur Excel illisibles.');
            $relsXpath = new \DOMXPath($rels);
            $relationship = $relsXpath->query('//*[local-name()="Relationship" and @Id=' . $this->xpathLiteral($relationId) . ']')?->item(0);
            if (!$relationship instanceof \DOMElement) {
                return $fallback;
            }

            $target = str_replace('\\', '/', trim($relationship->getAttribute('Target')));
            if ($target === '') {
                return $fallback;
            }
            if (str_starts_with($target, '/')) {
                $target = ltrim($target, '/');
            } elseif (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . ltrim($target, '/');
            }

            $parts = [];
            foreach (explode('/', $target) as $part) {
                if ($part === '' || $part === '.') continue;
                if ($part === '..') {
                    array_pop($parts);
                    continue;
                }
                $parts[] = $part;
            }
            $resolved = implode('/', $parts);
            return $resolved !== '' ? $resolved : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /** @param list<string> $shared */
    private function xlsxCellValueDom(\DOMXPath $xpath, \DOMElement $cell, string $type, array $shared): string
    {
        if ($type === 'inlineStr') {
            $nodes = $xpath->query('.//*[local-name()="is"]//*[local-name()="t"]', $cell);
            $text = '';
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $text .= $node->textContent;
                }
            }
            return $text;
        }

        $valueNode = $xpath->query('./*[local-name()="v"][1]', $cell)?->item(0);
        $raw = $valueNode !== null ? trim($valueNode->textContent) : '';

        if ($type === 's') {
            if ($raw === '' || !ctype_digit($raw)) {
                return '';
            }
            return (string)($shared[(int)$raw] ?? '');
        }

        if ($type === 'str' || $type === 'e' || $type === 'b' || $type === '' || $type === 'n') {
            return $type === 'b' ? ($raw === '1' ? '1' : '0') : $raw;
        }

        return $raw;
    }

    /** @return list<string> */
    private function readSharedStringsDom(string $xmlString): array
    {
        $dom = $this->loadDom($xmlString, 'Impossible de lire les chaînes partagées Excel.');
        $xpath = new \DOMXPath($dom);
        $items = $xpath->query('//*[local-name()="si"]');
        $result = [];
        if ($items === false) {
            return $result;
        }
        foreach ($items as $item) {
            $parts = $xpath->query('.//*[local-name()="t"]', $item);
            $text = '';
            if ($parts !== false) {
                foreach ($parts as $part) {
                    $text .= $part->textContent;
                }
            }
            $result[] = $text;
        }
        return $result;
    }

    private function loadDom(string $content, string $error): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException($error);
        }
        return $dom;
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }
        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }
        $parts = explode("'", $value);
        return 'concat(' . implode(', "\'", ', array_map(static fn(string $part): string => "'" . $part . "'", $parts)) . ')';
    }


    /**
     * Lit une cellule XLSX de façon compatible avec les fichiers générés par
     * TicketFlow ET ceux réenregistrés par Microsoft Excel.
     *
     * Microsoft Excel utilise très souvent sharedStrings (t="s") alors que
     * TicketFlow génère des inline strings (t="inlineStr"). Les enfants <v>,
     * <is> et <t> appartiennent au namespace SpreadsheetML et ne doivent pas
     * être lus directement via $cell->v.
     *
     * @param list<string> $shared
     */
    private function xlsxCellValue(SimpleXMLElement $cell, string $type, array $shared): string
    {
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $children = $cell->children($namespace);

        if ($type === 'inlineStr') {
            $cell->registerXPathNamespace('x', $namespace);
            $parts = $cell->xpath('.//x:is//x:t') ?: [];
            $text = '';
            foreach ($parts as $part) {
                $text .= (string)$part;
            }
            return $text;
        }

        $raw = isset($children->v) ? (string)$children->v : '';

        if ($type === 's') {
            if ($raw === '' || !ctype_digit(trim($raw))) {
                return '';
            }
            return (string)($shared[(int)trim($raw)] ?? '');
        }

        if ($type === 'str') {
            return $raw;
        }

        if ($type === 'b') {
            return trim($raw) === '1' ? '1' : '0';
        }

        return $raw;
    }

    /** @return array<string,array{id:int,name:string,display:string}> */
    private function loadRoles(): array
    {
        $roles = [];
        foreach ($this->pdo->query('SELECT id, name FROM roles')->fetchAll() as $row) {
            $display = $row['name'] === 'IT' ? 'Support IT' : (string)$row['name'];
            foreach ([(string)$row['name'], $display] as $alias) {
                $roles[$this->normalizeLookup($alias)] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'display' => $display];
            }
        }
        if (isset($roles['administrateur'])) $roles['admin'] = $roles['administrateur'];
        if (isset($roles['it'])) {
            $roles['support'] = $roles['it'];
            $roles['supportit'] = $roles['it'];
        }
        if (isset($roles['manager'])) $roles['manageur'] = $roles['manager'];
        if (isset($roles['collaborateur'])) $roles['collaborator'] = $roles['collaborateur'];
        return $roles;
    }

    /** @return array<string,array<string,mixed>> */
    private function loadGroups(): array
    {
        $stmt = $this->pdo->query(
            "SELECT g.id, g.name, g.active, g.manager_id,
                    m.firstname AS manager_firstname, m.lastname AS manager_lastname,
                    m.username AS manager_username, m.email AS manager_email
             FROM groups_company g
             LEFT JOIN users m ON m.id = g.manager_id"
        );
        $groups = [];
        foreach ($stmt->fetchAll() as $row) {
            $groups[$this->normalizeLookup((string)$row['name'])] = $row;
        }
        return $groups;
    }

    /** @return list<array<string,mixed>> */
    private function loadManagers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT u.id, u.firstname, u.lastname, u.username, u.email
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'Manager' AND u.active = 1"
        );
        return $stmt->fetchAll();
    }

    /** @return array{usernames:array<string,bool>,emails:array<string,bool>} */
    private function loadExistingUsers(): array
    {
        $usernames = [];
        $emails = [];
        foreach ($this->pdo->query('SELECT username, email FROM users')->fetchAll() as $row) {
            $usernames[mb_strtolower((string)$row['username'])] = true;
            $emails[mb_strtolower((string)$row['email'])] = true;
        }
        return ['usernames' => $usernames, 'emails' => $emails];
    }

    /** @param list<array<string,mixed>> $managers */
    private function resolveManager(string $value, array $managers): ?array
    {
        $needle = $this->normalizeLookup($value);
        $matches = [];
        foreach ($managers as $manager) {
            $aliases = [
                (string)$manager['username'],
                (string)$manager['email'],
                trim((string)$manager['firstname'] . ' ' . (string)$manager['lastname']),
            ];
            foreach ($aliases as $alias) {
                if ($this->normalizeLookup($alias) === $needle) {
                    $matches[(int)$manager['id']] = $manager;
                }
            }
        }
        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    private function normalizeDate(string $value): ?string
    {
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $serial = (int)floor((float)$value);
            if ($serial >= 20000 && $serial <= 80000) {
                return (new DateTimeImmutable('1899-12-30'))->modify('+' . $serial . ' days')->format('Y-m-d');
            }
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }

    private function normalizeBoolean(string $value): ?bool
    {
        $value = $this->normalizeLookup($value);
        if (in_array($value, ['', '1', 'oui', 'yes', 'true', 'actif', 'active'], true)) return true;
        if (in_array($value, ['0', 'non', 'no', 'false', 'inactif', 'inactive'], true)) return false;
        return null;
    }

    private function generatePassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#%+-_';
        $all = $upper . $lower . $digits . $symbols;
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];
        while (count($chars) < 18) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /** @param list<string> $headers */
    private function assertRequiredHeaders(array $headers): void
    {
        foreach (['prenom', 'nom', 'identifiant', 'email', 'role'] as $required) {
            if (!in_array($required, $headers, true)) {
                $detected = array_values(array_filter($headers, static fn(string $header): bool => $header !== ''));
                $detail = $detected !== [] ? ' Colonnes détectées : ' . implode(', ', $detected) . '.' : ' Aucune colonne lisible n’a été détectée.';
                throw new RuntimeException('Colonne obligatoire absente : ' . $required . '.' . $detail);
            }
        }
    }

    /** @param list<mixed> $headers @return list<string> */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn($value) => $this->normalizeHeader((string)$value), $headers);
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D"], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
        $value = mb_strtolower(trim($value));
        $map = ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a','ù'=>'u','û'=>'u','ü'=>'u','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ç'=>'c','’'=>'_','\''=>'_',' '=>'_','-'=>'_'];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9_]+/', '', $value) ?? $value;
        $aliases = [
            'first_name'=>'prenom','firstname'=>'prenom','prenom'=>'prenom',
            'last_name'=>'nom','lastname'=>'nom','nom'=>'nom',
            'username'=>'identifiant','login'=>'identifiant','identifiant'=>'identifiant',
            'mail'=>'email','e_mail'=>'email','email'=>'email',
            'role'=>'role','groupe'=>'groupe','group'=>'groupe','manager'=>'manager','manageur'=>'manager',
            'date_arrivee'=>'date_arrivee','date_d_arrivee'=>'date_arrivee','arrival_date'=>'date_arrivee',
            'actif'=>'actif','active'=>'actif',
            'mot_de_passe'=>'mot_de_passe','password'=>'mot_de_passe',
            'forcer_changement_mot_de_passe'=>'forcer_changement_mot_de_passe','must_change_password'=>'forcer_changement_mot_de_passe',
        ];
        return $aliases[$value] ?? $value;
    }

    private function normalizeLookup(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a','ù'=>'u','û'=>'u','ü'=>'u','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ç'=>'c']);
        return preg_replace('/[^a-z0-9@._-]+/', '', $value) ?? $value;
    }

    /** @param list<string> $headers @param list<mixed> $values @return array<string,string> */
    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header === '') continue;
            $row[$header] = trim((string)($values[$index] ?? ''));
        }
        return $row;
    }

    /** @param list<mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string)$value) !== '') return false;
        }
        return true;
    }

    /** @return list<string> */
    private function readSharedStrings(string $xmlString): array
    {
        $xml = $this->loadXml($xmlString, 'Impossible de lire les chaînes partagées Excel.');
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $items = $xml->xpath('//x:si') ?: [];
        $result = [];
        foreach ($items as $item) {
            $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $item->xpath('.//x:t') ?: [];
            $text = '';
            foreach ($parts as $part) $text .= (string)$part;
            $result[] = $text;
        }
        return $result;
    }

    private function inlineString(SimpleXMLElement $cell): string
    {
        $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = $cell->xpath('.//x:is//x:t') ?: [];
        $text = '';
        foreach ($parts as $part) $text .= (string)$part;
        return $text;
    }

    private function loadXml(string $content, string $error): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) throw new RuntimeException($error);
        return $xml;
    }

    private function columnIndex(string $cellRef): int
    {
        if (!preg_match('/^([A-Z]+)/', $cellRef, $m)) return 0;
        $number = 0;
        foreach (str_split($m[1]) as $char) {
            $number = $number * 26 + (ord($char) - 64);
        }
        return max(0, $number - 1);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille autorisée.',
            UPLOAD_ERR_PARTIAL => 'Le transfert du fichier est incomplet.',
            UPLOAD_ERR_NO_FILE => 'Sélectionnez un fichier CSV ou XLSX.',
            default => 'Le fichier n’a pas pu être envoyé.',
        };
    }
}
