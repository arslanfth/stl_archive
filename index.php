<?php
require_once __DIR__ . '/includes/bootstrap.php';
ensureDatabaseConfig('html');
require_once __DIR__ . '/includes/lang.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(t('app.title', 'STL ArÅŸivim')) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
   <div class="container">
        <?php include 'includes/menu.php'; ?>

        <div class="main">
            <?php include 'includes/categories.php'; ?>
            <div id="galleryResults">
                <?php include 'includes/gallery_list.php'; ?>
            </div>
        </div>
    </div>

    <div class="modal-bg" id="modalAdd" style="display:none;">
        <div class="modal modal-detail">
            <button type="button" class="modal-corner-close" id="btnCloseAddIcon" aria-label="<?= htmlspecialchars(t('actions.close', 'Kapat')) ?>">
                <i data-lucide="x"></i>
            </button>
            <div class="modal-title"><?= htmlspecialchars(t('modal.new_record', 'Yeni KayÄ±t Ekle')) ?></div>
            <form id="formAdd" enctype="multipart/form-data">
                <label><?= htmlspecialchars(t('form.category', 'Kategori')) ?>:</label>
                <select name="category_id" required id="addCategory"></select>
                <label><?= htmlspecialchars(t('form.title', 'BaÅŸlÄ±k')) ?>:</label>
                <input type="text" name="title" maxlength="100" required>
                <label><?= htmlspecialchars(t('form.size', 'Boyut')) ?>:</label>
                <input type="text" name="size" maxlength="50" required>
                <label><?= htmlspecialchars(t('form.download_link', 'Download Linki')) ?>:</label>
                <input type="url" name="download" maxlength="255" required>
                <label><?= htmlspecialchars(t('form.image', 'GÃ¶rsel')) ?>:</label>
                <div class="multi-upload-field" id="addImageField">
                  <input type="hidden" name="new_image_order" id="addNewImageOrder" value="[]">
                  <input type="file" id="addImageInput" name="images[]" class="file-input" accept="image/*" multiple>
                  <div id="addImageEmptyState" class="upload-dropzone" tabindex="0" role="button" aria-controls="addImageInput">
                    <strong><?= htmlspecialchars(t('form.images_dropzone_title', 'DosyalarÄ± buraya sÃ¼rÃ¼kleyin veya tÄ±klayÄ±n')) ?></strong>
                    <span><?= htmlspecialchars(t('form.images_dropzone_hint', 'JPG, PNG, WEBP â€” Ã§oklu seÃ§ilebilir')) ?></span>
                  </div>
                  <div id="addImageSelectedState" class="upload-selected-state" hidden>
                    <div class="upload-selected-header">
                      <strong id="addImageSelectedTitle"><?= htmlspecialchars(t('form.selected_images', 'SeÃ§ili GÃ¶rseller')) ?></strong>
                    </div>
                    <div id="addImagePreviewList" class="upload-preview-list" aria-live="polite"></div>
                    <button type="button" id="addImageTrigger" class="upload-add-more">+ <?= htmlspecialchars(t('form.add_new_image', 'Yeni GÃ¶rsel Ekle')) ?></button>
                    <div id="addImageSecondaryDropzone" class="upload-dropzone upload-dropzone--compact" tabindex="0" role="button" aria-controls="addImageInput">
                      <strong><?= htmlspecialchars(t('form.images_dropzone_title', 'DosyalarÄ± buraya sÃ¼rÃ¼kleyin veya tÄ±klayÄ±n')) ?></strong>
                    </div>
                  </div>
                  <span id="addFileName" class="file-name-label"><?= htmlspecialchars(t('form.no_file_selected', 'Dosya seÃ§ilmedi')) ?></span>
                </div>
                <div class="modal-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-main"><?= htmlspecialchars(t('actions.save')) ?></button>
                    <button type="button" class="btn-cancel" id="btnCancelAdd"><?= htmlspecialchars(t('actions.cancel', 'Ä°ptal')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-bg" id="modalDetail" style="display:none;">
        <div class="modal modal-detail" id="detailContent"></div>
    </div>

    <div class="modal-bg" id="modalCategory" style="display:none;">
        <div class="modal modal-detail" id="categoryContent"></div>
    </div>

    <div id="imgLightbox" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);justify-content:center;align-items:center;">
      <img id="lightboxImg" src="" alt="<?= htmlspecialchars(t('form.image', 'GÃ¶rsel')) ?>" style="max-width:95vw;max-height:90vh;border-radius:16px;box-shadow:0 8px 40px #0008;">
    </div>

    <div id="confirmModal" style="display:none;position:fixed;left:0;top:0;width:100vw;height:100vh;z-index:100001;background:rgba(0,0,0,0.5);justify-content:center;align-items:center;">
      <div style="background:#222;padding:32px 28px 18px 28px;border-radius:16px;min-width:320px;box-shadow:0 2px 32px #0008;text-align:center;">
        <div id="confirmModalText" style="font-size:1.1rem;margin-bottom:24px;"><?= htmlspecialchars(t('modal.confirm_delete', 'Bu kaydÄ± silmek istediÄŸinize emin misiniz?')) ?></div>
        <button id="confirmModalYes" class="btn-danger" style="margin-right:18px;"><?= htmlspecialchars(t('actions.delete', 'Sil')) ?></button>
        <button id="confirmModalNo" class="btn-cancel"><?= htmlspecialchars(t('actions.dismiss', 'VazgeÃ§')) ?></button>
      </div>
    </div>

    <div class="modal-bg" id="modalDriveAuth" style="display:none;">
      <div class="modal modal-detail">
        <div class="modal-title"><?= htmlspecialchars(t('modal.drive_auth_required', 'Google Drive BaÄŸlantÄ±sÄ± Gerekli')) ?></div>
        <div class="detail-meta" style="line-height:1.6;">
          <?= htmlspecialchars(t('modal.drive_auth_help', 'Drive eriÅŸimi yenilenmeli. AÅŸaÄŸÄ±daki baÄŸlantÄ±yÄ± yeni sekmede aÃ§Ä±p Google onayÄ±nÄ± tamamlayabilirsiniz.')) ?>
        </div>
        <label for="driveAuthUrl" style="margin-top:8px;"><?= htmlspecialchars(t('modal.drive_auth_link_label', 'Yetkilendirme BaÄŸlantÄ±sÄ±')) ?>:</label>
        <textarea id="driveAuthUrl" readonly></textarea>
        <div class="modal-actions">
          <button type="button" class="btn-main" id="btnOpenDriveAuth"><?= htmlspecialchars(t('actions.open_link', 'BaÄŸlantÄ±yÄ± AÃ§')) ?></button>
          <button type="button" class="btn-cancel" id="btnCopyDriveAuth"><?= htmlspecialchars(t('actions.copy', 'Kopyala')) ?></button>
          <button type="button" class="btn-cancel" id="btnCloseDriveAuth"><?= htmlspecialchars(t('actions.close')) ?></button>
        </div>
      </div>
    </div>

    <div class="modal-bg" id="modalDriveSettings" style="display:none;">
      <div class="modal modal-detail drive-settings-modal" role="dialog" aria-modal="true" aria-labelledby="driveSettingsTitle">
        <button type="button" class="drive-settings-close" id="btnCloseDriveSettingsIcon" aria-label="<?= htmlspecialchars(t('settings.close_modal_aria')) ?>">
          <i data-lucide="x"></i>
        </button>
        <div class="drive-settings-header">
          <div class="drive-settings-brand" aria-hidden="true">
            <img src="assets/icons/google-drive.png" alt="" role="presentation">
          </div>
          <div class="drive-settings-headcopy">
            <div class="modal-title" id="driveSettingsTitle"><?= htmlspecialchars(t('settings.heading')) ?></div>
            <p class="drive-settings-intro"><?= htmlspecialchars(t('settings.modal_intro')) ?></p>
          </div>
        </div>
        <form id="formDriveSettings">
          <div class="drive-settings-status-card" id="driveSettingsStatusCard">
            <div class="drive-settings-status-icon" aria-hidden="true"></div>
            <div class="drive-settings-status-copy">
              <span class="drive-settings-status-label"><?= htmlspecialchars(t('settings.connection_status')) ?></span>
              <strong id="driveSettingsStatus" class="status-pill disconnected"><?= htmlspecialchars(t('settings.not_connected')) ?></strong>
              <small id="driveSettingsStatusMeta"><?= htmlspecialchars(t('settings.not_connected_desc')) ?></small>
            </div>
          </div>
          <div id="driveSettingsAlert" class="drive-settings-alert" hidden></div>
          <div class="drive-settings-grid">
            <div class="drive-settings-field drive-settings-field--full">
              <label for="driveSettingsClientId"><?= htmlspecialchars(t('settings.client_id_label')) ?></label>
              <input type="text" id="driveSettingsClientId" name="client_id" autocomplete="off">
            </div>
            <div class="drive-settings-field drive-settings-field--full">
              <label for="driveSettingsClientSecret"><?= htmlspecialchars(t('settings.client_secret_label')) ?></label>
              <input type="password" id="driveSettingsClientSecret" name="client_secret" autocomplete="new-password" placeholder="<?= htmlspecialchars(t('settings.secret_placeholder')) ?>">
              <small id="driveSettingsSecretHint"><?= htmlspecialchars(t('settings.secret_empty_hint')) ?></small>
            </div>
            <div class="drive-settings-field drive-settings-field--full">
              <label for="driveSettingsRedirectUri"><?= htmlspecialchars(t('settings.redirect_uri_label')) ?></label>
              <div class="drive-settings-inline">
                <input type="text" id="driveSettingsRedirectUri" name="redirect_uri" autocomplete="off">
                <button type="button" class="btn-cancel drive-settings-copy-btn" id="btnCopyDriveRedirectUri"><?= htmlspecialchars(t('actions.copy')) ?></button>
              </div>
              <small><?= htmlspecialchars(t('settings.redirect_uri_help')) ?></small>
            </div>
            <div class="drive-settings-field drive-settings-field--full">
              <label for="driveSettingsFolderId"><?= htmlspecialchars(t('settings.folder_id_new_label')) ?></label>
              <input type="text" id="driveSettingsFolderId" name="folder_id" autocomplete="off">
            </div>
          </div>
          <div class="drive-settings-footer">
            <div class="drive-settings-footer-copy">
              <small class="drive-settings-footer-note"><?= htmlspecialchars(t('settings.secret_footer_note')) ?></small>
              <div class="drive-guide-link-row">
                <button type="button" class="drive-guide-link-btn" id="btnOpenDriveGuide">
                  <i data-lucide="book-open"></i>
                  <span><?= htmlspecialchars(t('settings.open_guide_button')) ?></span>
                </button>
              </div>
            </div>
            <div class="modal-actions drive-settings-actions">
              <button type="submit" class="btn-main" id="btnSaveDriveSettings"><?= htmlspecialchars(t('actions.save')) ?></button>
              <button type="button" class="btn-cancel" id="btnStartDriveOauth"><?= htmlspecialchars(t('settings.connect_button')) ?></button>
              <button type="button" class="btn-cancel drive-settings-close-btn" id="btnCloseDriveSettings" aria-label="<?= htmlspecialchars(t('settings.close_modal_aria')) ?>"><?= htmlspecialchars(t('actions.close')) ?></button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="modal-bg" id="modalDriveGuide" style="display:none;">
      <div class="modal modal-detail drive-guide-modal" role="dialog" aria-modal="true" aria-labelledby="driveGuideTitle">
        <button type="button" class="drive-settings-close" id="btnCloseDriveGuideIcon" aria-label="<?= htmlspecialchars(t('settings.guide_close_aria')) ?>">
          <i data-lucide="x"></i>
        </button>
        <div class="drive-settings-header drive-guide-header">
          <div class="drive-settings-brand" aria-hidden="true">
            <img src="assets/icons/google-drive.png" alt="" role="presentation">
          </div>
          <div class="drive-settings-headcopy">
            <div class="modal-title" id="driveGuideTitle"><?= htmlspecialchars(t('settings.guide_title')) ?></div>
            <p class="drive-settings-intro"><?= htmlspecialchars(t('settings.guide_intro')) ?></p>
          </div>
        </div>
        <div class="drive-guide-body modal-scroll">
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">1</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_1_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_1_body')) ?></p>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">2</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_2_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_2_body')) ?></p>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">3</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_3_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_3_body')) ?></p>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">4</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_4_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_4_body')) ?></p>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">5</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_5_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_5_body')) ?></p>
              <div class="drive-guide-code modal-scroll"><?= htmlspecialchars(t('settings.guide_step_5_example')) ?></div>
              <small><?= htmlspecialchars(t('settings.guide_step_5_note')) ?></small>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">6</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_6_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_6_body')) ?></p>
              <small><?= htmlspecialchars(t('settings.guide_step_6_note')) ?></small>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">7</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_7_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_7_body')) ?></p>
              <div class="drive-guide-code modal-scroll"><?= htmlspecialchars(t('settings.guide_step_7_example')) ?></div>
              <small><?= htmlspecialchars(t('settings.guide_step_7_value')) ?></small>
            </div>
          </div>
          <div class="drive-guide-step">
            <span class="drive-guide-step__number">8</span>
            <div class="drive-guide-step__content">
              <h3><?= htmlspecialchars(t('settings.guide_step_8_title')) ?></h3>
              <p><?= htmlspecialchars(t('settings.guide_step_8_body')) ?></p>
            </div>
          </div>
        </div>
        <div class="drive-settings-footer drive-guide-footer">
          <small class="drive-settings-footer-note"><?= htmlspecialchars(t('settings.guide_footer_note')) ?></small>
          <div class="modal-actions drive-settings-actions">
            <button type="button" class="btn-main" id="btnReturnDriveSettings"><?= htmlspecialchars(t('settings.back_to_settings')) ?></button>
            <button type="button" class="btn-cancel drive-settings-close-btn" id="btnCloseDriveGuide"><?= htmlspecialchars(t('actions.close')) ?></button>
          </div>
        </div>
      </div>
    </div>

    <div id="toastMessage" style="display:none;position:fixed;top:34px;right:30px;z-index:100002;min-width:210px;padding:17px 32px 17px 18px;font-size:17px;border-radius:11px;box-shadow:0 4px 20px #1118;transition:opacity .6s,transform .4s;opacity:0;">
      <span id="toastIcon" style="margin-right:10px; font-size:19px; vertical-align:middle;"></span>
      <span id="toastText"></span>
    </div>

    <script>
      window.APP_LANG = <?= json_encode([
          'common.uncategorized' => t('common.uncategorized', 'Kategorisiz'),
          'common.total' => t('common.total', 'Toplam'),
          'common.unlimited' => t('common.unlimited', 'SÄ±nÄ±rsÄ±z'),
          'actions.google_drive_update' => t('actions.google_drive_update', 'Google Driveâ€™dan GÃ¼ncelle'),
          'actions.add' => t('actions.add', 'Ekle'),
          'actions.save' => t('actions.save'),
          'actions.update' => t('actions.update', 'GÃ¼ncelle'),
          'actions.edit' => t('actions.edit', 'DÃ¼zenle'),
          'actions.delete' => t('actions.delete', 'Sil'),
          'actions.cancel' => t('actions.cancel', 'Ä°ptal'),
          'actions.dismiss' => t('actions.dismiss', 'VazgeÃ§'),
          'actions.close' => t('actions.close'),
          'actions.copy' => t('actions.copy', 'Kopyala'),
          'actions.download' => t('actions.download', 'Ä°ndir'),
          'actions.download_desc' => t('actions.download_desc', 'DosyayÄ± indir'),
          'actions.edit_desc' => t('actions.edit_desc', 'Bilgileri dÃ¼zenle'),
          'actions.delete_desc' => t('actions.delete_desc', 'DosyayÄ± sil'),
          'actions.close_desc' => t('actions.close_desc', 'Pencereyi kapat'),
          'actions.open_link' => t('actions.open_link', 'BaÄŸlantÄ±yÄ± AÃ§'),
          'actions.preview_enlarge' => t('actions.preview_enlarge', 'GÃ¶rseli bÃ¼yÃ¼t'),
          'modal.new_record' => t('modal.new_record', 'Yeni KayÄ±t Ekle'),
          'modal.edit_record' => t('modal.edit_record', 'KayÄ±t DÃ¼zenle'),
          'modal.confirm_delete' => t('modal.confirm_delete', 'Bu kaydÄ± silmek istediÄŸinize emin misiniz?'),
          'modal.drive_auth_required' => t('modal.drive_auth_required', 'Google Drive BaÄŸlantÄ±sÄ± Gerekli'),
          'modal.drive_auth_help' => t('modal.drive_auth_help', 'Drive eriÅŸimi yenilenmeli. AÅŸaÄŸÄ±daki baÄŸlantÄ±yÄ± yeni sekmede aÃ§Ä±p Google onayÄ±nÄ± tamamlayabilirsiniz.'),
          'modal.drive_auth_link_label' => t('modal.drive_auth_link_label', 'Yetkilendirme BaÄŸlantÄ±sÄ±'),
          'form.category' => t('form.category', 'Kategori'),
          'form.title' => t('form.title', 'BaÅŸlÄ±k'),
          'form.size' => t('form.size', 'Boyut'),
          'form.download_link' => t('form.download_link', 'Download Linki'),
          'form.file' => t('form.file', 'Dosya'),
          'form.image' => t('form.image', 'GÃ¶rsel'),
          'form.image_select' => t('form.image_select', 'GÃ¶rsel SeÃ§'),
          'form.no_file_selected' => t('form.no_file_selected', 'Dosya seÃ§ilmedi'),
          'form.select_prompt' => t('form.select_prompt', 'SeÃ§iniz'),
          'form.images_dropzone_title' => t('form.images_dropzone_title', 'DosyalarÄ± buraya sÃ¼rÃ¼kleyin veya tÄ±klayÄ±n'),
          'form.images_dropzone_hint' => t('form.images_dropzone_hint', 'JPG, PNG, WEBP â€” Ã§oklu seÃ§ilebilir'),
          'form.selected_images' => t('form.selected_images', 'SeÃ§ili GÃ¶rseller'),
          'form.add_new_image' => t('form.add_new_image', 'Yeni GÃ¶rsel Ekle'),
          'form.image_select_count' => t('form.image_select_count', 'gÃ¶rsel seÃ§ildi'),
          'form.cover_label' => t('form.cover_label', 'Kapak'),
          'form.make_cover' => t('form.make_cover', 'Kapak yap'),
          'form.cover_image' => t('form.cover_image', 'Kapak gÃ¶rseli'),
          'category.title' => t('category.title', 'Kategoriler'),
          'category.name' => t('category.name', 'Kategori adÄ±'),
          'category.add_new' => t('category.add_new', 'Yeni kategori ekle'),
          'category.confirm_delete' => t('category.confirm_delete', 'Bu kategoriyi silmek istediğinize emin misiniz?'),
          'category.reorder_handle' => t('category.reorder_handle'),
          'category.save_order' => t('category.save_order'),
          'gallery.drive_storage' => t('gallery.drive_storage', 'Depolama alanÄ±'),
          'gallery.drive_storage_label' => t('gallery.drive_storage_label', '%s / %s kullanÄ±lÄ±yor'),
          'detail.file_size' => t('detail.file_size', 'Dosya Boyutu'),
          'detail.file_type' => t('detail.file_type', 'Dosya TÃ¼rÃ¼'),
          'detail.category' => t('detail.category', 'Kategori'),
          'detail.created_at' => t('detail.created_at', 'Eklenme Tarihi'),
          'detail.title' => t('detail.title'),
          'detail.download' => t('detail.download'),
          'detail.operations' => t('detail.operations', 'Ä°ÅŸlemler'),
          'messages.auth_link_copied' => t('messages.auth_link_copied', 'Yetkilendirme baÄŸlantÄ±sÄ± kopyalandÄ±.'),
          'messages.auth_link_selected' => t('messages.auth_link_selected', 'BaÄŸlantÄ± seÃ§ildi. Ctrl+C ile kopyalayabilirsiniz.'),
          'messages.add_success' => t('messages.add_success', 'BaÅŸarÄ±yla kaydedildi!'),
          'messages.add_error_prefix' => t('messages.add_error_prefix', 'KayÄ±t baÅŸarÄ±sÄ±z:'),
          'messages.update_success' => t('messages.update_success', 'KayÄ±t gÃ¼ncellendi!'),
          'messages.update_error_prefix' => t('messages.update_error_prefix', 'GÃ¼ncelleme baÅŸarÄ±sÄ±z:'),
          'messages.delete_success' => t('messages.delete_success', 'KayÄ±t silindi!'),
          'messages.delete_error_prefix' => t('messages.delete_error_prefix', 'Silinemedi:'),
          'messages.category_add_success' => t('messages.category_add_success', 'Kategori eklendi!'),
          'messages.category_add_error_prefix' => t('messages.category_add_error_prefix', 'Kategori eklenemedi:'),
          'messages.category_name_short' => t('messages.category_name_short', 'Kategori adÄ± Ã§ok kÄ±sa!'),
          'messages.category_update_success' => t('messages.category_update_success', 'Kategori gÃ¼ncellendi!'),
          'messages.category_update_error_prefix' => t('messages.category_update_error_prefix', 'Kategori gÃ¼ncellenemedi:'),
          'messages.category_delete_success' => t('messages.category_delete_success', 'Kategori silindi!'),
          'messages.category_delete_error_prefix' => t('messages.category_delete_error_prefix', 'Kategori silinemedi:'),
          'messages.category_reorder_saved' => t('messages.category_reorder_saved'),
          'messages.category_reorder_save_error' => t('messages.category_reorder_save_error'),
          'messages.drive_syncing' => t('messages.drive_syncing', 'GÃ¼ncelleniyor...'),
          'messages.drive_sync_error' => t('messages.drive_sync_error', 'Drive gÃ¼ncellemesi sÄ±rasÄ±nda bir hata oluÅŸtu.'),
          'messages.drive_auth_prompt_opened' => t('messages.drive_auth_prompt_opened', 'Drive baÄŸlantÄ±sÄ±nÄ± yenilemek iÃ§in pencere aÃ§Ä±ldÄ±.'),
          'messages.loading' => t('messages.loading', 'YÃ¼kleniyor...'),
          'messages.drive_storage_loading' => t('messages.drive_storage_loading', 'Drive depolama bilgisi alÄ±nÄ±yor'),
          'messages.drive_storage_unavailable' => t('messages.drive_storage_unavailable', 'Depolama bilgisi alÄ±namadÄ±'),
          'messages.drive_storage_try_later' => t('messages.drive_storage_try_later', 'Daha sonra tekrar deneyin'),
          'messages.drive_auth_needed' => t('messages.drive_auth_needed', 'Yetkilendirme gerekiyor'),
          'messages.generic_error' => t('messages.generic_error', 'Bir hata oluÅŸtu!'),
          'settings.open_modal' => t('settings.open_modal'),
          'settings.heading' => t('settings.heading'),
          'settings.connection_status' => t('settings.connection_status'),
          'settings.client_id_label' => t('settings.client_id_label'),
          'settings.client_secret_label' => t('settings.client_secret_label'),
          'settings.redirect_uri_label' => t('settings.redirect_uri_label'),
          'settings.folder_id_new_label' => t('settings.folder_id_new_label'),
          'settings.redirect_uri_help' => t('settings.redirect_uri_help'),
          'settings.connected' => t('settings.connected'),
          'settings.not_connected' => t('settings.not_connected'),
          'settings.connect_button' => t('settings.connect_button'),
          'settings.modal_intro' => t('settings.modal_intro'),
          'settings.secret_placeholder' => t('settings.secret_placeholder'),
          'settings.secret_present_hint' => t('settings.secret_present_hint'),
          'settings.secret_empty_hint' => t('settings.secret_empty_hint'),
          'settings.load_error' => t('settings.load_error'),
          'settings.reconnect_button' => t('settings.reconnect_button'),
          'settings.integration_saved' => t('settings.integration_saved'),
          'settings.integration_save_error' => t('settings.integration_save_error'),
          'settings.connected_desc' => t('settings.connected_desc'),
          'settings.not_connected_desc' => t('settings.not_connected_desc'),
          'settings.connect_success' => t('settings.connect_success'),
          'settings.connect_missing' => t('settings.connect_missing'),
          'settings.connect_error' => t('settings.connect_error'),
          'settings.callback_error' => t('settings.callback_error'),
          'settings.oauth_access_denied' => t('settings.oauth_access_denied'),
          'settings.secret_footer_note' => t('settings.secret_footer_note'),
          'settings.close_modal_aria' => t('settings.close_modal_aria'),
          'settings.open_guide_button' => t('settings.open_guide_button'),
          'messages.redirect_uri_copied' => t('messages.redirect_uri_copied'),
          'messages.redirect_uri_copy_failed' => t('messages.redirect_uri_copy_failed'),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="assets/vendor/lucide/lucide.min.js"></script>
    <script src="assets/app.js?v=1"></script>
</body>
</html>










