<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('html');
require_once __DIR__ . '/includes/lang.php';

function redirectToApp(string $status, string $messageKey): void
{
    $query = [
        'drive_settings' => '1',
    ];

    if ($status === 'success') {
        $query['oauth_success'] = '1';
    } else {
        $query['oauth_error'] = $messageKey;
    }

    header('Location: index.php?' . http_build_query($query));
    exit;
}

if (!isset($_GET['code']) || trim((string) $_GET['code']) === '') {
    $error = trim((string) ($_GET['error'] ?? ''));
    redirectToApp('error', $error !== '' ? 'oauth_' . $error : 'settings.callback_error');
}

try {
    $stmt = $pdo->query("SELECT client_id, client_secret, redirect_uri, refresh_token FROM google_drive_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch();

    $clientId = trim((string) ($settings['client_id'] ?? ''));
    $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
    $redirectUri = trim((string) ($settings['redirect_uri'] ?? ''));
    $existingRefreshToken = trim((string) ($settings['refresh_token'] ?? ''));

    if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
        redirectToApp('error', 'settings.connect_missing');
    }

    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri($redirectUri);
    $client->addScope(Google_Service_Drive::DRIVE_READONLY);
    $client->setAccessType('offline');
    $client->setPrompt('consent');

    $token = $client->fetchAccessTokenWithAuthCode((string) $_GET['code']);
    if (!is_array($token) || isset($token['error'])) {
        redirectToApp('error', 'settings.callback_error');
    }

    $accessToken = trim((string) ($token['access_token'] ?? ''));
    if ($accessToken === '') {
        redirectToApp('error', 'settings.callback_error');
    }

    $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        $refreshToken = $existingRefreshToken;
    }

    $tokenType = trim((string) ($token['token_type'] ?? ''));
    $scope = trim((string) ($token['scope'] ?? Google_Service_Drive::DRIVE_READONLY));
    $created = isset($token['created']) ? (int) $token['created'] : time();
    $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 0;
    $expiresAt = null;
    if ($expiresIn > 0) {
        $expiresAt = date('Y-m-d H:i:s', $created + $expiresIn);
    }

    $storedToken = $token;
    if (!isset($storedToken['refresh_token']) && $refreshToken !== '') {
        $storedToken['refresh_token'] = $refreshToken;
    }

    $update = $pdo->prepare(
        "UPDATE google_drive_settings
         SET access_token = :access_token,
             refresh_token = :refresh_token,
             token_type = :token_type,
             scope = :scope,
             expires_at = :expires_at,
             raw_token_json = :raw_token_json,
             is_connected = 1
         WHERE id = 1"
    );
    $update->execute([
        ':access_token' => $accessToken,
        ':refresh_token' => $refreshToken !== '' ? $refreshToken : null,
        ':token_type' => $tokenType !== '' ? $tokenType : null,
        ':scope' => $scope !== '' ? $scope : null,
        ':expires_at' => $expiresAt,
        ':raw_token_json' => json_encode($storedToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    redirectToApp('success', 'settings.connect_success');
} catch (Throwable $e) {
    redirectToApp('error', 'settings.callback_error');
}
