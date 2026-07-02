<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

$categoryPalette = [
    '#7c5cff',
    '#39a0ff',
    '#31c48d',
    '#f59e0b',
    '#ec4899',
    '#94a3b8',
    '#ef4444',
    '#14b8a6',
    '#eab308',
    '#8b5cf6',
    '#06b6d4',
    '#f97316',
    '#22c55e',
    '#d946ef',
    '#64748b',
];

if (empty($_POST['name'])) {
    exit(t('messages.name_empty', 'Ad boş!'));
}

$name = trim($_POST['name']);
if (mb_strlen($name) < 2) {
    exit(t('messages.too_short', 'Çok kısa!'));
}

$usedColors = $pdo->query("SELECT color FROM categories WHERE color IS NOT NULL AND TRIM(color) <> ''")
    ->fetchAll(PDO::FETCH_COLUMN);
$usedColors = array_map('strtolower', array_map('trim', $usedColors));

$selectedColor = null;
foreach ($categoryPalette as $paletteColor) {
    if (!in_array(strtolower($paletteColor), $usedColors, true)) {
        $selectedColor = $paletteColor;
        break;
    }
}

if ($selectedColor === null) {
    $categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $selectedColor = $categoryPalette[$categoryCount % count($categoryPalette)];
}

$maxSortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM categories")->fetchColumn();
$nextSortOrder = $maxSortOrder > 0 ? $maxSortOrder + 10 : 10;

$pdo->prepare("INSERT INTO categories (name, color, sort_order) VALUES (?, ?, ?)")->execute([$name, $selectedColor, $nextSortOrder]);
echo 'OK';
