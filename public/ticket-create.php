<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Collaborateur', 'Manager');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Créer un ticket';

$types = $pdo->query('SELECT id, code, name FROM ticket_types ORDER BY id')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM ticket_categories WHERE active = 1 ORDER BY name')->fetchAll();
$priorities = $pdo->query('SELECT id, name, level FROM priorities ORDER BY level')->fetchAll();

$form = [
    'type_id' => '',
    'category_id' => '',
    'priority_id' => '',
    'title' => '',
    'description' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    }

    $form['type_id'] = (int) ($_POST['type_id'] ?? 0);
    $form['category_id'] = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
    $form['priority_id'] = (int) ($_POST['priority_id'] ?? 0);
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));

    $typeIds = array_map(static fn(array $row): int => (int) $row['id'], $types);
    $categoryIds = array_map(static fn(array $row): int => (int) $row['id'], $categories);
    $priorityIds = array_map(static fn(array $row): int => (int) $row['id'], $priorities);

    if (!in_array((int) $form['type_id'], $typeIds, true)) {
        $errors[] = 'Sélectionnez un type de demande valide.';
    }
    if ($form['category_id'] !== null && !in_array((int) $form['category_id'], $categoryIds, true)) {
        $errors[] = 'La catégorie sélectionnée n’est pas valide.';
    }
    if (!in_array((int) $form['priority_id'], $priorityIds, true)) {
        $errors[] = 'Sélectionnez un niveau d’importance valide.';
    }

    $titleLength = mb_strlen($form['title']);
    if ($titleLength < 5 || $titleLength > 80) {
        $errors[] = 'Le titre doit contenir entre 5 et 80 caractères.';
    }

    $descriptionLength = mb_strlen($form['description']);
    if ($descriptionLength < 10 || $descriptionLength > 2000) {
        $errors[] = 'La description doit contenir entre 10 et 2000 caractères.';
    }

    if (!$errors) {
        try {
            $ticketNumber = $ticketService->create($form, (int) $user['id']);
            $_SESSION['flash_success'] = 'Le ticket ' . $ticketNumber . ' a été créé avec succès.';
            header('Location: ticket.php?number=' . urlencode($ticketNumber));
            exit;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            $logDir = __DIR__ . '/../storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0770, true);
            }
            @error_log(
                '[' . date('c') . '] Création ticket : ' . $e->getMessage() . PHP_EOL,
                3,
                $logDir . '/ticket-errors.log'
            );
            $environment = (string) ($config['app']['environment'] ?? 'production');
            $errors[] = $environment === 'development'
                ? 'Erreur technique lors de la création : ' . $e->getMessage()
                : 'Impossible de créer le ticket pour le moment.';
        }
    }
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced ticket-create-heading-v0135">
    <div>
        <span class="badge"><i class="fa-solid fa-plus"></i> Nouvelle demande</span>
        <h1>Créer un ticket</h1>
        <p>Donnez à l’équipe IT les informations essentielles pour accélérer le diagnostic et la prise en charge.</p>
    </div>
    <a class="btn secondary" href="my-tickets.php"><i class="fa-solid fa-arrow-left"></i> Mes tickets</a>
</section>

<?php if ($errors): ?>
    <div class="alert error"><strong>Le formulaire contient des erreurs :</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="ticket-create-layout-v0135">
    <section class="panel form-panel ticket-create-card-v0135">
        <div class="form-section-heading">
            <span class="form-section-icon"><i class="fa-solid fa-ticket"></i></span>
            <div><h2>Votre demande</h2><p>Les champs marqués d’un * sont obligatoires.</p></div>
        </div>
        <form method="post" class="form-grid ticket-create-form-v0135" id="ticket-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field">
                <label for="type_id"><i class="fa-solid fa-layer-group"></i> Type de demande *</label>
                <select id="type_id" name="type_id" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= (int) $form['type_id'] === (int) $type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['code'] . ' — ' . $type['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category_id"><i class="fa-solid fa-tags"></i> Catégorie</label>
                <select id="category_id" name="category_id"><option value="">Non définie</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($form['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field span-2 ticket-priority-field">
                <label><i class="fa-solid fa-gauge-high"></i> Importance *</label>
                <div class="priority-choice-grid priority-choice-grid-v0135">
                    <?php foreach ($priorities as $priority): ?>
                        <label class="priority-choice priority-level-<?= (int) $priority['level'] ?>">
                            <input type="radio" name="priority_id" value="<?= (int) $priority['id'] ?>" <?= (int) $form['priority_id'] === (int) $priority['id'] ? 'checked' : '' ?> required>
                            <span class="priority-choice-icon"><i class="fa-solid <?= match ((int)$priority['level']) {1=>'fa-leaf',2=>'fa-circle-info',3=>'fa-triangle-exclamation',default=>'fa-fire'} ?>"></i></span>
                            <span><strong><?= htmlspecialchars($priority['name']) ?></strong><small><?= match ((int) $priority['level']) { 1 => 'Peu bloquant', 2 => 'Gêne dans le travail', 3 => 'Activité fortement impactée', default => 'Blocage majeur / sécurité' } ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="field-hint"><i class="fa-solid fa-circle-info"></i> L’équipe IT pourra ajuster la priorité après analyse.</small>
            </div>
            <div class="field span-2">
                <label for="title"><i class="fa-solid fa-heading"></i> Titre *</label>
                <input id="title" name="title" minlength="5" maxlength="80" required value="<?= htmlspecialchars((string) $form['title']) ?>" placeholder="Ex. Outlook ne démarre plus">
                <div class="field-counter"><span>Décrivez le problème en quelques mots.</span><strong><span id="title-count"><?= mb_strlen((string) $form['title']) ?></span> / 80</strong></div>
            </div>
            <div class="field span-2">
                <label for="description"><i class="fa-solid fa-align-left"></i> Description *</label>
                <textarea id="description" name="description" minlength="10" maxlength="2000" required rows="9" placeholder="Expliquez le problème, depuis quand il est présent et les tests déjà effectués…"><?= htmlspecialchars((string) $form['description']) ?></textarea>
                <div class="field-counter"><span>Plus la description est précise, plus le diagnostic sera rapide.</span><strong><span id="description-count"><?= mb_strlen((string) $form['description']) ?></span> / 2000</strong></div>
            </div>
            <div class="form-actions span-2 ticket-create-actions-v0135">
                <a class="btn ghost" href="dashboard.php"><i class="fa-solid fa-xmark"></i> Annuler</a>
                <button class="btn primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Créer le ticket</button>
            </div>
        </form>
    </section>
    <aside class="ticket-create-help-v0135">
        <section class="panel ticket-create-help-card">
            <span class="help-icon"><i class="fa-solid fa-lightbulb"></i></span>
            <div><h2>Pour une prise en charge plus rapide</h2><p>Indiquez le message d’erreur, le moment où le problème a commencé et ce que vous avez déjà essayé.</p></div>
        </section>
        <section class="panel ticket-create-help-card">
            <span class="help-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div><h2>Ne partagez jamais de mot de passe</h2><p>TicketFlow peut stocker vos identifiants applicatifs, mais jamais vos mots de passe.</p></div>
        </section>
    </aside>
</div>
<script>
for (const [id, counter] of [['title','title-count'], ['description','description-count']]) {
    const field = document.getElementById(id);
    const output = document.getElementById(counter);
    field.addEventListener('input', () => output.textContent = field.value.length);
}
</script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
