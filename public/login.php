<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = mb_strtolower(trim((string)($_POST['identifier'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? null;

    if (!$csrf->validate(is_string($token) ? $token : null)) {
        $error = t('login.error_expired');
    } elseif ($identifier === '' || $password === '') {
        $error = t('login.error_required');
    } elseif (!$auth->attempt($identifier, $password)) {
        usleep(250000);
        $error = $auth->lastError() ?? t('login.error_invalid');
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

$appName = $config['app']['name'] ?? 'TicketFlow';
$currentLocale = $translator->locale();

$theme = (string)($_COOKIE['ticketflow_theme'] ?? 'light');
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($currentLocale) ?>" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars(t('login.title')) ?> - <?= htmlspecialchars($appName) ?></title>

    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=1.3.0-dev.8-fix20">
</head>

<body class="tf-login17-page">

<main class="tf-login17-stage">
    <section class="tf-login17-card">

        <aside class="tf-login17-brand-panel">
            <div class="tf-login17-brand">
                <span class="tf-login17-logo">
                    <i class="fa-solid fa-ticket"></i>
                </span>
                <div>
                    <small><?= htmlspecialchars(t('login.platform')) ?></small>
                    <strong><?= htmlspecialchars($appName) ?></strong>
                </div>
            </div>

            <div class="tf-login17-brand-copy">
                <span class="tf-login17-env">
                    <i class="fa-solid fa-network-wired"></i>
                    <?= htmlspecialchars(t('login.environment')) ?>
                </span>

                <h1><?= htmlspecialchars(t('login.hero_title')) ?></h1>
                <p><?= htmlspecialchars(t('login.hero_text')) ?></p>
            </div>

            <div class="tf-login17-feature-list">
                <div>
                    <span class="tf-login17-feature-icon"><i class="fa-solid fa-bolt"></i></span>
                    <span><?= htmlspecialchars(t('login.live')) ?></span>
                </div>
                <div>
                    <span class="tf-login17-feature-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <span><?= htmlspecialchars(t('login.secure')) ?></span>
                </div>
                <div>
                    <span class="tf-login17-feature-icon"><i class="fa-solid fa-clock"></i></span>
                    <span><?= htmlspecialchars(t('login.sla')) ?></span>
                </div>
            </div>
</aside>

        <section class="tf-login17-auth-panel">
            <header class="tf-login17-toolbar">
                <div class="tf-login17-language" data-language-menu>
                    <button
                        type="button"
                        class="tf-login17-language-trigger"
                        data-language-toggle
                        aria-expanded="false"
                        aria-haspopup="true"
                    >
                        <i class="fa-solid fa-language"></i>
                        <span><?= $currentLocale === 'en' ? 'English' : 'Français' ?></span>
                        <i class="fa-solid fa-chevron-down tf-login17-language-arrow"></i>
                    </button>

                    <div class="tf-login17-language-menu" data-language-options hidden>
                        <button type="button" data-language-value="fr" class="<?= $currentLocale === 'fr' ? 'active' : '' ?>">
                            <span class="tf-login17-language-code">FR</span>
                            <span>Français</span>
                            <?php if ($currentLocale === 'fr'): ?><i class="fa-solid fa-check"></i><?php endif; ?>
                        </button>

                        <button type="button" data-language-value="en" class="<?= $currentLocale === 'en' ? 'active' : '' ?>">
                            <span class="tf-login17-language-code">EN</span>
                            <span>English</span>
                            <?php if ($currentLocale === 'en'): ?><i class="fa-solid fa-check"></i><?php endif; ?>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="tf-login17-theme"
                    data-theme-toggle
                    aria-label="<?= htmlspecialchars(t('login.theme')) ?>"
                >
                    <i class="fa-solid <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                </button>
            </header>

            <div class="tf-login17-auth-content">
                <div class="tf-login17-heading">
                    <span class="tf-login17-kicker"><?= htmlspecialchars($appName) ?></span>
                    <h2><?= htmlspecialchars(t('login.title')) ?></h2>
                    <p><?= htmlspecialchars(t('login.subtitle')) ?></p>
                </div>

                <?php if ($error !== null): ?>
                    <div class="tf-login17-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form class="tf-login17-form" method="post" action="login.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">

                    <div class="tf-login17-field">
                        <label for="identifier"><?= htmlspecialchars(t('login.identifier')) ?></label>
                        <div class="tf-login17-input">
                            <i class="fa-regular fa-user"></i>
                            <input
                                id="identifier"
                                name="identifier"
                                type="text"
                                maxlength="190"
                                required
                                autocomplete="username"
                                value="<?= htmlspecialchars($identifier) ?>"
                                placeholder="jdupont ou prenom.nom@entreprise.fr"
                            >
                        </div>
                    </div>

                    <div class="tf-login17-field">
                        <div class="tf-login17-label-row">
                            <label for="password"><?= htmlspecialchars(t('login.password')) ?></label>
                            <a href="forgot-password.php"><?= htmlspecialchars(t('login.forgot')) ?></a>
                        </div>

                        <div class="tf-login17-input">
                            <i class="fa-solid fa-lock"></i>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                            >

                            <button
                                type="button"
                                class="tf-login17-password-toggle"
                                data-password-toggle="password"
                                aria-label="<?= htmlspecialchars(t('login.password')) ?>"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button class="tf-login17-submit" type="submit">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        <span><?= htmlspecialchars(t('login.submit')) ?></span>
                    </button>
                </form>
            </div>
        </section>

    </section>
</main>

<script>
(() => {
    const languageRoot = document.querySelector('[data-language-menu]');
    const languageToggle = document.querySelector('[data-language-toggle]');
    const languageOptions = document.querySelector('[data-language-options]');
    const themeToggle = document.querySelector('[data-theme-toggle]');

    const closeLanguageMenu = () => {
        if (!languageToggle || !languageOptions) return;
        languageOptions.hidden = true;
        languageToggle.setAttribute('aria-expanded', 'false');
        languageRoot?.classList.remove('open');
    };

    languageToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!languageOptions) return;

        const opening = languageOptions.hidden;
        languageOptions.hidden = !opening;
        languageToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        languageRoot?.classList.toggle('open', opening);
    });

    document.querySelectorAll('[data-language-value]').forEach((button) => {
        button.addEventListener('click', () => {
            const language = button.getAttribute('data-language-value') === 'en' ? 'en' : 'fr';
            const url = new URL(window.location.href);
            url.searchParams.set('lang', language);
            window.location.href = url.toString();
        });
    });

    document.addEventListener('click', (event) => {
        if (!languageRoot?.contains(event.target)) {
            closeLanguageMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeLanguageMenu();
        }
    });

    themeToggle?.addEventListener('click', () => {
        const current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        document.documentElement.dataset.theme = next;
        document.cookie = 'ticketflow_theme=' + next + '; Path=/; Max-Age=31536000; SameSite=Lax';

        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.className = next === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const inputId = button.getAttribute('data-password-toggle');
            const input = inputId ? document.getElementById(inputId) : null;

            if (!(input instanceof HTMLInputElement)) return;

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';

            const icon = button.querySelector('i');
            if (icon) {
                icon.className = reveal ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            }
        });
    });
})();
</script>

</body>
</html>
