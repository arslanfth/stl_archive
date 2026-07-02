<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

function normalizeEditUploads(): array
{
    $files = [];

    if (isset($_FILES['new_images']) && is_array($_FILES['new_images']['name'] ?? null)) {
        $count = count($_FILES['new_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $_FILES['new_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $files[] = [
                'name' => $_FILES['new_images']['name'][$i] ?? '',
                'type' => $_FILES['new_images']['type'][$i] ?? '',
                'tmp_name' => $_FILES['new_images']['tmp_name'][$i] ?? '',
                'error' => $error,
                'size' => $_FILES['new_images']['size'][$i] ?? 0,
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

function parseMediaOrderPayload($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry) || !isset($entry['type'])) {
            continue;
        }

        $type = (string)$entry['type'];
        if ($type === 'existing') {
            $id = isset($entry['id']) ? (int)$entry['id'] : 0;
            if ($id > 0) {
                $items[] = ['type' => 'existing', 'id' => $id];
            }
            continue;
        }

        if ($type === 'new') {
            $index = isset($entry['index']) ? (int)$entry['index'] : -1;
            if ($index >= 0) {
                $items[] = ['type' => 'new', 'index' => $index];
            }
        }
    }

    return $items;
}

if (
    empty($_POST['id']) ||
    empty($_POST['category_id']) ||
    empty($_POST['title']) ||
    empty($_POST['size']) ||
    empty($_POST['download'])
) {
    echo t('messages.field_missing', 'Eksik alan var!');
    exit;
}

$id = (int)$_POST['id'];
$uploadDir = __DIR__ . '/upload/';
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

$rowStmt = $pdo->prepare("SELECT id, filename FROM images WHERE id = ?");
$rowStmt->execute([$id]);
$imageRow = $rowStmt->fetch(PDO::FETCH_ASSOC);
if (!$imageRow) {
    echo t('messages.record_not_found', 'Kayıt bulunamadı!');
    exit;
}

$hasImageMediaTable = false;
try {
    $hasImageMediaTable = (bool)$pdo->query("SHOW TABLES LIKE 'image_media'")->fetchColumn();
} catch (Throwable $e) {
    $hasImageMediaTable = false;
}

$removedMediaIds = array_values(array_unique(array_map('intval', (array)($_POST['removed_media_ids'] ?? []))));
$requestedCoverImageId = isset($_POST['cover_image_id']) ? (int)$_POST['cover_image_id'] : 0;
$requestedCoverNewUploadIndex = isset($_POST['cover_new_upload_index']) ? (int)$_POST['cover_new_upload_index'] : -1;
$requestedMediaOrder = parseMediaOrderPayload($_POST['media_order'] ?? '');
$uploadedImages = normalizeEditUploads();
$validatedUploads = [];
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

foreach ($uploadedImages as $image) {
    $ext = strtolower(pathinfo((string)($image['name'] ?? ''), PATHINFO_EXTENSION));
    if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !in_array($ext, $allowedExtensions, true)) {
        if ($finfo) {
            finfo_close($finfo);
        }
        echo t('messages.invalid_file_type', 'Geçersiz dosya tipi!');
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
            echo t('messages.invalid_file_type', 'Geçersiz dosya tipi!');
            exit;
        }
    }

    $validatedUploads[] = [
        'extension' => $ext,
        'tmp_name' => $tmpName,
    ];
}

if ($finfo) {
    finfo_close($finfo);
}

$savedFilenames = [];

foreach ($validatedUploads as $upload) {
    $filename = uniqid('img_', true) . '.' . $upload['extension'];
    $targetFile = $uploadDir . $filename;

    if (!move_uploaded_file($upload['tmp_name'], $targetFile)) {
        cleanupUploadedFiles($uploadDir, $savedFilenames);
        echo t('messages.upload_failed', 'Dosya yüklenemedi!');
        exit;
    }

    $savedFilenames[] = $filename;
}

$deletedFileCandidates = [];

try {
    $newCoverFilename = (string)($imageRow['filename'] ?? '');

    if ($hasImageMediaTable) {
        $existingMediaStmt = $pdo->prepare(
            "SELECT id, filename, is_cover, sort_order
             FROM image_media
             WHERE image_id = ?
             ORDER BY sort_order ASC, id ASC"
        );
        $existingMediaStmt->execute([$id]);
        $existingMedia = $existingMediaStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingById = [];
        foreach ($existingMedia as $mediaRow) {
            $existingById[(int)$mediaRow['id']] = $mediaRow;
        }

        $validRemovedIds = [];
        foreach ($removedMediaIds as $mediaId) {
            if ($mediaId > 0 && isset($existingById[$mediaId])) {
                $validRemovedIds[] = $mediaId;
                $deletedFileCandidates[] = (string)$existingById[$mediaId]['filename'];
            }
        }

        if ($validRemovedIds) {
            $placeholders = implode(',', array_fill(0, count($validRemovedIds), '?'));
            $deleteMediaStmt = $pdo->prepare("DELETE FROM image_media WHERE image_id = ? AND id IN ($placeholders)");
            $deleteMediaStmt->execute(array_merge([$id], $validRemovedIds));
        }

        $maxSortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM image_media WHERE image_id = ?");
        $maxSortStmt->execute([$id]);
        $sortOrder = (int)$maxSortStmt->fetchColumn() + 1;

        $insertedMediaIds = [];

        if ($savedFilenames) {
            $createdAt = date('Y-m-d H:i:s');
            $insertMediaStmt = $pdo->prepare(
                "INSERT INTO image_media (image_id, filename, is_cover, sort_order, created_at) VALUES (?, ?, 0, ?, ?)"
            );
            foreach ($savedFilenames as $filename) {
                $insertMediaStmt->execute([$id, $filename, $sortOrder, $createdAt]);
                $insertedMediaIds[] = (int)$pdo->lastInsertId();
                $sortOrder++;
            }
        }

        $activeMediaStmt = $pdo->prepare(
            "SELECT id, filename, is_cover, sort_order
             FROM image_media
             WHERE image_id = ?
             ORDER BY sort_order ASC, id ASC"
        );
        $activeMediaStmt->execute([$id]);
        $activeMedia = $activeMediaStmt->fetchAll(PDO::FETCH_ASSOC);

        $activeById = [];
        $orderedMediaIds = [];
        $currentCoverRow = null;
        foreach ($activeMedia as $mediaRow) {
            $mediaRowId = (int)($mediaRow['id'] ?? 0);
            if ($mediaRowId <= 0) {
                continue;
            }

            $activeById[$mediaRowId] = $mediaRow;
            if ($currentCoverRow === null && (int)($mediaRow['is_cover'] ?? 0) === 1) {
                $currentCoverRow = $mediaRow;
            }
        }

        foreach ($requestedMediaOrder as $orderItem) {
            if (($orderItem['type'] ?? '') === 'existing') {
                $existingId = (int)($orderItem['id'] ?? 0);
                if ($existingId > 0 && isset($activeById[$existingId]) && !in_array($existingId, $orderedMediaIds, true)) {
                    $orderedMediaIds[] = $existingId;
                }
                continue;
            }

            if (($orderItem['type'] ?? '') === 'new') {
                $newIndex = (int)($orderItem['index'] ?? -1);
                if ($newIndex >= 0 && isset($insertedMediaIds[$newIndex])) {
                    $newMediaId = (int)$insertedMediaIds[$newIndex];
                    if ($newMediaId > 0 && isset($activeById[$newMediaId]) && !in_array($newMediaId, $orderedMediaIds, true)) {
                        $orderedMediaIds[] = $newMediaId;
                    }
                }
            }
        }

        foreach ($activeMedia as $mediaRow) {
            $mediaRowId = (int)($mediaRow['id'] ?? 0);
            if ($mediaRowId > 0 && !in_array($mediaRowId, $orderedMediaIds, true)) {
                $orderedMediaIds[] = $mediaRowId;
            }
        }

        if ($orderedMediaIds) {
            $updateSortStmt = $pdo->prepare("UPDATE image_media SET sort_order = ? WHERE id = ? AND image_id = ?");
            foreach ($orderedMediaIds as $sortIndex => $mediaId) {
                $updateSortStmt->execute([$sortIndex, $mediaId, $id]);
                if (isset($activeById[$mediaId])) {
                    $activeById[$mediaId]['sort_order'] = $sortIndex;
                }
            }
        }

        $resolvedCoverId = 0;
        if ($requestedCoverImageId > 0 && isset($activeById[$requestedCoverImageId])) {
            $resolvedCoverId = $requestedCoverImageId;
        } elseif ($requestedCoverNewUploadIndex >= 0 && isset($insertedMediaIds[$requestedCoverNewUploadIndex])) {
            $resolvedCoverId = (int)$insertedMediaIds[$requestedCoverNewUploadIndex];
        } elseif ($currentCoverRow && isset($activeById[(int)$currentCoverRow['id']])) {
            $resolvedCoverId = (int)$currentCoverRow['id'];
        } elseif ($activeMedia) {
            $resolvedCoverId = (int)$activeMedia[0]['id'];
        }

        $resetCoverStmt = $pdo->prepare("UPDATE image_media SET is_cover = 0 WHERE image_id = ?");
        $resetCoverStmt->execute([$id]);

        if ($resolvedCoverId > 0 && isset($activeById[$resolvedCoverId])) {
            $setCoverStmt = $pdo->prepare("UPDATE image_media SET is_cover = 1 WHERE id = ? AND image_id = ?");
            $setCoverStmt->execute([$resolvedCoverId, $id]);
            $newCoverFilename = (string)$activeById[$resolvedCoverId]['filename'];
        } else {
            $newCoverFilename = '';
        }
    } elseif ($savedFilenames) {
        $newCoverFilename = $savedFilenames[0];
        $oldFilename = (string)($imageRow['filename'] ?? '');
        if ($oldFilename && file_exists($uploadDir . $oldFilename)) {
            @unlink($uploadDir . $oldFilename);
        }
    }

    $updateStmt = $pdo->prepare(
        "UPDATE images SET category_id = ?, title = ?, size = ?, download_link = ?, filename = ? WHERE id = ?"
    );
    $ok = $updateStmt->execute([
        $_POST['category_id'],
        $_POST['title'],
        $_POST['size'],
        $_POST['download'],
        $newCoverFilename,
        $id
    ]);

    if (!$ok) {
        throw new RuntimeException(t('messages.database_error', 'Veritabanı hatası!'));
    }

    foreach ($deletedFileCandidates as $filename) {
        $path = $uploadDir . $filename;
        if ($filename && file_exists($path)) {
            @unlink($path);
        }
    }

    echo "OK";
} catch (Throwable $e) {
    cleanupUploadedFiles($uploadDir, $savedFilenames);
    echo t('messages.error_prefix', 'Hata:') . ' ' . $e->getMessage();
}
exit;
