<?php
function generate_custom_id(PDO $db, int $organizationId): string
{
    $stmt = $db->prepare('SELECT number_prefix, next_expense_number FROM organizations WHERE id = ?');
    $stmt->execute([$organizationId]);
    $orgData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orgData) {
        throw new RuntimeException(t('Organisatie-instellingen niet gevonden.'));
    }
    $prefix = $orgData['number_prefix'] ?: 'EXP';
    $sequence = (int)$orgData['next_expense_number'];

    do {
        $candidate = sprintf('%s-%04d', $prefix, $sequence);
        $check = $db->prepare('SELECT COUNT(*) FROM expense_reports WHERE organization_id = ? AND custom_id = ?');
        $check->execute([$organizationId, $candidate]);
        if ($check->fetchColumn() == 0) {
            break;
        }
        $sequence++;
    } while (true);

    $updateOrg = $db->prepare('UPDATE organizations SET next_expense_number = ? WHERE id = ?');
    $updateOrg->execute([$sequence + 1, $organizationId]);

    return $candidate;
}

function ensure_unique_custom_id(PDO $db, int $organizationId, string $customId, ?int $excludeId = null): void
{
    $query = 'SELECT COUNT(*) FROM expense_reports WHERE organization_id = ? AND custom_id = ?';
    $params = [$organizationId, $customId];
    if ($excludeId) {
        $query .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
        throw new RuntimeException(t('Dit onkostennota-nummer is al in gebruik.'));
    }
}

function expense_decimal($value): float
{
    if (!is_scalar($value)) throw new InvalidArgumentException(t('Invalid amount.'));
    $value = str_replace(',', '.', trim((string)$value));
    if (!preg_match('/^\d+(?:\.\d{1,4})?$/D', $value)) throw new InvalidArgumentException(t('Invalid amount.'));
    $number = (float)$value;
    if (!is_finite($number) || $number > 1000000) throw new InvalidArgumentException(t('Invalid amount.'));
    return $number;
}

function expense_line_amounts(array $line): array
{
    $netCents = (int)round((float)$line['quantity'] * (float)$line['rate'] * 100, 0, PHP_ROUND_HALF_UP);
    $vatCents = (int)round($netCents * (float)($line['vat_rate'] ?? 0) / 100, 0, PHP_ROUND_HALF_UP);
    return ['net' => $netCents / 100, 'vat' => $vatCents / 100, 'gross' => ($netCents + $vatCents) / 100];
}

function expense_totals(array $lines): array
{
    $net = 0; $vat = 0;
    foreach ($lines as $line) {
        $amounts = expense_line_amounts($line);
        $net += (int)round($amounts['net'] * 100);
        $vat += (int)round($amounts['vat'] * 100);
    }
    return ['net' => $net / 100, 'vat' => $vat / 100, 'gross' => ($net + $vat) / 100];
}

function attachment_directory(): string
{
    global $dbPath;
    return dirname($dbPath) . '/attachments';
}
