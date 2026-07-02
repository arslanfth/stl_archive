<?php
require_once __DIR__ . '/lang.php';


function ensureDatabaseConfig(string $mode = 'html'): void
{
    $dbConfigPath = __DIR__ . '/db.php';

    if (is_file($dbConfigPath)) {
        require_once $dbConfigPath;
        if (isset($pdo)) {
            $GLOBALS['pdo'] = $pdo;
        }
        return;
    }

    if ($mode === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            [
                'success' => false,
                'message' => t('messages.install_not_completed'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    header('Location: install.php');
    exit;
}


