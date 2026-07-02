<?php
// includes/menu.php
$totalCount = $pdo->query("SELECT COUNT(*) FROM images")->fetchColumn();
?>
<div class="page-header">
    <div class="brand-block">
        <div class="brand-mark" aria-hidden="true">
            <img src="assets/logo.png" alt="" role="presentation">
        </div>
        <div class="brand-copy">
            <h1><?= htmlspecialchars(t('app.title', 'STL Arşivim')) ?></h1>
            <p><?= htmlspecialchars(t('app.subtitle', '3D Model Kütüphanem')) ?></p>
        </div>
    </div>
    <div class="header-actions">
        <button id="btnDriveSync" class="btn-main">
            <span class="gdrive-icon" aria-hidden="true">
                    <img src="assets/icons/google-drive.png" alt="" role="presentation">
            </span>
            <span class="drive-label"><?= htmlspecialchars(t('actions.google_drive_update', 'Google Drive’dan Güncelle')) ?></span>
        </button>
        <button
            id="btnDriveSettings"
            class="btn-icon-secondary"
            type="button"
            aria-label="<?= htmlspecialchars(t('settings.open_modal', 'Google Drive ayarlarını aç')) ?>"
            title="<?= htmlspecialchars(t('settings.open_modal', 'Google Drive ayarlarını aç')) ?>"
        >
            <i data-lucide="settings-2"></i>
        </button>
    </div>
</div>

<div class="topbar">
    <div class="toolbar-search">
        <span class="search-icon" aria-hidden="true"></span>
        <input id="searchInput" type="text" placeholder="<?= htmlspecialchars(t('search.placeholder', 'Başlık ile ara...')) ?>" autocomplete="off">
        <div class="toolbar-actions">
            <button id="btnCategory"><?= htmlspecialchars(t('actions.category_management', 'Kategori Yönetimi')) ?></button>
            <button id="btnAdd"><?= htmlspecialchars(t('actions.new_record', 'Yeni Kayıt Ekle')) ?></button>
        </div>
    </div>
</div>
