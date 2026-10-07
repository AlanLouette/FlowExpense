<?php
require_once __DIR__ . '/bootstrap.php';

enforceOrganizationAccess($currentOrganization ?? null);

function nav_link(string $href, string $label, string $page, string $currentPage): string
{
    $active = $page === $currentPage ? 'active' : '';
    return sprintf('<a class="nav-link %s" href="%s">%s</a>', $active, htmlspecialchars($href), htmlspecialchars($label));
}

function renderPageStart(string $title, string $currentPage = ''): void
{
    global $currentUser, $availableOrganizations, $currentOrganization;
    ?>
<!DOCTYPE html>
<html lang="<?= app_language() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> · FlowExpense</title>
    <link rel="stylesheet" href="style.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <div class="logo-circle"></div>
            <span class="brand">FlowExpense</span>
        </div>
        <nav class="sidebar-nav" aria-label="<?= ht('Main navigation') ?>">
            <div class="nav-group">
                <span class="nav-group-title"><?= ht('Activity') ?></span>
                <?= nav_link('index.php', t('Dashboard'), 'index', $currentPage) ?>
                <?= nav_link('form.php', t('Nieuwe onkostennota'), 'form', $currentPage) ?>
                <?= nav_link('history.php', t('History'), 'history', $currentPage) ?>
                <?= nav_link('trash.php', t('Trash'), 'trash', $currentPage) ?>
            </div>
            <div class="nav-group">
                <span class="nav-group-title"><?= ht('Reference data') ?></span>
                <?= nav_link('recipients.php', t('Ontvangers'), 'recipients', $currentPage) ?>
                <?= nav_link('categories.php', t('Categorieën'), 'categories', $currentPage) ?>
                <?= nav_link('units.php', t('Eenheden'), 'units', $currentPage) ?>
            </div>
            <?php if (demo_mode() || ($currentUser && (int)$currentUser['is_admin'] === 1)): ?>
            <div class="nav-group">
                <span class="nav-group-title"><?= ht('Administration') ?></span>
                <?php foreach ([['organizations.php','Organisaties','organizations'],['users.php','Gebruikers','users'],['settings.php','Instellingen','settings'],['backups.php','Backups','backups']] as [$href,$label,$page]): ?>
                    <?php if (demo_mode()): ?>
                        <span class="nav-link nav-locked" role="link" aria-disabled="true" tabindex="0" title="<?= ht('Unavailable in the guest demo. Administrator access required.') ?>" aria-label="<?= htmlspecialchars(t($label).' — '.t('Unavailable in the guest demo. Administrator access required.'),ENT_QUOTES) ?>"><span><?= ht($label) ?></span><span aria-hidden="true">🔒</span></span>
                    <?php else: ?>
                        <?= nav_link($href, t($label), $page, $currentPage) ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-meta">
                <span class="user-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
                <span class="user-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
            </div>
            <form action="logout.php" method="post" class="inline-action"><?php csrf_field(); ?><button class="logout-link" type="submit"><?= ht('Afmelden') ?></button></form>
        </div>
    </aside>
    <div class="app-content">
        <header class="app-topbar">
            <div class="topbar-left">
                <h1><?= htmlspecialchars($title) ?></h1>
                <?php if (!empty($currentOrganization['legal_name'])): ?>
                    <span class="org-subtitle"><?= htmlspecialchars($currentOrganization['legal_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="topbar-right">
                <?php language_selector(); ?>
                <?php if (!empty($availableOrganizations)): ?>
                    <form method="post" action="switch-organization.php" class="org-switcher"><?php csrf_field(); ?>
                        <label for="organization_id"><?= ht('Organisatie') ?></label>
                        <select name="organization_id" id="organization_id" onchange="this.form.submit()">
                            <?php foreach ($availableOrganizations as $org): ?>
                                <option value="<?= (int)$org['id'] ?>" <?= ((int)$org['id'] === (int)$currentOrganization['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        </header>
        <main class="app-main">
            <?php if (demo_mode()): ?>
            <div class="demo-notice"><div><strong><?= ht('Guest demo — fictional data') ?></strong><p><?= ht('Your workspace is private to this browser and expires after 24 hours. Use fictional documents only.') ?></p></div><form action="guest.php" method="post" class="inline-action" onsubmit="return confirm(<?= htmlspecialchars(jt('Reset the guest workspace and discard your demo changes?'), ENT_QUOTES) ?>);"><?php csrf_field(); ?><input type="hidden" name="action" value="reset"><button type="submit"><?= ht('Reset demo') ?></button></form>
                <?php if (getenv('GUEST_ENABLED') === '1'): ?><form action="logout.php" method="post" class="inline-action"><?php csrf_field(); ?><button type="submit"><?= ht('Sign in to my account') ?></button></form><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['backup_error']) && (int)$currentUser['is_admin']): ?>
                <p class="notice"><?= ht('Automatic backup failed. Check the Backups page.') ?></p>
            <?php endif; ?>
    <?php
}

function renderPageEnd(): void
{
    ?>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}
