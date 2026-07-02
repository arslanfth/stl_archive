<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

if (!isset($_POST['id']) || empty($_POST['name'])) {
    exit(t('messages.missing_data', 'Eksik veri!'));
}

$id = intval($_POST['id']);
$name = trim($_POST['name']);
if (mb_strlen($name) < 2) {
    exit(t('messages.too_short', 'Çok kısa!'));
}

$pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
echo 'OK';
