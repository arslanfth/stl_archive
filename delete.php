<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

if (empty($_POST['id'])) {
    echo t('messages.invalid_request', 'Geçersiz istek!');
    exit;
}

$id = (int)$_POST['id'];
$uploadDir = __DIR__ . '/upload/';

$stmt = $pdo->prepare("SELECT id, filename FROM images WHERE id = ?");
$stmt->execute([$id]);
$imageRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imageRow) {
    echo t('messages.record_not_found', 'Kayıt bulunamadı!');
    exit;
}

$filenames = [];
$legacyFilename = trim((string)($imageRow['filename'] ?? ''));
if ($legacyFilename !== '') {
    $filenames[] = basename($legacyFilename);
}

$hasImageMediaTable = false;
try {
    $hasImageMediaTable = (bool)$pdo->query("SHOW TABLES LIKE 'image_media'")->fetchColumn();
} catch (Throwable $e) {
    $hasImageMediaTable = false;
}

if ($hasImageMediaTable) {
    $mediaStmt = $pdo->prepare("SELECT filename FROM image_media WHERE image_id = ?");
    $mediaStmt->execute([$id]);
    foreach ($mediaStmt->fetchAll(PDO::FETCH_COLUMN) as $mediaFilename) {
        $mediaFilename = trim((string)$mediaFilename);
        if ($mediaFilename !== '') {
            $filenames[] = basename($mediaFilename);
        }
    }
}

$filenames = array_values(array_unique(array_filter($filenames, static function ($filename) {
    return $filename !== '' && $filename !== '.' && $filename !== '..';
})));

try {
    if ($hasImageMediaTable) {
        $deleteMediaStmt = $pdo->prepare("DELETE FROM image_media WHERE image_id = ?");
        if (!$deleteMediaStmt->execute([$id])) {
            echo t('messages.linked_media_delete_error', 'Bağlı görseller silinemedi!');
            exit;
        }
    }

    $deleteImageStmt = $pdo->prepare("DELETE FROM images WHERE id = ?");
    if (!$deleteImageStmt->execute([$id])) {
        echo t('messages.database_error', 'Veritabanı hatası!');
        exit;
    }

    foreach ($filenames as $filename) {
        $filePath = $uploadDir . $filename;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    echo "OK";
} catch (Throwable $e) {
    echo t('messages.database_error', 'Veritabanı hatası!');
}
exit;
