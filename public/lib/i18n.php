<?php
// Translate interface strings only. User-entered descriptions, names and database values are untouched.
$language = $_SESSION['language'] ?? $_COOKIE['flowexpense_language'] ?? 'fr';
if (!in_array($language, ['fr', 'en', 'nl'], true)) {
    $language = 'fr';
}
$_SESSION['language'] = $language;
function app_language(): string { return $_SESSION['language'] ?? 'fr'; }
function t(string $key): string
{
    static $catalog;
    $catalog ??= json_decode(file_get_contents(__DIR__ . '/translations.json'), true, 512, JSON_THROW_ON_ERROR);
    return $catalog[$key][app_language()] ?? $key;
}
function ht(string $key): string { return htmlspecialchars(t($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function jt(string $key): string { return json_encode(t($key), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }
function language_selector(): void
{
    $return = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    if (!empty($_SERVER['QUERY_STRING'])) $return .= '?' . $_SERVER['QUERY_STRING'];
    ?>
    <form action="language.php" method="post" class="language-switcher" style="margin:0;padding:0;box-shadow:none;background:transparent;"><?php csrf_field(); ?>
        <input type="hidden" name="return" value="<?= htmlspecialchars($return, ENT_QUOTES) ?>">
        <label><?= ht('Language') ?>
            <select name="language" onchange="this.form.submit()" aria-label="<?= ht('Language') ?>">
                <?php foreach (['fr' => 'Français', 'en' => 'English', 'nl' => 'Nederlands'] as $code => $name): ?>
                    <option value="<?= $code ?>" <?= app_language() === $code ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit"><?= ht('Save') ?></button></noscript>
    </form>
    <?php
}

function localized_number(float $value, int $decimals = 0, ?string $unusedDecimal = null, ?string $unusedThousands = null): string
{
    $language = app_language();
    return number_format($value, $decimals, $language === 'en' ? '.' : ',', $language === 'fr' ? ' ' : ($language === 'en' ? ',' : '.'));
}
