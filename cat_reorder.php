<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, string $message = ''): void
{
    echo json_encode(
        $success ? ['success' => true] : ['success' => false, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(false, t('messages.json_post_only'));
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);

if (!is_array($payload)) {
    jsonResponse(false, t('messages.invalid_json_body'));
}

if (!array_key_exists('ids', $payload)) {
    jsonResponse(false, t('messages.ids_required'));
}

$ids = $payload['ids'];

if (!is_array($ids) || $ids === []) {
    jsonResponse(false, t('messages.ids_non_empty_array'));
}

$normalizedIds = [];
foreach ($ids as $id) {
    if (filter_var($id, FILTER_VALIDATE_INT) === false) {
        jsonResponse(false, t('messages.ids_positive_integers'));
    }

    $intId = (int)$id;
    if ($intId <= 0) {
        jsonResponse(false, t('messages.ids_positive_integers'));
    }

    $normalizedIds[] = $intId;
}

if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
    jsonResponse(false, t('messages.ids_duplicate_value'));
}

$placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
$checkStmt = $pdo->prepare("SELECT id FROM categories WHERE id IN ($placeholders)");
$checkStmt->execute($normalizedIds);
$existingIds = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN));

if (count($existingIds) !== count($normalizedIds)) {
    jsonResponse(false, t('messages.ids_invalid_or_missing'));
}

$startedTransaction = false;

try {
    if (!$pdo->inTransaction()) {
        $startedTransaction = $pdo->beginTransaction();
    }

    $updateStmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");

    foreach ($normalizedIds as $index => $categoryId) {
        $sortOrder = ($index + 1) * 10;
        $updateStmt->execute([$sortOrder, $categoryId]);
    }

    if ($startedTransaction && $pdo->inTransaction()) {
        $pdo->commit();
    }

    jsonResponse(true);
} catch (Throwable $e) {
    if ($startedTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, t('messages.category_reorder_save_error'));
}



