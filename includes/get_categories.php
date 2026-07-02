<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$cats = $pdo->query("SELECT id, name, color, sort_order FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
echo json_encode($cats);
