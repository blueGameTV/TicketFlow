<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Importer des utilisateurs';

$templateHeaders = ['prenom','nom','identifiant','email','role','groupe','manager','date_arrivee','actif','mot_de_passe','forcer_changement_mot_de_passe'];
$templateRow = ['Jean','Dupont','jdupont','jean.dupont@example.fr','Collaborateur','Commerce','manager.commerce','01/10/2026','oui','','oui'];

if (isset($_GET['template'])) {
    $format = (string)$_GET['template'];
    if ($format === 'csv') {
        $filename = 'ticketflow-modele-import-utilisateurs.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, $templateHeaders, ';');
        fputcsv($out, $templateRow, ';');
        fclose($out);
        exit;
    }
    if ($format === 'xlsx') {
        $tmp = $excelExportService->createXlsx('Import utilisateurs', $templateHeaders, [$templateRow]);
        $excelExportService->sendDownload($tmp, 'ticketflow-modele-import-utilisateurs.xlsx');
    }
}

$errors = [];
$preview = null;
$importResult = $_SESSION['user_import_result'] ?? null;
unset($_SESSION['user_import_result']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    } else {
        $action = (string)($_POST['action'] ?? 'preview');
        try {
            if ($action === 'preview') {
                $rawRows = $userImportService->parseUpload($_FILES['users_file'] ?? []);
                $preview = $userImportService->validateRows($rawRows);
                $_SESSION['user_import_preview'] = [
                    'created_at' => time(),
                    'raw_rows' => $rawRows,
                ];
            } elseif ($action === 'confirm') {
                $stored = $_SESSION['user_import_preview'] ?? null;
                if (!is_array($stored) || !isset($stored['raw_rows']) || time() - (int)($stored['created_at'] ?? 0) > 1800) {
                    throw new RuntimeException('La prévisualisation a expiré. Importez à nouveau votre fichier.');
                }
                $preview = $userImportService->validateRows($stored['raw_rows']);
                if ($preview['valid'] < 1) {
                    throw new RuntimeException('Aucune ligne valide à importer.');
                }
                $result = $userImportService->import($preview['rows'], (int)$user['id']);
                unset($_SESSION['user_import_preview']);
                $_SESSION['user_import_result'] = $result;
                header('Location: admin-user-import.php');
                exit;
            } elseif ($action === 'cancel') {
                unset($_SESSION['user_import_preview']);
                header('Location: admin-user-import.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced v120-import-heading">
    <div>
        <span class="badge"><i class="fa-solid fa-file-import"></i> Administration</span>
        <h1>Importer des utilisateurs</h1>
        <p>Créez plusieurs comptes depuis un fichier CSV ou Excel, avec rattachement aux groupes et contrôle du Manager.</p>
    </div>
    <a class="btn secondary" href="admin-users.php"><i class="fa-solid fa-arrow-left"></i> Retour aux utilisateurs</a>
</section>

<?php if ($errors): ?>
    <div class="alert error"><strong>Import impossible :</strong><ul><?php foreach ($errors as $message): ?><li><?= htmlspecialchars($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (is_array($importResult)): ?>
<section class="panel v120-import-result">
    <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-circle-check"></i></span><div><h2><?= (int)$importResult['created'] ?> compte<?= (int)$importResult['created'] > 1 ? 's' : '' ?> créé<?= (int)$importResult['created'] > 1 ? 's' : '' ?></h2><p>Les mots de passe ci-dessous ne sont affichés qu’après cet import. Copiez-les avant de quitter la page.</p></div></div>
    <div class="table-wrap">
        <table class="v120-import-credentials"><thead><tr><th>Identifiant</th><th>Mot de passe initial</th><th>Origine</th></tr></thead><tbody>
        <?php foreach ($importResult['credentials'] as $credential): ?>
            <tr><td><strong><?= htmlspecialchars($credential['username']) ?></strong></td><td><code><?= htmlspecialchars($credential['password']) ?></code></td><td><?= !empty($credential['generated']) ? 'Généré par TicketFlow' : 'Fourni dans le fichier' ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="v120-import-security-note"><i class="fa-solid fa-shield-halved"></i><span>Transmettez les mots de passe par un canal approprié. Par défaut, le modèle force leur modification à la première connexion.</span></div>
</section>
<?php endif; ?>

<section class="v120-import-grid">
    <article class="panel v120-import-upload">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span><div><h2>1. Charger le fichier</h2><p>Formats pris en charge : CSV et XLSX · 500 utilisateurs maximum · 5 Mo maximum.</p></div></div>
        <form method="post" enctype="multipart/form-data" class="v120-import-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="preview">
            <div class="field"><label for="users_file">Fichier utilisateurs *</label><input class="file-input" id="users_file" type="file" name="users_file" accept=".csv,.xlsx" required><small>Utilisez de préférence le modèle TicketFlow pour éviter les erreurs de colonnes.</small></div>
            <button class="btn primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Analyser et prévisualiser</button>
        </form>
    </article>
    <article class="panel v120-import-template">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-file-arrow-down"></i></span><div><h2>2. Modèles TicketFlow</h2><p>Les deux modèles contiennent les mêmes colonnes et un exemple de ligne.</p></div></div>
        <div class="v120-template-actions"><a class="btn secondary" href="admin-user-import.php?template=csv"><i class="fa-solid fa-file-csv"></i> Modèle CSV</a><a class="btn secondary" href="admin-user-import.php?template=xlsx"><i class="fa-solid fa-file-excel"></i> Modèle Excel</a></div>
        <div class="v120-import-columns"><strong>Colonnes obligatoires</strong><span>prenom · nom · identifiant · email · role</span><strong>Colonnes facultatives</strong><span>groupe · manager · date_arrivee · actif · mot_de_passe · forcer_changement_mot_de_passe</span></div>
    </article>
</section>

<section class="panel v120-import-rules">
    <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-circle-info"></i></span><div><h2>Règles de rattachement</h2><p>TicketFlow vérifie les données avant de créer le moindre compte.</p></div></div>
    <div class="v120-rule-grid">
        <div><i class="fa-solid fa-users"></i><strong>Groupe</strong><span>Le nom doit correspondre à un groupe actif déjà présent dans TicketFlow.</span></div>
        <div><i class="fa-solid fa-user-tie"></i><strong>Manager</strong><span>Facultatif. S’il est renseigné, il doit correspondre au Manager du groupe. Sinon TicketFlow récupère automatiquement le Manager du groupe.</span></div>
        <div><i class="fa-solid fa-key"></i><strong>Mot de passe</strong><span>Facultatif. S’il est vide, TicketFlow génère automatiquement un mot de passe initial sécurisé.</span></div>
        <div><i class="fa-solid fa-shield-halved"></i><strong>Sécurité</strong><span>Le changement du mot de passe est forcé par défaut à la première connexion.</span></div>
    </div>
</section>

<?php if (is_array($preview)): ?>
<section class="panel v120-import-preview">
    <div class="v120-import-preview-head">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-table-list"></i></span><div><h2>Prévisualisation</h2><p>Aucun compte n’a encore été créé.</p></div></div>
        <div class="v120-import-counters"><span class="is-valid"><strong><?= (int)$preview['valid'] ?></strong> valide<?= (int)$preview['valid'] > 1 ? 's' : '' ?></span><span class="is-invalid"><strong><?= (int)$preview['invalid'] ?></strong> erreur<?= (int)$preview['invalid'] > 1 ? 's' : '' ?></span></div>
    </div>
    <div class="table-wrap v120-import-table-wrap">
        <table class="v120-import-table">
            <thead><tr><th>Ligne</th><th>État</th><th>Utilisateur</th><th>Rôle</th><th>Groupe</th><th>Manager</th><th>Arrivée</th><th>Détails</th></tr></thead>
            <tbody><?php foreach ($preview['rows'] as $row): ?>
                <tr class="<?= $row['valid'] ? 'is-valid' : 'is-invalid' ?>">
                    <td>#<?= (int)$row['line'] ?></td>
                    <td><span class="v120-row-status <?= $row['valid'] ? 'valid' : 'invalid' ?>"><i class="fa-solid <?= $row['valid'] ? 'fa-check' : 'fa-xmark' ?>"></i><?= $row['valid'] ? 'Prêt' : 'Erreur' ?></span></td>
                    <td><strong><?= htmlspecialchars(trim($row['firstname'].' '.$row['lastname'])) ?></strong><small>@<?= htmlspecialchars($row['username']) ?> · <?= htmlspecialchars($row['email']) ?></small></td>
                    <td><?= htmlspecialchars($row['role_name']) ?></td><td><?= htmlspecialchars($row['group_name']) ?></td><td><?= htmlspecialchars($row['manager_name']) ?></td><td><?= $row['arrival_date'] ? htmlspecialchars(date('d/m/Y', strtotime($row['arrival_date']))) : '—' ?></td>
                    <td><?php if ($row['errors']): ?><ul class="v120-row-errors"><?php foreach ($row['errors'] as $message): ?><li><?= htmlspecialchars($message) ?></li><?php endforeach; ?></ul><?php else: ?><span class="muted"><?= $row['password'] === '' ? 'Mot de passe généré automatiquement' : 'Mot de passe fourni' ?> · <?= $row['active'] ? 'Actif' : 'Désactivé' ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <div class="v120-import-actions">
        <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="cancel"><button class="btn ghost" type="submit">Annuler la prévisualisation</button></form>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="confirm"><button class="btn primary" type="submit" <?= (int)$preview['valid'] < 1 ? 'disabled' : '' ?>><i class="fa-solid fa-user-plus"></i> Importer <?= (int)$preview['valid'] ?> ligne<?= (int)$preview['valid'] > 1 ? 's' : '' ?> valide<?= (int)$preview['valid'] > 1 ? 's' : '' ?></button></form>
    </div>
    <?php if ((int)$preview['invalid'] > 0): ?><p class="v120-import-skip-note"><i class="fa-solid fa-circle-info"></i> Les lignes en erreur seront ignorées. Corrigez le fichier puis relancez une prévisualisation si vous souhaitez toutes les importer.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
