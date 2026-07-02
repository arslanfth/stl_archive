<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('html');
require_once __DIR__ . '/includes/lang.php';

function redirectToApp(string $messageKey): void
{
    $query = [
        'drive_settings' => '1',
        'oauth_error' => $messageKey,
    ];

    header('Location: index.php?' . http_build_query($query));
    exit;
}

try {
    $stmt = $pdo->query("SELECT client_id, client_secret, redirect_uri FROM google_drive_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch();

    $clientId = trim((string) ($settings['client_id'] ?? ''));
    $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
    $redirectUri = trim((string) ($settings['redirect_uri'] ?? ''));

    if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
        redirectToApp('settings.connect_missing');
    }

    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri($redirectUri);
    $client->addScope(Google_Service_Drive::DRIVE_READONLY);
    $client->setAccessType('offline');
    $client->setPrompt('consent');

    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit;
} catch (Throwable $e) {
    redirectToApp('settings.connect_error');
}
