<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query(
        "SELECT client_id, redirect_uri, folder_id, is_connected, client_secret
         FROM google_drive_settings
         WHERE id = 1
         LIMIT 1"
    );
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        echo json_encode([
            'success' => true,
            'client_id' => '',
            'redirect_uri' => '',
            'folder_id' => '',
            'is_connected' => 0,
            'secret_configured' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'success' => true,
        'client_id' => (string) ($settings['client_id'] ?? ''),
        'redirect_uri' => (string) ($settings['redirect_uri'] ?? ''),
        'folder_id' => (string) ($settings['folder_id'] ?? ''),
        'is_connected' => (int) ($settings['is_connected'] ?? 0),
        'secret_configured' => trim((string) ($settings['client_secret'] ?? '')) !== '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => t('settings.load_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

