<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    $usageMode = in_array($_POST['usage_mode'] ?? '', ['solo', 'team'], true) ? $_POST['usage_mode'] : 'team';
    $db->prepare('UPDATE organizations SET usage_mode = ? WHERE id = ?')->execute([$usageMode, $orgId]);
    $legalName = trim($_POST['legal_name'] ?? '');
    $address1 = trim($_POST['address_line1'] ?? '');
    $address2 = trim($_POST['address_line2'] ?? '');
    $postal = trim($_POST['postal_code'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $vat = trim($_POST['vat_number'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $contact = trim($_POST['contact_email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $prefix = trim($_POST['number_prefix'] ?? 'EXP');
    $nextNumber = max(1, (int)($_POST['next_expense_number'] ?? 1));
    $primary = trim($_POST['branding_primary'] ?? '#1f2933');
    $accent = trim($_POST['branding_accent'] ?? '#3b82f6');

    $stmt = $db->prepare('UPDATE organizations SET legal_name = ?, address_line1 = ?, address_line2 = ?, postal_code = ?, city = ?, country = ?, vat_number = ?, iban = ?, bank_name = ?, contact_email = ?, phone = ?, number_prefix = ?, next_expense_number = ?, branding_primary = ?, branding_accent = ? WHERE id = ?');
    $stmt->execute([
        $legalName,
        $address1,
        $address2,
        $postal,
        $city,
        $country,
        $vat,
        $iban,
        $bank,
        $contact,
        $phone,
        $prefix ?: 'EXP',
        $nextNumber,
        $primary,
        $accent,
        $orgId
    ]);

    record_event('settings_updated',null,['usage_mode'=>$usageMode,'legal_name'=>$legalName]);
    $db->commit();
    $_SESSION['flash_message'] = t('Instellingen opgeslagen.');
    header('Location: settings.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
$stmt->execute([$orgId]);
$organization = $stmt->fetch(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

renderPageStart(t('Instellingen'), 'settings');
?>
<section class="card">
    <h2><?= ht('Organisatiegegevens') ?></h2>
    <?php if ($flash): ?>
        <p class="notice"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>
    <form method="post"><?php csrf_field(); ?>
        <label><?= ht('Usage mode') ?>
            <select name="usage_mode">
                <option value="solo" <?= $organization['usage_mode'] === 'solo' ? 'selected' : '' ?>><?= ht('Solo') ?></option>
                <option value="team" <?= $organization['usage_mode'] === 'team' ? 'selected' : '' ?>><?= ht('Team') ?></option>
            </select>
        </label>
        <p class="notice"><?= ht('Solo mode simplifies new business expenses. Existing reports stay editable.') ?></p>
        <div class="grid-2">
            <label><?= ht('Juridische naam') ?>
                <input type="text" name="legal_name" value="<?= htmlspecialchars($organization['legal_name'] ?? '') ?>">
            </label>
            <label><?= ht('Adres regel 1') ?>
                <input type="text" name="address_line1" value="<?= htmlspecialchars($organization['address_line1'] ?? '') ?>">
            </label>
            <label><?= ht('Adres regel 2') ?>
                <input type="text" name="address_line2" value="<?= htmlspecialchars($organization['address_line2'] ?? '') ?>">
            </label>
            <label><?= ht('Postcode') ?>
                <input type="text" name="postal_code" value="<?= htmlspecialchars($organization['postal_code'] ?? '') ?>">
            </label>
            <label><?= ht('Stad') ?>
                <input type="text" name="city" value="<?= htmlspecialchars($organization['city'] ?? '') ?>">
            </label>
            <label><?= ht('Land') ?>
                <input type="text" name="country" value="<?= htmlspecialchars($organization['country'] ?? '') ?>">
            </label>
            <label><?= ht('BTW-nummer') ?>
                <input type="text" name="vat_number" value="<?= htmlspecialchars($organization['vat_number'] ?? '') ?>">
            </label>
            <label><?= ht('IBAN') ?>
                <input type="text" name="iban" value="<?= htmlspecialchars($organization['iban'] ?? '') ?>">
            </label>
            <label><?= ht('Bank') ?>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($organization['bank_name'] ?? '') ?>">
            </label>
            <label><?= ht('Contact e-mail') ?>
                <input type="email" name="contact_email" value="<?= htmlspecialchars($organization['contact_email'] ?? '') ?>">
            </label>
            <label><?= ht('Telefoon') ?>
                <input type="text" name="phone" value="<?= htmlspecialchars($organization['phone'] ?? '') ?>">
            </label>
            <label><?= ht('Nummer prefix') ?>
                <input type="text" name="number_prefix" value="<?= htmlspecialchars($organization['number_prefix'] ?? 'EXP') ?>">
            </label>
            <label><?= ht('Volgend onkostennummer') ?>
                <input type="number" name="next_expense_number" min="1" value="<?= htmlspecialchars($organization['next_expense_number'] ?? 1) ?>">
            </label>
            <label><?= ht('Primaire kleur') ?>
                <input type="color" name="branding_primary" value="<?= htmlspecialchars($organization['branding_primary'] ?? '#1f2933') ?>">
            </label>
            <label><?= ht('Accentkleur') ?>
                <input type="color" name="branding_accent" value="<?= htmlspecialchars($organization['branding_accent'] ?? '#3b82f6') ?>">
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="submit"><?= ht('Opslaan') ?></button>
        </div>
    </form>
</section>
<?php
renderPageEnd();
