<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('json');
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/formatting.php';

header('Content-Type: application/json; charset=utf-8');

function syncResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getDbDriveSyncSettings(PDO $pdo): ?array
{
    try {
        $stmt = $pdo->query(
            "SELECT client_id, client_secret, redirect_uri, folder_id, access_token, refresh_token, token_type, scope, expires_at, raw_token_json, is_connected
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

function hasDbDriveSyncConfig(?array $settings): bool
{
    if (!$settings) {
        return false;
    }

    $clientId = trim((string) ($settings['client_id'] ?? ''));
    $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
    $folderId = trim((string) ($settings['folder_id'] ?? ''));
    $accessToken = trim((string) ($settings['access_token'] ?? ''));
    $refreshToken = trim((string) ($settings['refresh_token'] ?? ''));
    $rawTokenJson = trim((string) ($settings['raw_token_json'] ?? ''));

    return $clientId !== ''
        && $clientSecret !== ''
        && $folderId !== ''
        && ($accessToken !== '' || $refreshToken !== '' || $rawTokenJson !== '');
}

function missingDriveSettingsResponse(): array
{
    return [
        'status' => 'error',
        'success' => false,
        'auth_required' => true,
        'message' => t('messages.drive_settings_missing'),
    ];
}

function driveReauthResponse(): array
{
    return [
        'status' => 'error',
        'success' => false,
        'auth_required' => true,
        'message' => t('messages.drive_reauth_needed'),
    ];
}

function createDbDriveClient(array $settings): Google_Client
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

function buildDbDriveToken(array $settings): array
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

function normalizeDriveCreatedAt(?string $createdAt): string
{
    $fallback = date('Y-m-d H:i:s');
    $createdAt = trim((string) $createdAt);

    if ($createdAt === '') {
        return $fallback;
    }

    try {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Istanbul');
        return (new DateTimeImmutable($createdAt))
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $fallback;
    }
}

function performDriveSync(PDO $pdo, Google_Service_Drive $service, string $folderId): int
{
    $pageToken = null;
    $addedCount = 0;

    do {
        $params = [
            'q' => "'$folderId' in parents and trashed = false",
            'fields' => 'nextPageToken, files(id, name, mimeType, createdTime, size, webContentLink)',
            'pageToken' => $pageToken,
            'pageSize' => 100,
        ];
        $results = $service->files->listFiles($params);

        foreach ($results->getFiles() as $file) {
            if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                continue;
            }

            $driveId = $file->getId();
            $title = $file->getName();
            $created = normalizeDriveCreatedAt($file->getCreatedTime());
            $sizeBytes = $file->getSize();
            $sizeStr = $sizeBytes ? formatFileSize($sizeBytes) : '-';
            $download = "https://drive.google.com/uc?export=download&id=$driveId";

            $check = $pdo->prepare("SELECT COUNT(*) FROM images WHERE drive_file_id = ?");
            $check->execute([$driveId]);
            if ($check->fetchColumn() > 0) {
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO images (category_id, title, size, download_link, created_at, drive_file_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                null,
                $title,
                $sizeStr,
                $download,
                $created,
                $driveId,
            ]);
            $addedCount++;
        }

        $pageToken = $results->getNextPageToken();
    } while ($pageToken);

    return $addedCount;
}

function runDbDriveSync(PDO $pdo, array $settings): array
{
    $client = createDbDriveClient($settings);
    $token = buildDbDriveToken($settings);
    $folderId = trim((string) ($settings['folder_id'] ?? ''));

    if ($folderId === '') {
        return missingDriveSettingsResponse();
    }

    if (empty($token['access_token']) && empty($token['refresh_token'])) {
        return driveReauthResponse();
    }

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        if (!$refreshToken && !empty($token['refresh_token'])) {
            $refreshToken = $token['refresh_token'];
        }

        if (!$refreshToken) {
            $pdo->prepare("UPDATE google_drive_settings SET is_connected = 0 WHERE id = 1")->execute();
            return driveReauthResponse();
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (!is_array($newToken) || isset($newToken['error'])) {
            $pdo->prepare("UPDATE google_drive_settings SET is_connected = 0 WHERE id = 1")->execute();
            return driveReauthResponse();
        }

        if (!isset($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refreshToken;
        }

        updateDbDriveTokenState($pdo, $newToken, 1);
        $client->setAccessToken($newToken);
    }

    $service = new Google_Service_Drive($client);
    $addedCount = performDriveSync($pdo, $service, $folderId);

    return [
        'status' => 'success',
        'success' => true,
        'auth_required' => false,
        'message' => sprintf(t('messages.drive_added_count', "Drive'dan yeni eklenen dosya sayÄ±sÄ±: %d"), $addedCount),
    ];
}

try {
    $dbSettings = getDbDriveSyncSettings($pdo);
    $response = hasDbDriveSyncConfig($dbSettings)
        ? runDbDriveSync($pdo, $dbSettings)
        : missingDriveSettingsResponse();
} catch (Throwable $e) {
    $response = [
        'status' => 'error',
        'success' => false,
        'auth_required' => false,
        'message' => $e->getMessage(),
    ];
}

syncResponse($response);


