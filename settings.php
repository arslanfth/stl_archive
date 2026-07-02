<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('html');
require_once __DIR__ . '/includes/lang.php';

$msg = '';
$errorMsg = '';

if (isset($_GET['oauth_success'])) {
    $msg = trim((string) $_GET['oauth_success']);
}
if (isset($_GET['oauth_error'])) {
    $errorMsg = trim((string) $_GET['oauth_error']);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/3d-release/settings.php')), '/');
$baseUrl = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
$defaultRedirectUri = $baseUrl . '/google_oauth_callback.php';

$driveSettings = [
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => $defaultRedirectUri,
    'folder_id' => '',
    'is_connected' => 0,
];

try {
    $stmt = $pdo->query("SELECT client_id, client_secret, redirect_uri, folder_id, is_connected FROM google_drive_settings WHERE id = 1 LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        $driveSettings['client_id'] = (string) ($row['client_id'] ?? '');
        $driveSettings['client_secret'] = (string) ($row['client_secret'] ?? '');
        $driveSettings['redirect_uri'] = trim((string) ($row['redirect_uri'] ?? '')) ?: $defaultRedirectUri;
        $driveSettings['folder_id'] = (string) ($row['folder_id'] ?? '');
        $driveSettings['is_connected'] = (int) ($row['is_connected'] ?? 0);
    }
} catch (Throwable $e) {
    if ($errorMsg === '') {
        $errorMsg = t('settings.integration_save_error', 'Google Drive entegrasyon ayarları kaydedilemedi.');
    }
}

$currentId = $driveSettings['folder_id'] !== '' ? $driveSettings['folder_id'] : 'FOLDER_ID_HERE';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_drive_settings'])) {
    $clientId = trim((string) ($_POST['client_id'] ?? ''));
    $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
    $redirectUri = trim((string) ($_POST['redirect_uri'] ?? ''));
    $folderId = trim((string) ($_POST['drive_folder_id'] ?? ''));

    if ($redirectUri === '') {
        $redirectUri = $defaultRedirectUri;
    }

    if ($folderId === '') {
        $errorMsg = t('settings.drive_folder_required', 'Folder ID boş olamaz!');
    } else {
        try {
            if ($clientSecret !== '') {
                $updateDriveSettings = $pdo->prepare(
                    "UPDATE google_drive_settings
                     SET client_id = :client_id,
                         client_secret = :client_secret,
                         redirect_uri = :redirect_uri,
                         folder_id = :folder_id
                     WHERE id = 1"
                );
                $updateDriveSettings->execute([
                    ':client_id' => $clientId,
                    ':client_secret' => $clientSecret,
                    ':redirect_uri' => $redirectUri,
                    ':folder_id' => $folderId,
                ]);
            } else {
                $updateDriveSettings = $pdo->prepare(
                    "UPDATE google_drive_settings
                     SET client_id = :client_id,
                         redirect_uri = :redirect_uri,
                         folder_id = :folder_id
                     WHERE id = 1"
                );
                $updateDriveSettings->execute([
                    ':client_id' => $clientId,
                    ':redirect_uri' => $redirectUri,
                    ':folder_id' => $folderId,
                ]);
            }

            $msg = t('settings.integration_saved', 'Google Drive entegrasyon ayarları kaydedildi!');
            $driveSettings['client_id'] = $clientId;
            if ($clientSecret !== '') {
                $driveSettings['client_secret'] = $clientSecret;
            }
            $driveSettings['redirect_uri'] = $redirectUri;
            $driveSettings['folder_id'] = $folderId;
            $currentId = $folderId;
        } catch (Throwable $e) {
            $errorMsg = t('settings.integration_save_error', 'Google Drive entegrasyon ayarları kaydedilemedi.');
        }
    }
}

$canStartOauth = $driveSettings['client_id'] !== ''
    && $driveSettings['client_secret'] !== ''
    && trim((string) $driveSettings['redirect_uri']) !== '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(t('settings.page_title', 'Google Drive Ayarları')) ?></title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #141824;
    color: #e6e6e6;
    padding: 30px;
}
.settings-shell {
    max-width: 860px;
}
.settings-card {
    margin-top: 22px;
    padding: 22px;
    border: 1px solid #2b3350;
    border-radius: 16px;
    background: #1a2030;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
}
.settings-card h3 {
    margin: 0 0 8px;
}
.settings-card p {
    margin: 0 0 18px;
    color: #aab4da;
}
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.settings-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.settings-field--full {
    grid-column: 1 / -1;
}
label {
    font-size: 0.95rem;
}
input[type=text],
input[type=password] {
    padding: 11px 12px;
    width: 100%;
    border-radius: 8px;
    border: 1px solid #465074;
    background: #101521;
    color: #f5f7ff;
    box-sizing: border-box;
}
button,
.button-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
    background: #6366f1;
    color: #fff;
    cursor: pointer;
    margin-right: 8px;
    text-decoration: none;
}
.button-link.is-disabled {
    background: #2f3650;
    color: #aab4da;
    cursor: not-allowed;
    pointer-events: none;
}
small {
    color: #9aa3c7;
    line-height: 1.45;
}
.msg {
    margin-top: 15px;
    color: #1ca854;
}
.error {
    margin-top: 15px;
    color: #ed2e3c;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.9rem;
    border: 1px solid #39425e;
    background: #101521;
}
.status-pill.connected {
    color: #8ff0b6;
    border-color: #23583b;
    background: rgba(28, 168, 84, 0.12);
}
.status-pill.disconnected {
    color: #ffb4b4;
    border-color: #6b2b32;
    background: rgba(237, 46, 60, 0.12);
}
.settings-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
    flex-wrap: wrap;
}
@media (max-width: 720px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="settings-shell">
    <h2><?= htmlspecialchars(t('settings.heading', 'Google Drive Ayarları')) ?></h2>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="settings-card">
        <h3><?= htmlspecialchars(t('settings.integration_heading', 'Google Drive Entegrasyonu')) ?></h3>
        <p><?= htmlspecialchars(t('settings.redirect_uri_help', 'Google Cloud Console tarafında yetkili Redirect URI olarak bunu ekleyin.')) ?></p>

        <form method="post">
            <div class="settings-grid">
                <div class="settings-field settings-field--full">
                    <label><?= htmlspecialchars(t('settings.connection_status', 'Bağlantı Durumu')) ?></label>
                    <div>
                        <span class="status-pill <?= !empty($driveSettings['is_connected']) ? 'connected' : 'disconnected' ?>">
                            <?= htmlspecialchars(!empty($driveSettings['is_connected']) ? t('settings.connected', 'Bağlı') : t('settings.not_connected', 'Bağlı değil')) ?>
                        </span>
                    </div>
                </div>

                <div class="settings-field settings-field--full">
                    <label for="client_id"><?= htmlspecialchars(t('settings.client_id_label', 'OAuth Client ID')) ?></label>
                    <input type="text" id="client_id" name="client_id" value="<?= htmlspecialchars($driveSettings['client_id']) ?>">
                </div>

                <div class="settings-field settings-field--full">
                    <label for="client_secret"><?= htmlspecialchars(t('settings.client_secret_label', 'OAuth Client Secret')) ?></label>
                    <input type="password" id="client_secret" name="client_secret" value="">
                    <small><?= htmlspecialchars(t('settings.client_secret_help', 'Boş bırakırsanız mevcut secret korunur.')) ?></small>
                </div>

                <div class="settings-field settings-field--full">
                    <label for="redirect_uri"><?= htmlspecialchars(t('settings.redirect_uri_label', 'Redirect URI')) ?></label>
                    <input type="text" id="redirect_uri" name="redirect_uri" value="<?= htmlspecialchars($driveSettings['redirect_uri']) ?>">
                    <small><?= htmlspecialchars(t('settings.redirect_uri_help', 'Google Cloud Console tarafında yetkili Redirect URI olarak bunu ekleyin.')) ?></small>
                    <small><?= htmlspecialchars($driveSettings['redirect_uri']) ?></small>
                </div>

                <div class="settings-field settings-field--full">
                    <label for="drive_folder_id"><?= htmlspecialchars(t('settings.folder_id_new_label', 'Drive Klasör ID')) ?></label>
                    <input type="text" id="drive_folder_id" name="drive_folder_id" value="<?= htmlspecialchars($currentId) ?>">
                </div>
            </div>

            <div class="settings-actions">
                <button type="submit" name="save_drive_settings" value="1"><?= htmlspecialchars(t('actions.save', 'Kaydet')) ?></button>
                <a
                    href="<?= $canStartOauth ? 'google_oauth_start.php' : '#' ?>"
                    class="button-link<?= $canStartOauth ? '' : ' is-disabled' ?>"
                    aria-disabled="<?= $canStartOauth ? 'false' : 'true' ?>"
                >
                    <?= htmlspecialchars(!empty($driveSettings['is_connected']) ? t('settings.reconnect_button', 'Google Drive’a Yeniden Bağlan') : t('settings.connect_button', 'Google Drive’a Bağlan')) ?>
                </a>
            </div>
            <?php if (!$canStartOauth): ?>
                <small><?= htmlspecialchars(t('settings.connect_missing', 'Bağlanmak için önce Client ID, Client Secret ve Redirect URI alanlarını doldurun.')) ?></small>
            <?php endif; ?>
        </form>
    </div>
</div>
</body>
</html>
