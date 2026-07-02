<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/formatting.php';

header('Content-Type: application/json; charset=utf-8');

function driveStorageResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function formatDriveBytes($bytes): string
{
    return formatFileSize($bytes);
}

function buildDriveStoragePayload($quota): array
{
    $usage = (float) ($quota ? $quota->getUsage() : 0);
    $limit = (float) ($quota ? $quota->getLimit() : 0);
    $percent = $limit > 0 ? (int) round(($usage / $limit) * 100) : 0;
    $percent = max(0, min(100, $percent));

    $usageText = formatDriveBytes($usage);
    $limitText = $limit > 0 ? formatDriveBytes($limit) : t('common.unlimited', 'SÄ±nÄ±rsÄ±z');

    return [
        'success' => true,
        'usageText' => $usageText,
        'limitText' => $limitText,
        'label' => sprintf(t('gallery.drive_storage_label', '%s / %s kullanÄ±lÄ±yor'), $usageText, $limitText),
        'percent' => $percent,
    ];
}

function getDbDriveSettings(PDO $pdo): ?array
{
    try {
        $stmt = $pdo->query(
            "SELECT client_id, client_secret, redirect_uri, access_token, refresh_token, token_type, scope, expires_at, raw_token_json, is_connected
             FROM google_drive_settings
             WHERE id = 1
             LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function hasDbDriveConfig(?array $settings): bool
{
    if (!$settings) {
        return false;
    }

    $clientId = trim((string) ($settings['client_id'] ?? ''));
    $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
    $accessToken = trim((string) ($settings['access_token'] ?? ''));
    $refreshToken = trim((string) ($settings['refresh_token'] ?? ''));
    $rawTokenJson = trim((string) ($settings['raw_token_json'] ?? ''));

    return $clientId !== ''
        && $clientSecret !== ''
        && ($accessToken !== '' || $refreshToken !== '' || $rawTokenJson !== '');
}

function missingDriveStorageResponse(): array
{
    return [
        'success' => false,
        'auth_required' => true,
        'message' => t('messages.drive_not_connected'),
    ];
}

function createClientFromDb(array $settings): Google_Client
{
    $client = new Google_Client();
    $client->setClientId((string) $settings['client_id']);
    $client->setClientSecret((string) $settings['client_secret']);
    $client->setRedirectUri((string) ($settings['redirect_uri'] ?? ''));
    $client->addScope(Google_Service_Drive::DRIVE_READONLY);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    return $client;
}

function buildTokenFromDb(array $settings): array
{
    $rawTokenJson = trim((string) ($settings['raw_token_json'] ?? ''));
    if ($rawTokenJson !== '') {
        $decoded = json_decode($rawTokenJson, true);
        if (is_array($decoded) && (!empty($decoded['access_token']) || !empty($decoded['refresh_token']))) {
            if (empty($decoded['refresh_token']) && !empty($settings['refresh_token'])) {
                $decoded['refresh_token'] = (string) $settings['refresh_token'];
            }
            return $decoded;
        }
    }

    $token = [
        'access_token' => (string) ($settings['access_token'] ?? ''),
        'refresh_token' => (string) ($settings['refresh_token'] ?? ''),
        'token_type' => (string) ($settings['token_type'] ?? 'Bearer'),
        'scope' => (string) ($settings['scope'] ?? Google_Service_Drive::DRIVE_READONLY),
    ];

    $expiresAt = trim((string) ($settings['expires_at'] ?? ''));
    if ($expiresAt !== '') {
        $expiresTs = strtotime($expiresAt);
        if ($expiresTs !== false) {
            $token['created'] = max(0, $expiresTs - 3600);
            $token['expires_in'] = max(0, $expiresTs - time());
        }
    }

    return $token;
}

function updateDbDriveTokenState(PDO $pdo, array $token, int $isConnected = 1): void
{
    $refreshToken = trim((string) ($token['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        $existingStmt = $pdo->query("SELECT refresh_token FROM google_drive_settings WHERE id = 1 LIMIT 1");
        $existingRefreshToken = trim((string) ($existingStmt->fetchColumn() ?: ''));
        if ($existingRefreshToken !== '') {
            $refreshToken = $existingRefreshToken;
            $token['refresh_token'] = $existingRefreshToken;
        }
    }

    $created = isset($token['created']) ? (int) $token['created'] : time();
    $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 0;
    $expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', $created + $expiresIn) : null;

    $stmt = $pdo->prepare(
        "UPDATE google_drive_settings
         SET access_token = :access_token,
             refresh_token = :refresh_token,
             token_type = :token_type,
             scope = :scope,
             expires_at = :expires_at,
             raw_token_json = :raw_token_json,
             is_connected = :is_connected
         WHERE id = 1"
    );
    $stmt->execute([
        ':access_token' => trim((string) ($token['access_token'] ?? '')) !== '' ? (string) $token['access_token'] : null,
        ':refresh_token' => $refreshToken !== '' ? $refreshToken : null,
        ':token_type' => trim((string) ($token['token_type'] ?? '')) !== '' ? (string) $token['token_type'] : null,
        ':scope' => trim((string) ($token['scope'] ?? '')) !== '' ? (string) $token['scope'] : null,
        ':expires_at' => $expiresAt,
        ':raw_token_json' => json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':is_connected' => $isConnected,
    ]);
}

function runDbDriveStorageFlow(PDO $pdo, array $settings): array
{
    $client = createClientFromDb($settings);
    $token = buildTokenFromDb($settings);

    if (empty($token['access_token']) && empty($token['refresh_token'])) {
        return missingDriveStorageResponse();
    }

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        if (!$refreshToken && !empty($token['refresh_token'])) {
            $refreshToken = $token['refresh_token'];
        }

        if (!$refreshToken) {
            $pdo->prepare("UPDATE google_drive_settings SET is_connected = 0 WHERE id = 1")->execute();
            return missingDriveStorageResponse();
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($newToken) || isset($newToken['error'])) {
            $pdo->prepare("UPDATE google_drive_settings SET is_connected = 0 WHERE id = 1")->execute();
            return missingDriveStorageResponse();
        }

        if (!isset($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }

        updateDbDriveTokenState($pdo, $newToken, 1);
        $client->setAccessToken($newToken);
    }

    $service = new Google_Service_Drive($client);
    $about = $service->about->get(['fields' => 'storageQuota']);
    return buildDriveStoragePayload($about->getStorageQuota());
}

try {
    $dbSettings = getDbDriveSettings($pdo);
    driveStorageResponse(
        hasDbDriveConfig($dbSettings)
            ? runDbDriveStorageFlow($pdo, $dbSettings)
            : missingDriveStorageResponse()
    );
} catch (Throwable $e) {
    driveStorageResponse([
        'success' => false,
        'message' => t('messages.drive_storage_unavailable', 'Depolama bilgisi alÄ±namadÄ±'),
    ]);
}

