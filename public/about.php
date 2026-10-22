<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.about');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrf->validate($_POST['csrf_token'] ?? null)) {
    $releaseNoteService->markSeen((int)$user['id']);
    $_SESSION['flash_success'] = t('about.marked_seen');
    header('Location: about.php#nouveautes');
    exit;
}
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$version = $releaseNoteService->currentVersion();
$releases = $releaseNoteService->releases();
if ($translator->locale() === 'en') {
    $releaseTranslations = [
        'TicketFlow v1.3.1 — Corrections de l’interface' => 'TicketFlow v1.3.1 — Interface fixes',
        'Correction de l’alignement du badge BETA dans la configuration e-mail.' => 'Fixed BETA badge alignment in email configuration.',
        'Suppression de l’indicateur « Compte actif » dans les paramètres.' => 'Removed the Active account indicator from settings.',
        'Réorganisation et harmonisation des cartes de la page Paramètres.' => 'Reorganized and unified the cards on the Settings page.',
        'Amélioration des espacements et de l’affichage sur les petits écrans.' => 'Improved spacing and layout on smaller screens.',
        'Aucune migration de base de données nécessaire.' => 'No database migration required.',
        'TicketFlow v1.3.0 — Interface, sécurité et assistance' => 'TicketFlow v1.3.0 — Interface, security and support',
        'Nouvelle interface de connexion, adaptée aux thèmes clair et sombre.' => 'New login interface adapted to light and dark themes.',
        'Harmonisation des styles et amélioration des pages, formulaires et menus.' => 'Unified styles and improved pages, forms and menus.',
        'Récupération de mot de passe par e-mail ou avec un code validé par le Support IT.' => 'Password recovery by email or with an IT Support-approved code.',
        'Catalogue des codes d’erreur techniques accessible aux Administrateurs et au Support IT.' => 'Technical error code catalog for Administrators and IT Support.',
        'Amélioration de la gestion et de l’affichage des maintenances.' => 'Improved maintenance management and display.',
        'Améliorations des traductions françaises et anglaises.' => 'Improved French and English translations.',
        'Alertes de service et suivi des incidents.' => 'Service alerts and incident tracking.',
        'Mode maintenance immédiat et maintenances planifiées.' => 'Immediate maintenance mode and scheduled maintenance windows.',
        'Historique des nouveautés et suivi de lecture par utilisateur.' => 'Release history and per-user read tracking.',
        'Export des alertes et maintenances.' => 'Export of alerts and maintenance windows.',
        'Améliorations de la navigation et des formulaires.' => 'Navigation and form improvements.',
        'Thèmes, import, validation Manager et langues' => 'Themes, import, Manager approval and languages',
        'Thème clair, sombre ou système mémorisé par utilisateur.' => 'Light, dark or system theme saved per user.',
        'Interface Français / English avec langue enregistrée par compte.' => 'French / English interface with language saved per account.',
        'Validation Manager à deux niveaux : N+1 puis Manager sélectionné.' => 'Two-level Manager approval: N+1 then selected Manager.',
        'Import utilisateurs CSV / Excel avec prévisualisation et contrôles.' => 'CSV / Excel user import with preview and validation.',
        'Changement forcé du mot de passe et assistant d’installation modernisés.' => 'Forced password change and redesigned setup assistant.',
        'Calendriers, conversations, pièces jointes et alertes de service améliorés.' => 'Improved calendars, conversations, attachments and service alerts.',
        'Maintenance et composants de formulaire — finition' => 'Maintenance and form components — finishing',
        'Alertes et maintenance — corrections fonctionnelles' => 'Alerts and maintenance — functional fixes',
        'Maintenance et interface — correctifs' => 'Maintenance and interface — fixes',
        'Alertes, maintenance et nouveautés' => 'Alerts, maintenance and what’s new',
        'Première version stable' => 'First stable version',
        'Ajustements supplémentaires du mode maintenance immédiat.' => 'Additional adjustments to immediate maintenance mode.',
        'Menus déroulants TicketFlow entièrement personnalisés au lieu du menu natif du navigateur.' => 'Fully customized TicketFlow dropdowns instead of native browser menus.',
        'Navigation clavier et synchronisation des menus avec les formulaires existants.' => 'Keyboard navigation and menu synchronization with existing forms.',
        'Finition visuelle des champs, cases et actions de maintenance.' => 'Visual finishing for fields, checkboxes and maintenance actions.',
        'Correction de la fin automatique du mode maintenance avec le fuseau horaire TicketFlow.' => 'Fixed automatic maintenance-mode ending using the TicketFlow timezone.',
        'Les alertes globales n’affichent plus que leur titre dans la bannière ; un clic ouvre le message complet.' => 'Global alerts now show only their title in the banner; clicking opens the full message.',
        'Titre des alertes limité à 80 caractères et message étendu à 2000 caractères.' => 'Alert titles are limited to 80 characters and messages extended to 2000 characters.',
        'Nouvelle mise en page de la page À propos & Nouveautés.' => 'New layout for the About & What’s New page.',
        'Ajustements visuels supplémentaires de la page de maintenance.' => 'Additional visual adjustments to the maintenance page.',
        'Redirection automatique des Collaborateurs et Managers lors du démarrage d’une maintenance.' => 'Automatic redirection of Employees and Managers when maintenance starts.',
        'Fin automatique du mode maintenance lorsque la date de fin estimée est atteinte.' => 'Automatic end of maintenance mode when the estimated end time is reached.',
        'Correction de la déconnexion depuis la page de maintenance.' => 'Fixed sign-out from the maintenance page.',
        'Nouvelle présentation de la page de maintenance.' => 'New maintenance page presentation.',
        'Nouvelle présentation de la page À propos & Nouveautés.' => 'New presentation of the About & What’s New page.',
        'Accès aux Alertes de service déplacé dans la barre supérieure pour Administrateur et IT.' => 'Service Alerts access moved to the top bar for Administrators and IT Support.',
        'Alertes de service globales en temps réel.' => 'Real-time global service alerts.',
        'Page À propos et historique des versions.' => 'About page and version history.',
        'Journal des nouveautés par utilisateur.' => 'Per-user What’s New log.',
        'Mode maintenance TicketFlow.' => 'TicketFlow maintenance mode.',
        'Maintenances planifiées.' => 'Scheduled maintenance windows.',
        'Extraction Excel des alertes et maintenances.' => 'Excel export of alerts and maintenance windows.',
        'Workflows Administrateur, IT, Manager et Collaborateur.' => 'Administrator, IT Support, Manager and Employee workflows.',
        'Recherche globale, filtres avancés, vues enregistrées et actions multiples.' => 'Global search, advanced filters, saved views and bulk actions.',
        'SLA, notifications, exports, statistiques, audit, e-mails et automatisations.' => 'SLA, notifications, exports, statistics, audit, emails and automations.',
        'Installation automatique Debian/Ubuntu.' => 'Automatic Debian/Ubuntu installation.',
        'Développement' => 'Development',
        'Stable' => 'Stable',
    ];
    foreach ($releases as &$release) {
        $release['title'] = $releaseTranslations[$release['title']] ?? $release['title'];
        $release['date'] = $releaseTranslations[$release['date']] ?? $release['date'];
        foreach ($release['items'] as &$item) $item = $releaseTranslations[$item] ?? $item;
        unset($item);
    }
    unset($release);
}
$seen = $releaseNoteService->hasSeen((int)$user['id']);
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="tf-about-hero">
    <div class="tf-about-hero-main">
        <div class="tf-about-logo"><i class="fa-solid fa-ticket"></i></div>
        <div>
            <span class="tf-about-eyebrow">TicketFlow</span>
            <h1><?= htmlspecialchars(t('about.title')) ?></h1>
            <p><?= htmlspecialchars(t('about.subtitle')) ?></p>
        </div>
    </div>
    <div class="tf-about-version-card">
        <span><?= htmlspecialchars(t('about.installed_version')) ?></span>
        <strong><?= htmlspecialchars($version) ?></strong>
        <small><?= htmlspecialchars(t('about.current_stable')) ?></small>
    </div>
</section>

<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="tf-about-facts">
    <article><i class="fa-solid fa-user-gear"></i><div><span><?= htmlspecialchars(t('about.creator')) ?></span><strong>blueGameTV</strong></div></article>
    <article><i class="fa-solid fa-code"></i><div><span><?= htmlspecialchars(t('about.technologies')) ?></span><strong>PHP · MariaDB · Apache · JavaScript</strong></div></article>
    <article><i class="fa-solid fa-shield-halved"></i><div><span><?= htmlspecialchars(t('about.previous_version')) ?></span><strong>v1.3.0</strong></div></article>
    <article><i class="fa-solid fa-circle-check"></i><div><span><?= htmlspecialchars(t('about.current_status')) ?></span><strong>Stable v<?= htmlspecialchars($version) ?></strong></div></article>
</section>

<section class="panel tf-about-journal" id="nouveautes">
    <div class="tf-about-journal-top">
        <div class="tf-about-journal-title">
            <span class="panel-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
            <div><h2><?= htmlspecialchars(t('about.journal')) ?></h2><p><?= htmlspecialchars(t('about.journal_help')) ?></p></div>
        </div>
        <?php if (!$seen): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                <button class="btn primary" type="submit"><i class="fa-solid fa-check"></i> <?= htmlspecialchars(t('about.seen_button')) ?></button>
            </form>
        <?php else: ?>
            <span class="tf-about-seen"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars(t('about.seen')) ?></span>
        <?php endif; ?>
    </div>

    <div class="tf-release-list">
        <?php foreach ($releases as $release): $current = $release['version'] === $version; ?>
            <article class="tf-release-row <?= $current ? 'is-current' : '' ?>">
                <div class="tf-release-rail"><span><i class="fa-solid <?= $current ? 'fa-star' : 'fa-code-commit' ?>"></i></span></div>
                <div class="tf-release-row-body">
                    <div class="tf-release-row-head">
                        <div>
                            <div class="tf-release-kicker">v<?= htmlspecialchars((string)$release['version']) ?></div>
                            <h3><?= htmlspecialchars((string)$release['title']) ?></h3>
                        </div>
                        <div class="tf-release-tags">
                            <span><?= htmlspecialchars((string)$release['date']) ?></span>
                            <?php if ($current): ?><span class="current"><?= htmlspecialchars(t('common.current_version')) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <ul><?php foreach ($release['items'] as $item): ?><li><i class="fa-solid fa-check"></i><span><?= htmlspecialchars((string)$item) ?></span></li><?php endforeach; ?></ul>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
