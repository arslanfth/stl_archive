<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

function normalizeUploadedImages(): array
{
    $files = [];

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'] ?? null)) {
        $count = count($_FILES['images']['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name' => $_FILES['images']['name'][$i] ?? '',
                'type' => $_FILES['images']['type'][$i] ?? '',
                'tmp_name' => $_FILES['images']['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $_FILES['images']['size'][$i] ?? 0,
            ];
        }
    }

    if (!$files && isset($_FILES['image'])) {
        $error = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_NO_FILE) {
            $files[] = [
                'name' => $_FILES['image']['name'] ?? '',
                'type' => $_FILES['image']['type'] ?? '',
                'tmp_name' => $_FILES['image']['tmp_name'] ?? '',
                'error' => $error,
                'size' => $_FILES['image']['size'] ?? 0,
            ];
        }
    }

    return $files;
}

function cleanupUploadedFiles(string $uploadDir, array $filenames): void
{
    foreach ($filenames as $filename) {
        $path = $uploadDir . $filename;
        if ($filename && file_exists($path)) {
            @unlink($path);
        }
    }
}

function parseNewImageOrder($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) {
        return [];
    }

    $order = [];
    foreach ($decoded as $entry) {
        $index = (int)$entry;
        if ($index >= 0 && !in_array($index, $order, true)) {
            $order[] = $index;
        }
    }

    return $order;
}

if (
    empty($_POST['category_id']) ||
    empty($_POST['title']) ||
    empty($_POST['size']) ||
    empty($_POST['download'])
) {
    echo t('messages.field_missing', 'Eksik alan var!');
    exit;
}

$uploadedImages = normalizeUploadedImages();
if (!$uploadedImages) {
    echo t('messages.field_missing', 'Eksik alan var!');
    exit;
}

$uploadDir = __DIR__ . '/upload/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/svg+xml',
    'text/xml',
    'application/xml',
];

$validatedImages = [];
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

foreach ($uploadedImages as $image) {
    $ext = strtolower(pathinfo((string)($image['name'] ?? ''), PATHINFO_EXTENSION));
    if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !in_array($ext, $allowedExtensions, true)) {
        if ($finfo) {
            finfo_close($finfo);
        }
        echo t('messages.invalid_file', 'Geçersiz dosya!');
        exit;
    }

    $tmpName = (string)($image['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        if ($finfo) {
            finfo_close($finfo);
        }
        echo t('messages.invalid_file', 'Geçersiz dosya!');
        exit;
    }

    $mimeType = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
    if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
        if (!($ext === 'svg' && in_array($mimeType, ['text/plain', 'application/octet-stream'], true))) {
            if ($finfo) {
                finfo_close($finfo);
            }
            echo t('messages.invalid_file', 'Geçersiz dosya!');
            exit;
        }
    }

    $validatedImages[] = [
        'extension' => $ext,
        'tmp_name' => $tmpName,
    ];
}

if ($finfo) {
    finfo_close($finfo);
}

$requestedNewImageOrder = parseNewImageOrder($_POST['new_image_order'] ?? '');
if ($requestedNewImageOrder) {
    $orderedUploads = [];
    foreach ($requestedNewImageOrder as $index) {
        if (isset($validatedImages[$index])) {
            $orderedUploads[] = $validatedImages[$index];
        }
    }
    foreach ($validatedImages as $index => $image) {
        if (!in_array($index, $requestedNewImageOrder, true)) {
            $orderedUploads[] = $image;
        }
    }
    $validatedImages = $orderedUploads;
}

$savedFilenames = [];

foreach ($validatedImages as $image) {
    $filename = uniqid('img_', true) . '.' . $image['extension'];
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($image['tmp_name'], $targetFile)) {
        cleanupUploadedFiles($uploadDir, $savedFilenames);
        echo t('messages.upload_failed', 'Dosya yüklenemedi!');
        exit;
    }

    $savedFilenames[] = $filename;
}

$coverFilename = $savedFilenames[0] ?? '';
$hasImageMediaTable = false;

try {
    $hasImageMediaTable = (bool)$pdo->query("SHOW TABLES LIKE 'image_media'")->fetchColumn();
} catch (Throwable $e) {
    $hasImageMediaTable = false;
}

$imageId = null;
$createdAt = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare(
        "INSERT INTO images (category_id, title, filename, size, download_link, created_at) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $success = $stmt->execute([
        $_POST['category_id'],
        $_POST['title'],
        $coverFilename,
        $_POST['size'],
        $_POST['download'],
        $createdAt
    ]);

    if (!$success) {
        cleanupUploadedFiles($uploadDir, $savedFilenames);
        echo t('messages.database_error', 'Veritabanı hatası!');
        exit;
    }

    $imageId = (int)$pdo->lastInsertId();

    if ($hasImageMediaTable) {
        $mediaStmt = $pdo->prepare(
            "INSERT INTO image_media (image_id, filename, is_cover, sort_order, created_at) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($savedFilenames as $index => $filename) {
            $mediaStmt->execute([
                $imageId,
                $filename,
                $index === 0 ? 1 : 0,
                $index,
                $createdAt
            ]);
        }
    }

    echo "OK";
} catch (Throwable $e) {
    cleanupUploadedFiles($uploadDir, $savedFilenames);

    if ($imageId) {
        try {
            if ($hasImageMediaTable) {
                $cleanupMediaStmt = $pdo->prepare("DELETE FROM image_media WHERE image_id = ?");
                $cleanupMediaStmt->execute([$imageId]);
            }

            $cleanupImageStmt = $pdo->prepare("DELETE FROM images WHERE id = ?");
            $cleanupImageStmt->execute([$imageId]);
        } catch (Throwable $cleanupError) {
            // Best effort cleanup only.
        }
    }

    echo t('messages.database_error', 'Veritabanı hatası!');
}
exit;
