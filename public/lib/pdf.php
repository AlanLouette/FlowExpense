<?php
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/expenses.php';

function expense_pdf_html(array $report, array $lines, array $organization): string
{
    $business = ($report['expense_type'] ?? 'reimbursement') === 'business';
    $total = (float)($report['total'] ?? 0);
    $description = $report['description'] ?? '';
    $renderedDescription = markdown_to_html($description);
    $orgAddressParts = array_filter([
        $organization['address_line1'] ?? '',
        $organization['address_line2'] ?? '',
        trim(($organization['postal_code'] ?? '') . ' ' . ($organization['city'] ?? '')),
        $organization['country'] ?? ''
    ]);
    $orgAddress = implode("\n", $orgAddressParts);
    $brandAccent = $organization['branding_accent'] ?? '#3b82f6';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="<?= app_language() ?>">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 32px; }
            body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 12px; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; }
            .brand { color: <?= htmlspecialchars($brandAccent) ?>; font-size: 28px; font-weight: 700; letter-spacing: 1px; }
            .meta { text-align: right; }
            .meta strong { display: block; font-size: 14px; }
            .section { margin-top: 24px; }
            h1 { font-size: 24px; margin: 0 0 6px; }
            h2 { font-size: 18px; margin: 0 0 12px; }
            h3 { font-size: 14px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
            .info-grid { display: flex; gap: 20px; }
            .info-card { flex: 1; padding: 16px; border-radius: 12px; background: #f5f8ff; border: 1px solid #dbeafe; }
            .info-card strong { display: block; font-size: 12px; color: #6b7280; text-transform: uppercase; }
            .info-card span { display: block; font-size: 14px; margin-top: 6px; color: #1f2937; }
            table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            th { background: #f1f5f9; text-align: left; padding: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
            td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
            tbody tr:nth-child(even) { background: #f8fafc; }
            .totals { margin-top: 16px; display: flex; justify-content: flex-end; }
            .totals table { width: 240px; }
            .totals td { border: none; padding: 6px 0; }
            .totals tr:last-child td { font-weight: 700; font-size: 14px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
            .notes { margin-top: 16px; padding: 16px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
            .payment { margin-top: 24px; padding: 18px; border-radius: 12px; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); }
            .payment strong { display: block; font-size: 14px; margin-bottom: 6px; }
            .signature { margin-top: 36px; display: flex; justify-content: space-between; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="header">
            <div>
                <div class="brand"><?= htmlspecialchars($organization['name'] ?? 'FlowExpense') ?></div>
                <h1><?= ht('Onkostennota') ?> <?= htmlspecialchars($report['custom_id'] ?: '#' . $report['id']) ?></h1>
                <div><?= ht('Opgesteld op') ?> <?= htmlspecialchars($report['date'] ?? date('Y-m-d')) ?></div>
            </div>
            <div class="meta">
                <strong><?= ht($business ? 'Supplier' : 'Betaal aan') ?></strong>
                <div><?= nl2br(htmlspecialchars(trim(($business ? ($report['supplier'] ?? '') : ($report['recipient'] ?? '')) . "\n" . ($report['address'] ?? '')))) ?></div>
                <?php if (!$business): ?><div style="margin-top:10px;">IBAN: <?= htmlspecialchars($report['iban'] ?? '') ?></div>
                <div><?= ht('Bank:') ?> <?= htmlspecialchars($report['bank_name'] ?? '') ?></div><?php endif; ?>
            </div>
        </div>

        <div class="section info-grid">
            <div class="info-card">
                <strong><?= ht($business ? 'Supplier' : 'Ontvanger') ?></strong>
                <span><?= htmlspecialchars($business ? ($report['supplier'] ?? '') : ($report['recipient'] ?? '')) ?></span>
                <span><?= nl2br(htmlspecialchars($report['address'] ?? '')) ?></span>
            </div>
            <div class="info-card">
                <strong><?= ht('Organisatie') ?></strong>
                <span><?= htmlspecialchars($organization['legal_name'] ?? $organization['name'] ?? '') ?></span>
                <span><?= nl2br(htmlspecialchars($orgAddress)) ?></span>
            </div>
            <div class="info-card">
                <strong><?= ht('Referenties') ?></strong>
                <span><?= ht('BTW:') ?> <?= htmlspecialchars($organization['vat_number'] ?? '-') ?></span>
                <span>IBAN: <?= htmlspecialchars($organization['iban'] ?? '-') ?></span>
                <span><?= ht('Bank:') ?> <?= htmlspecialchars($organization['bank_name'] ?? '-') ?></span>
            </div>
        </div>

        <div class="section">
            <h2><?= ht('Details') ?></h2>
            <table>
                <thead>
                <tr>
                    <th><?= ht('Omschrijving') ?></th>
                    <th><?= ht('Aantal') ?></th>
                    <th><?= ht('Eenheid') ?></th>
                    <th><?= ht('Prijs per eenheid') ?></th>
                    <th><?= ht('Amount excluding VAT') ?></th><th><?= ht('VAT amount') ?></th><th><?= ht('Amount including VAT') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lines as $line):
                    $quantity = (float)($line['quantity'] ?? 0);
                    $rate = (float)($line['rate'] ?? 0);
                    $amounts = expense_line_amounts($line);
                    $subtotal = $amounts['net'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($line['description'] ?? '') ?></td>
                        <td><?= localized_number($quantity, 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($line['unit'] ?? '-') ?></td>
                        <td>€ <?= localized_number($rate, 4, ',', '.') ?></td>
                        <td>€ <?= localized_number($subtotal, 2, ',', '.') ?></td>
                        <td>€ <?= localized_number($amounts['vat'], 2, ',', '.') ?> (<?= localized_number((float)($line['vat_rate'] ?? 0), 2) ?>%)</td>
                        <td>€ <?= localized_number($amounts['gross'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="totals">
                <table>
                    <tr><td><?= ht('Amount excluding VAT') ?></td><td style="text-align:right">€ <?= localized_number((float)($report['net_total'] ?? $total), 2, ',', '.') ?></td></tr>
                    <tr><td><?= ht('VAT amount') ?></td><td style="text-align:right">€ <?= localized_number((float)($report['vat_total'] ?? 0), 2, ',', '.') ?></td></tr>
                    <tr>
                        <td><?= ht('Amount including VAT') ?></td>
                        <td style="text-align:right;">€ <?= localized_number($total, 2, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($renderedDescription): ?>
            <div class="notes">
                <h3><?= ht('Omschrijving &amp; Doel') ?></h3>
                <?= $renderedDescription ?>
            </div>
        <?php endif; ?>

        <?php if (!$business): ?><div class="payment">
            <strong><?= ht('Betalingsinformatie') ?></strong>
            <div><?= ht('Gelieve het totaalbedrag van €') ?> <?= localized_number($total, 2, ',', '.') ?> <?= ht('over te maken naar') ?> <?= htmlspecialchars($report['iban'] ?? '') ?> <?= ht('ten name van') ?> <?= htmlspecialchars($business ? ($report['supplier'] ?? '') : ($report['recipient'] ?? '')) ?>.</div>
            <div><?= ht('Vermeld referentie') ?> <?= htmlspecialchars($report['custom_id'] ?: '#' . $report['id']) ?> <?= ht('bij de betaling.') ?></div>
        </div>

        <?php endif; ?>
        <div class="signature">
            <div><?= ht('Goedgekeurd door') ?> <?= htmlspecialchars($organization['legal_name'] ?? $organization['name'] ?? '') ?></div>
            <div><?= ht('Contact:') ?> <?= htmlspecialchars($organization['contact_email'] ?? ($organization['phone'] ?? '')) ?></div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
