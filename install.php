<?php
require_once __DIR__ . '/includes/lang.php';

$lockFile = __DIR__ . '/install.lock';
$dbConfigPath = __DIR__ . '/includes/db.php';
$schemaPath = __DIR__ . '/schema.sql';
$vendorAutoloadPath = __DIR__ . '/vendor/autoload.php';
$uploadDir = __DIR__ . '/upload';
$includesDir = __DIR__ . '/includes';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/3d-release/install.php')), '/');
$baseUrl = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
$defaultRedirectUri = $baseUrl . '/google_oauth_callback.php';

$messages = [
    'success' => '',
    'error' => '',
];

$form = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => 'root',
    'db_pass' => '',
    'db_create' => '1',
    'google_client_id' => '',
    'google_client_secret' => '',
    'google_redirect_uri' => $defaultRedirectUri,
    'google_folder_id' => '',
];

$systemChecks = [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function startsWith(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) === 0;
}

function buildSystemChecks(string $vendorAutoloadPath, string $schemaPath, string $uploadDir, string $includesDir, string $projectRoot): array
{
    return [
        [
            'label' => 'PHP Version',
            'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'value' => PHP_VERSION,
        ],
        [
            'label' => 'vendor/autoload.php',
            'status' => is_file($vendorAutoloadPath),
            'value' => is_file($vendorAutoloadPath) ? 'Bulundu' : 'Bulunamadı',
        ],
        [
            'label' => 'schema.sql',
            'status' => is_file($schemaPath),
            'value' => is_file($schemaPath) ? 'Bulundu' : 'Bulunamadı',
        ],
        [
            'label' => 'upload/',
            'status' => is_dir($uploadDir),
            'value' => is_dir($uploadDir) ? 'Klasör mevcut' : 'Klasör eksik',
        ],
        [
            'label' => 'upload/ yazılabilir',
            'status' => is_dir($uploadDir) && is_writable($uploadDir),
            'value' => (is_dir($uploadDir) && is_writable($uploadDir)) ? 'Yazılabilir' : 'Yazılamıyor',
        ],
        [
            'label' => 'includes/ yazılabilir',
            'status' => is_dir($includesDir) && is_writable($includesDir),
            'value' => (is_dir($includesDir) && is_writable($includesDir)) ? 'Yazılabilir' : 'Yazılamıyor',
        ],
        [
            'label' => 'Proje kökü yazılabilir',
            'status' => is_writable($projectRoot),
            'value' => is_writable($projectRoot) ? 'Yazılabilir' : 'Yazılamıyor',
        ],
    ];
}

function allChecksPassed(array $checks): bool
{
    foreach ($checks as $check) {
        if (empty($check['status'])) {
            return false;
        }
    }
    return true;
}

function createServerPdo(string $host, string $port, string $user, string $pass): PDO
{
    $port = trim($port) !== '' ? trim($port) : '3306';
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function createDatabasePdo(string $host, string $port, string $dbName, string $user, string $pass): PDO
{
    $port = trim($port) !== '' ? trim($port) : '3306';
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+03:00'");

    return $pdo;
}

function splitSqlStatements(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $lines = preg_split("/\r\n|\n|\r/", $sql);
    $filtered = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || startsWith($trimmed, '-- ') || startsWith($trimmed, '--') || startsWith($trimmed, '#')) {
            continue;
        }
        $filtered[] = $line;
    }

    $sql = implode("\n", $filtered);
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        if ($char === "'" && !$inDouble && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && $prev !== '\\') {
            $inDouble = !$inDouble;
        }

        if ($char === ';' && !$inSingle && !$inDouble) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function runSchema(PDO $pdo, string $schemaPath): void
{
    $sql = (string) file_get_contents($schemaPath);
    $statements = splitSqlStatements($sql);

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function upsertGoogleDriveSettings(PDO $pdo, array $form): void
{
    $clientId = trim($form['google_client_id']) !== '' ? trim($form['google_client_id']) : null;
    $clientSecret = trim($form['google_client_secret']) !== '' ? trim($form['google_client_secret']) : null;
    $redirectUri = trim($form['google_redirect_uri']) !== '' ? trim($form['google_redirect_uri']) : null;
    $folderId = trim($form['google_folder_id']) !== '' ? trim($form['google_folder_id']) : null;

    $existsStmt = $pdo->query("SELECT COUNT(*) FROM google_drive_settings WHERE id = 1");
    $exists = (int) $existsStmt->fetchColumn() > 0;

    if ($exists) {
        $stmt = $pdo->prepare(
            "UPDATE google_drive_settings
             SET client_id = :client_id,
                 client_secret = :client_secret,
                 redirect_uri = :redirect_uri,
                 folder_id = :folder_id,
                 access_token = NULL,
                 refresh_token = NULL,
                 token_type = NULL,
                 scope = NULL,
                 expires_at = NULL,
                 raw_token_json = NULL,
                 is_connected = 0
             WHERE id = 1"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO google_drive_settings
             (id, client_id, client_secret, redirect_uri, folder_id, access_token, refresh_token, token_type, scope, expires_at, raw_token_json, is_connected)
             VALUES
             (1, :client_id, :client_secret, :redirect_uri, :folder_id, NULL, NULL, NULL, NULL, NULL, NULL, 0)"
        );
    }

    $stmt->execute([
        ':client_id' => $clientId,
        ':client_secret' => $clientSecret,
        ':redirect_uri' => $redirectUri,
        ':folder_id' => $folderId,
    ]);
}

function buildDbPhp(array $form): string
{
    $host = var_export($form['db_host'], true);
    $port = var_export($form['db_port'], true);
    $dbName = var_export($form['db_name'], true);
    $user = var_export($form['db_user'], true);
    $pass = var_export($form['db_pass'], true);

    return <<<PHP
<?php
date_default_timezone_set('Europe/Istanbul');

\$host = {$host};
\$port = {$port};
\$db   = {$dbName};
\$user = {$user};
\$pass = {$pass};
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;port=\$port;dbname=\$db;charset=\$charset";
\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
    \$pdo->exec("SET time_zone = '+03:00'");
} catch (\\PDOException \$e) {
    throw new \\PDOException(\$e->getMessage(), (int)\$e->getCode());
}
?>
PHP;
}

function writeDbConfig(string $dbConfigPath, array $form): void
{
    $content = buildDbPhp($form);
    if (is_file($dbConfigPath)) {
        @copy($dbConfigPath, $dbConfigPath . '.bak');
    }
    file_put_contents($dbConfigPath, $content);
}

function writeInstallLock(string $lockFile): void
{
    $content = "Installed at: " . date('Y-m-d H:i:s') . PHP_EOL;
    file_put_contents($lockFile, $content);
}

$systemChecks = buildSystemChecks($vendorAutoloadPath, $schemaPath, $uploadDir, $includesDir, __DIR__);
$locked = is_file($lockFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    foreach ($form as $key => $default) {
        if ($key === 'db_create') {
            $form[$key] = isset($_POST[$key]) ? '1' : '';
        } else {
            $form[$key] = trim((string) ($_POST[$key] ?? $default));
        }
    }

    if ($form['db_host'] === '' || $form['db_name'] === '' || $form['db_user'] === '') {
        $messages['error'] = 'Veritabanı host, adı ve kullanıcı alanları zorunludur.';
    } elseif (!allChecksPassed($systemChecks)) {
        $messages['error'] = 'Kuruluma başlamadan önce sistem kontrollerindeki hataları çözün.';
    } else {
        try {
            $serverPdo = createServerPdo($form['db_host'], $form['db_port'], $form['db_user'], $form['db_pass']);

            if ($form['db_create'] === '1') {
                $dbNameQuoted = str_replace('`', '``', $form['db_name']);
                $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameQuoted}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $dbPdo = createDatabasePdo($form['db_host'], $form['db_port'], $form['db_name'], $form['db_user'], $form['db_pass']);
            runSchema($dbPdo, $schemaPath);
            upsertGoogleDriveSettings($dbPdo, $form);
            writeDbConfig($dbConfigPath, $form);
            writeInstallLock($lockFile);

            $messages['success'] = 'Kurulum tamamlandı.';
            $locked = true;
        } catch (Throwable $e) {
            $messages['error'] = 'Kurulum başarısız oldu: ' . $e->getMessage();
        }
    }
    $form['db_pass'] = '';
    $form['google_client_secret'] = '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kurulum</title>
<style>
body {
    margin: 0;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: #0f1220;
    color: #f5f7ff;
}
.install-shell {
    max-width: 980px;
    margin: 0 auto;
    padding: 32px 20px 48px;
}
.install-card {
    background: #171b2e;
    border: 1px solid #2b3350;
    border-radius: 18px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.28);
    padding: 24px;
}
.install-title {
    margin: 0 0 6px;
    font-size: 2rem;
}
.install-subtitle {
    margin: 0 0 24px;
    color: #9aa3c7;
}
.status-list {
    display: grid;
    gap: 12px;
    margin: 0 0 28px;
}
.status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 16px;
    border-radius: 14px;
    background: #1e2438;
    border: 1px solid #2b3350;
}
.status-label {
    font-weight: 600;
}
.status-value {
    color: #9aa3c7;
}
.status-pill {
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
}
.ok {
    background: rgba(49, 196, 141, 0.12);
    color: #7ef0c1;
}
.fail {
    background: rgba(239, 68, 68, 0.12);
    color: #ff9e9e;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.field,
.field-full {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.field-full {
    grid-column: 1 / -1;
}
label {
    font-size: 0.95rem;
    font-weight: 600;
}
input[type="text"],
input[type="password"],
input[type="number"] {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #2b3350;
    background: #1e2438;
    color: #f5f7ff;
}
.checkbox-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 8px;
}
.section-title {
    margin: 28px 0 14px;
    font-size: 1.1rem;
}
.message {
    margin: 0 0 18px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid transparent;
}
.message.success {
    background: rgba(49, 196, 141, 0.12);
    border-color: rgba(49, 196, 141, 0.24);
    color: #d8fff0;
}
.message.error {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.24);
    color: #ffe1e1;
}
.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 24px;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 12px;
    border: 1px solid #2b3350;
    background: #1e2438;
    color: #f5f7ff;
    text-decoration: none;
    cursor: pointer;
}
.btn-primary {
    border-color: transparent;
    background: linear-gradient(135deg, #7c5cff, #9b7bff);
}
.helper {
    color: #9aa3c7;
    font-size: 0.92rem;
    line-height: 1.5;
}
.install-guide-link-row {
    margin-top: 12px;
    display: flex;
    align-items: center;
}
.install-guide-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 0;
    background: transparent;
    color: #b8a6ff;
    font: inherit;
    font-size: 0.92rem;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 0;
}
.install-guide-link-btn:hover {
    color: #d0c2ff;
    text-decoration: underline;
}
.install-guide-link-btn:focus-visible {
    outline: 2px solid rgba(124, 92, 255, 0.55);
    outline-offset: 3px;
    border-radius: 8px;
}
.install-guide-link-icon {
    width: 16px;
    height: 16px;
    border-radius: 999px;
    border: 1px solid rgba(124, 92, 255, 0.34);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    font-size: 11px;
    line-height: 1;
}
.install-guide-modal-bg {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(8, 10, 18, 0.78);
    backdrop-filter: blur(8px);
}
.install-guide-modal {
    position: relative;
    display: flex;
    flex-direction: column;
    width: min(880px, calc(100vw - 32px));
    height: min(820px, calc(100vh - 48px));
    max-height: calc(100vh - 48px);
    overflow: hidden;
    border: 1px solid #2b3350;
    border-radius: 22px;
    background: linear-gradient(180deg, rgba(23, 27, 46, 0.99), rgba(18, 22, 37, 0.99));
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.34);
}
.install-guide-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    background: rgba(255,255,255,0.03);
    color: #f5f7ff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
}
.install-guide-close:hover {
    background: rgba(124, 92, 255, 0.12);
    border-color: rgba(124, 92, 255, 0.34);
}
.install-guide-header {
    padding: 26px 28px 12px;
    padding-right: 84px;
}
.install-guide-title {
    margin: 0 0 8px;
    font-size: 1.45rem;
}
.install-guide-intro {
    margin: 0;
    color: #9aa3c7;
    line-height: 1.6;
}
.install-guide-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    padding: 10px 28px 22px;
    scrollbar-width: thin;
    scrollbar-color: rgba(124, 92, 255, 0.52) rgba(255,255,255,0.04);
}
.install-guide-body::-webkit-scrollbar,
.install-guide-code::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.install-guide-body::-webkit-scrollbar-track,
.install-guide-code::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.7);
    border-radius: 999px;
}
.install-guide-body::-webkit-scrollbar-thumb,
.install-guide-code::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #7c5cff, #8b5cf6);
    border-radius: 999px;
    border: 2px solid rgba(15, 23, 42, 0.9);
}
.install-guide-body::-webkit-scrollbar-thumb:hover,
.install-guide-code::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #9b7bff, #a78bfa);
}
.install-guide-step {
    display: grid;
    grid-template-columns: 40px 1fr;
    gap: 14px;
    align-items: flex-start;
    padding: 16px 0;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.install-guide-step:first-child {
    border-top: 0;
}
.install-guide-step-number {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    background: rgba(124, 92, 255, 0.18);
    border: 1px solid rgba(124, 92, 255, 0.28);
    color: #f5f1ff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.install-guide-step-content h3 {
    margin: 0 0 8px;
    font-size: 1rem;
}
.install-guide-step-content p,
.install-guide-step-content small {
    margin: 0;
    color: #c4cae4;
    line-height: 1.65;
}
.install-guide-step-content small {
    display: block;
    margin-top: 8px;
    color: #9aa3c7;
}
.install-guide-code {
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: 14px;
    border: 1px solid rgba(124, 92, 255, 0.2);
    background: rgba(12, 16, 28, 0.82);
    color: #ece8ff;
    font-family: Consolas, Monaco, monospace;
    font-size: 0.88rem;
    overflow: auto;
    white-space: nowrap;
}
.install-guide-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 16px 28px 24px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.install-guide-footer-note {
    color: #9aa3c7;
    font-size: 0.9rem;
    line-height: 1.5;
}
@media (max-width: 720px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    .install-guide-modal {
        width: calc(100vw - 20px);
        height: calc(100vh - 20px);
        max-height: calc(100vh - 20px);
    }
    .install-guide-header,
    .install-guide-body,
    .install-guide-footer {
        padding-left: 18px;
        padding-right: 18px;
    }
    .install-guide-header {
        padding-right: 72px;
    }
    .install-guide-step {
        grid-template-columns: 32px 1fr;
        gap: 12px;
    }
    .install-guide-step-number {
        width: 32px;
        height: 32px;
        border-radius: 12px;
        font-size: 0.85rem;
    }
    .install-guide-footer {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>
<body>
<div class="install-shell">
    <div class="install-card">
        <h1 class="install-title">Kurulum</h1>
        <p class="install-subtitle">Uygulamayı ilk kez çalıştırmak için veritabanı ve Google Drive ayarlarını yapılandırın.</p>

        <?php if ($messages['success'] !== ''): ?>
            <div class="message success"><?= h($messages['success']) ?></div>
        <?php endif; ?>

        <?php if ($messages['error'] !== ''): ?>
            <div class="message error"><?= h($messages['error']) ?></div>
        <?php endif; ?>

        <?php if ($locked): ?>
            <div class="message error">Kurulum kilitli. <code>install.lock</code> bulunduğu için bu ekran tekrar çalıştırılamaz.</div>
            <div class="actions">
                <a class="btn btn-primary" href="index.php">Ana Sayfaya Git</a>
                <a class="btn" href="settings.php">Ayarlar Sayfasına Git</a>
            </div>
        <?php elseif ($messages['success'] !== ''): ?>
            <div class="actions">
                <a class="btn btn-primary" href="settings.php">Ayarlar Sayfasına Git</a>
                <a class="btn" href="index.php">Ana Sayfaya Git</a>
            </div>
            <p class="helper" style="margin-top:16px;">Google Drive bağlantısı için kurulumdan sonra Ayarlar &gt; Google Drive Entegrasyonu bölümünü kullanın.</p>
        <?php else: ?>
            <div class="status-list">
                <?php foreach ($systemChecks as $check): ?>
                    <div class="status-item">
                        <div>
                            <div class="status-label"><?= h($check['label']) ?></div>
                            <div class="status-value"><?= h((string) $check['value']) ?></div>
                        </div>
                        <span class="status-pill <?= $check['status'] ? 'ok' : 'fail' ?>"><?= $check['status'] ? 'Hazır' : 'Sorun Var' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post">
                <h2 class="section-title">Veritabanı Bilgileri</h2>
                <div class="form-grid">
                    <div class="field">
                        <label for="db_host">DB Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?= h($form['db_host']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_port">DB Port</label>
                        <input type="number" id="db_port" name="db_port" value="<?= h($form['db_port']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_name">DB Adı</label>
                        <input type="text" id="db_name" name="db_name" value="<?= h($form['db_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="db_user">DB Kullanıcı</label>
                        <input type="text" id="db_user" name="db_user" value="<?= h($form['db_user']) ?>" required>
                    </div>
                    <div class="field-full">
                        <label for="db_pass">DB Şifre</label>
                        <input type="password" id="db_pass" name="db_pass" value="">
                        <small>Güvenlik nedeniyle şifre alanları hata sonrası tekrar doldurulmaz.</small>
                    </div>
                    <div class="field-full checkbox-row">
                        <input type="checkbox" id="db_create" name="db_create" value="1" <?= $form['db_create'] === '1' ? 'checked' : '' ?>>
                        <label for="db_create" style="margin:0;">DB yoksa oluşturmayı dene</label>
                    </div>
                </div>

                <h2 class="section-title">Google Drive Bilgileri</h2>
                <p class="helper">Bu alanlar boş bırakılabilir. Boş bırakırsanız kurulum tamamlanır, ancak Google Drive bağlantısı “Bağlı değil” olarak kalır.</p>
                <div class="form-grid">
                    <div class="field-full">
                        <label for="google_client_id">OAuth Client ID</label>
                        <input type="text" id="google_client_id" name="google_client_id" value="<?= h($form['google_client_id']) ?>">
                    </div>
                    <div class="field-full">
                        <label for="google_client_secret">OAuth Client Secret</label>
                        <input type="password" id="google_client_secret" name="google_client_secret" value="">
                        <small>Güvenlik nedeniyle şifre alanları hata sonrası tekrar doldurulmaz.</small>
                    </div>
                    <div class="field-full">
                        <label for="google_redirect_uri">Redirect URI</label>
                        <input type="text" id="google_redirect_uri" name="google_redirect_uri" value="<?= h($form['google_redirect_uri']) ?>">
                    </div>
                    <div class="field-full">
                        <label for="google_folder_id">Drive Klasör ID</label>
                        <input type="text" id="google_folder_id" name="google_folder_id" value="<?= h($form['google_folder_id']) ?>">
                    </div>
                </div>

                <div class="install-guide-link-row">
                    <button type="button" class="install-guide-link-btn" id="openInstallGuideBtn">
                        <span class="install-guide-link-icon">i</span>
                        <span><?= h(t('settings.open_guide_button')) ?></span>
                    </button>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" type="submit">Kurulumu Başlat</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<div class="install-guide-modal-bg" id="installGuideModal" aria-hidden="true">
    <div class="install-guide-modal" role="dialog" aria-modal="true" aria-labelledby="installGuideTitle">
        <button type="button" class="install-guide-close" id="closeInstallGuideBtn" aria-label="<?= h(t('settings.guide_close_aria')) ?>">×</button>
        <div class="install-guide-header">
            <h2 class="install-guide-title" id="installGuideTitle"><?= h(t('settings.guide_title')) ?></h2>
            <p class="install-guide-intro"><?= h(t('settings.guide_intro')) ?></p>
        </div>
        <div class="install-guide-body">
            <div class="install-guide-step">
                <span class="install-guide-step-number">1</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_1_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_1_body')) ?></p>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">2</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_2_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_2_body')) ?></p>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">3</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_3_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_3_body')) ?></p>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">4</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_4_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_4_body')) ?></p>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">5</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_5_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_5_body')) ?></p>
                    <div class="install-guide-code"><?= h(t('settings.guide_step_5_example')) ?></div>
                    <small><?= h(t('settings.guide_step_5_note')) ?></small>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">6</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_6_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_6_body')) ?></p>
                    <small><?= h(t('settings.guide_step_6_note')) ?></small>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">7</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_7_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_7_body')) ?></p>
                    <div class="install-guide-code"><?= h(t('settings.guide_step_7_example')) ?></div>
                    <small><?= h(t('settings.guide_step_7_value')) ?></small>
                </div>
            </div>
            <div class="install-guide-step">
                <span class="install-guide-step-number">8</span>
                <div class="install-guide-step-content">
                    <h3><?= h(t('settings.guide_step_8_title')) ?></h3>
                    <p><?= h(t('settings.guide_step_8_body')) ?></p>
                </div>
            </div>
        </div>
        <div class="install-guide-footer">
            <small class="install-guide-footer-note"><?= h(t('settings.guide_footer_note')) ?></small>
            <button type="button" class="btn" id="installGuideCloseFooterBtn"><?= h(t('actions.close')) ?></button>
        </div>
    </div>
</div>
<script>
(function () {
    var openBtn = document.getElementById('openInstallGuideBtn');
    var modal = document.getElementById('installGuideModal');
    var closeBtn = document.getElementById('closeInstallGuideBtn');
    var closeFooterBtn = document.getElementById('installGuideCloseFooterBtn');

    if (!openBtn || !modal || !closeBtn || !closeFooterBtn) {
        return;
    }

    var openGuide = function () {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    };

    var closeGuide = function () {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    };

    openBtn.addEventListener('click', openGuide);
    closeBtn.addEventListener('click', closeGuide);
    closeFooterBtn.addEventListener('click', closeGuide);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeGuide();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeGuide();
        }
    });
})();
</script>
</body>
</html>
