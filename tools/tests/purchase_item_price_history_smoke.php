<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$model = (string)file_get_contents($root . '/application/models/Purchase_model.php');
$view = (string)file_get_contents($root . '/application/views/purchase/item_price_history.php');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$check($controller !== '' && $model !== '' && $view !== '', 'purchase price-history source files are readable');
$check(strpos($controller, '$this->Purchase_model->get_item_price_history($itemId, $limit)') !== false, 'controller delegates price history to the purchase model');
$check(strpos($model, 'public function get_item_price_history(int $itemId, int $limit = 20): array') !== false, 'purchase model exposes bounded price-history reader');
$check(strpos($model, "FROM pur_purchase_receipt_line rl") !== false && strpos($model, "r.status = 'POSTED'") !== false, 'posted purchase receipt is the canonical price-history source');
$check(strpos($model, 'rl.item_id = ? OR pol.item_id = ?') !== false && strpos($model, 'pol.unit_price') !== false, 'receipt query safely resolves both receipt and PO item identities with PO price');
$check(strpos($model, 'rl.qty_content_received / NULLIF(rl.qty_buy_received, 0)') !== false, 'HPP per isi derives from actual receipt conversion before fallbacks');
$check(strpos($model, "'LEGACY_LEDGER' AS source_type") !== false && strpos($model, 'AND l.receipt_line_id IS NULL') !== false, 'legacy ledger remains a non-duplicating fallback only');
$check(strpos($model, 'LIMIT {$limit}') !== false && strpos($model, 'min(200, max(5, $limit))') !== false, 'reader enforces a bounded result size');
$check(strpos($view, 'r.source_type === \'PURCHASE_RECEIPT\'') !== false && strpos($view, '<th>Sumber</th>') !== false, 'UI identifies canonical receipt versus legacy ledger rows');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " purchase item-price-history check(s) failed.\n");
    exit(1);
}
echo 'All ' . $checks . " purchase item-price-history checks passed.\n";
