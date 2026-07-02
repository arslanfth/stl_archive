<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

if (!isset($_POST['id'])) {
    exit(t('messages.id_missing', 'ID yok!'));
}

$id = intval($_POST['id']);

// Kategoriye bağlı kayıt varsa silme.
$count = $pdo->query("SELECT COUNT(*) FROM images WHERE category_id = $id")->fetchColumn();
if ($count > 0) {
    exit(t('messages.category_has_records', 'Bu kategoriye bağlı kayıtlar var!'));
}

$pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
echo 'OK';
