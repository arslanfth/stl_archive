<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => t('messages.invalid_request'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$clientId = trim((string) ($_POST['client_id'] ?? ''));
$clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
$redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
$folderId = trim((string) ($_POST['folder_id'] ?? ''));

try {
    $existingStmt = $pdo->query("SELECT id FROM google_drive_settings WHERE id = 1 LIMIT 1");
    $exists = (bool) $existingStmt->fetchColumn();

    if (!$exists) {
        $insertStmt = $pdo->prepare(
            "INSERT INTO google_drive_settings
             (id, client_id, client_secret, redirect_uri, folder_id, access_token, refresh_token, token_type, scope, expires_at, raw_token_json, is_connected)
             VALUES (1, :client_id, :client_secret, :redirect_uri, :folder_id, NULL, NULL, NULL, NULL, NULL, NULL, 0)"
        );
        $insertStmt->execute([
            ':client_id' => $clientId !== '' ? $clientId : null,
            ':client_secret' => $clientSecret !== '' ? $clientSecret : null,
            ':redirect_uri' => $redirectUri !== '' ? $redirectUri : null,
            ':folder_id' => $folderId !== '' ? $folderId : null,
        ]);
    } elseif ($clientSecret !== '') {
        $updateStmt = $pdo->prepare(
            "UPDATE google_drive_settings
             SET client_id = :client_id,
                 client_secret = :client_secret,
                 redirect_uri = :redirect_uri,
                 folder_id = :folder_id
             WHERE id = 1"
        );
        $updateStmt->execute([
            ':client_id' => $clientId !== '' ? $clientId : null,
            ':client_secret' => $clientSecret,
            ':redirect_uri' => $redirectUri !== '' ? $redirectUri : null,
            ':folder_id' => $folderId !== '' ? $folderId : null,
        ]);
    } else {
        $updateStmt = $pdo->prepare(
            "UPDATE google_drive_settings
             SET client_id = :client_id,
                 redirect_uri = :redirect_uri,
                 folder_id = :folder_id
             WHERE id = 1"
        );
        $updateStmt->execute([
            ':client_id' => $clientId !== '' ? $clientId : null,
            ':redirect_uri' => $redirectUri !== '' ? $redirectUri : null,
            ':folder_id' => $folderId !== '' ? $folderId : null,
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => t('settings.integration_saved'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => t('settings.integration_save_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

