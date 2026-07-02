// TOAST
function appLang(key, fallback) {
    if (window.APP_LANG && Object.prototype.hasOwnProperty.call(window.APP_LANG, key)) {
        return window.APP_LANG[key];
    }

    return fallback || key;
}

function showToast(msg, type = "success") {
    var toast = document.getElementById("toastMessage");
    var iconName = "check";
    var color = "#232937";
    var textColor = "#fff";

    // Simge ve renk seÃ§imi
    switch(type) {
        case "error":
        case "delete":
            iconName = "trash-2";
            color = "#ed2e3c";
            break;
        case "add":
            iconName = "check";
            color = "#1ca854";
            break;
        case "update":
            iconName = "pencil";
            color = "#ffbc0a";
            textColor = "#222";
            break;
        case "success":
        default:
            iconName = "check";
            color = "#232937";
            break;
    }

    // EÄŸer ayrÄ± #toastIcon ve #toastText varsa:
    if (document.getElementById("toastIcon") && document.getElementById("toastText")) {
        document.getElementById("toastIcon").innerHTML = `<i data-lucide="${iconName}"></i>`;
        document.getElementById("toastText").textContent = msg;
    } else {
        toast.innerHTML = `<span style="margin-right:10px;font-size:19px;vertical-align:middle;display:inline-flex;align-items:center;"><i data-lucide="${iconName}"></i></span>${msg}`;
    }

    toast.style.background = color;
    toast.style.color = textColor;
    toast.style.display = "block";
    toast.style.opacity = "1";
    toast.style.transform = "translateY(-15px) scale(1.05)";
    toast.style.transition = "opacity 0.6s, transform 0.4s";
    renderLucideIcons();
    setTimeout(function() {
        toast.style.opacity = "0";
        toast.style.transform = "translateY(0px) scale(0.98)";
        setTimeout(function() {
            toast.style.display = "none";
            toast.style.transition = "";
            toast.style.transform = "";
        }, 700);
    }, 2200);
}

function extractUrl(text) {
    var match = String(text || "").match(/https?:\/\/\S+/);
    return match ? match[0] : "";
}

function showDriveAuthModal(message) {
    var modal = document.getElementById("modalDriveAuth");
    var urlField = document.getElementById("driveAuthUrl");
    var openBtn = document.getElementById("btnOpenDriveAuth");
    var copyBtn = document.getElementById("btnCopyDriveAuth");
    var closeBtn = document.getElementById("btnCloseDriveAuth");
    var url = extractUrl(message);

    if (!modal || !urlField || !url) {
        return false;
    }

    urlField.value = url;
    modal.style.display = "flex";
    urlField.focus();
    urlField.select();

    openBtn.onclick = function() {
        window.open(url, "_blank", "noopener,noreferrer");
    };

    copyBtn.onclick = async function() {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                urlField.focus();
                urlField.select();
                document.execCommand("copy");
            }
            showToast(appLang('messages.auth_link_copied', 'Yetkilendirme baÄŸlantÄ±sÄ± kopyalandÄ±.'), "success");
        } catch (err) {
            urlField.focus();
            urlField.select();
            showToast(appLang('messages.auth_link_selected', 'BaÄŸlantÄ± seÃ§ildi. Ctrl+C ile kopyalayabilirsiniz.'), "update");
        }
    };

    closeBtn.onclick = function() {
        modal.style.display = "none";
    };

    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.style.display = "none";
        }
    };

    return true;
}

function renderDriveStorageCard(state) {
    var card = document.getElementById('driveStorageCard');
    if (!card) {
        return;
    }

    var valueEl = card.querySelector('.drive-storage-card__value');
    var metaEl = card.querySelector('.drive-storage-card__meta');
    var barEl = card.querySelector('.drive-storage-card__bar');

    if (!valueEl || !metaEl || !barEl) {
        return;
    }

    valueEl.textContent = state.value || '';
    metaEl.textContent = state.meta || '';
    barEl.style.width = (typeof state.percent === 'number' ? state.percent : 0) + '%';
}

function loadDriveStorageCard() {
    var card = document.getElementById('driveStorageCard');
    if (!card) {
        return;
    }

    renderDriveStorageCard({
        value: appLang('messages.loading', 'YÃ¼kleniyor...'),
        meta: appLang('messages.drive_storage_loading', 'Drive depolama bilgisi alÄ±nÄ±yor'),
        percent: 0
    });

    fetch('drive_storage.php')
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data && data.success) {
                renderDriveStorageCard({
                    value: data.label || ((data.usageText || '0 B') + ' / ' + (data.limitText || '0 B')),
                    meta: '',
                    percent: typeof data.percent === 'number' ? data.percent : 0
                });
                return;
            }

            renderDriveStorageCard({
                value: data && data.auth_required
                    ? appLang('messages.drive_auth_needed', 'Yetkilendirme gerekiyor')
                    : appLang('messages.drive_storage_unavailable', 'Depolama bilgisi alÄ±namadÄ±'),
                meta: data && data.message ? data.message : appLang('messages.drive_storage_try_later', 'Daha sonra tekrar deneyin'),
                percent: 0
            });
        })
        .catch(function() {
            renderDriveStorageCard({
                value: appLang('messages.drive_storage_unavailable', 'Depolama bilgisi alÄ±namadÄ±'),
                meta: appLang('messages.drive_storage_try_later', 'Daha sonra tekrar deneyin'),
                percent: 0
            });
        });
}




function setDriveSettingsAlert(message, type) {
    var alertEl = document.getElementById('driveSettingsAlert');
    if (!alertEl) {
        return;
    }

    if (!message) {
        alertEl.hidden = true;
        alertEl.className = 'drive-settings-alert';
        alertEl.textContent = '';
        return;
    }

    alertEl.hidden = false;
    alertEl.className = 'drive-settings-alert is-' + (type || 'info');
    alertEl.textContent = message;
}

function setDriveSettingsStatus(isConnected) {
    var statusEl = document.getElementById('driveSettingsStatus');
    var statusCard = document.getElementById('driveSettingsStatusCard');
    var statusMeta = document.getElementById('driveSettingsStatusMeta');
    var connectBtn = document.getElementById('btnStartDriveOauth');
    if (!statusEl) {
        return;
    }

    statusEl.classList.remove('connected', 'disconnected');
    statusEl.classList.add(isConnected ? 'connected' : 'disconnected');
    statusEl.textContent = isConnected
        ? appLang('settings.connected')
        : appLang('settings.not_connected');

    if (statusCard) {
        statusCard.classList.remove('is-connected', 'is-disconnected');
        statusCard.classList.add(isConnected ? 'is-connected' : 'is-disconnected');
    }

    if (statusMeta) {
        statusMeta.textContent = isConnected
            ? appLang('settings.connected_desc')
            : appLang('settings.not_connected_desc');
    }

    if (connectBtn) {
        connectBtn.textContent = isConnected
            ? appLang('settings.reconnect_button')
            : appLang('settings.connect_button');
    }
}

function populateDriveSettingsModal(data) {
    var clientId = document.getElementById('driveSettingsClientId');
    var clientSecret = document.getElementById('driveSettingsClientSecret');
    var redirectUri = document.getElementById('driveSettingsRedirectUri');
    var folderId = document.getElementById('driveSettingsFolderId');
    var secretHint = document.getElementById('driveSettingsSecretHint');

    if (clientId) {
        clientId.value = data && data.client_id ? data.client_id : '';
    }
    if (clientSecret) {
        clientSecret.value = '';
    }
    if (redirectUri) {
        redirectUri.value = data && data.redirect_uri ? data.redirect_uri : '';
    }
    if (folderId) {
        folderId.value = data && data.folder_id ? data.folder_id : '';
    }
    if (secretHint) {
        secretHint.textContent = data && data.secret_configured
            ? appLang('settings.secret_present_hint')
            : appLang('settings.secret_empty_hint');
    }

    setDriveSettingsStatus(Boolean(data && Number(data.is_connected) === 1));
}

var driveSettingsDraft = null;
function readDriveSettingsDraft() {
    return {
        client_id: document.getElementById('driveSettingsClientId') ? document.getElementById('driveSettingsClientId').value : '',
        client_secret: document.getElementById('driveSettingsClientSecret') ? document.getElementById('driveSettingsClientSecret').value : '',
        redirect_uri: document.getElementById('driveSettingsRedirectUri') ? document.getElementById('driveSettingsRedirectUri').value : '',
        folder_id: document.getElementById('driveSettingsFolderId') ? document.getElementById('driveSettingsFolderId').value : '',
        is_connected: document.getElementById('driveSettingsStatus') && document.getElementById('driveSettingsStatus').classList.contains('connected') ? 1 : 0,
        secret_configured: true
    };
}
function applyDriveSettingsDraft(data) {
    populateDriveSettingsModal(data || {});
    var clientSecret = document.getElementById('driveSettingsClientSecret');
    if (clientSecret && data && Object.prototype.hasOwnProperty.call(data, 'client_secret')) {
        clientSecret.value = data.client_secret || '';
    }
}

function loadDriveSettingsModal() {
    return fetch('google_settings_get.php', { credentials: 'same-origin' })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (!data || !data.success) {
                throw new Error(data && data.message ? data.message : appLang('settings.load_error'));
            }
            populateDriveSettingsModal(data);
            setDriveSettingsAlert('');
            return data;
        });
}

function openDriveSettingsModal() {
    var modal = document.getElementById('modalDriveSettings');
    if (!modal) {
        return Promise.resolve(null);
    }

    modal.style.display = 'flex';
    populateDriveSettingsModal({
        client_id: '',
        redirect_uri: '',
        folder_id: '',
        is_connected: 0,
        secret_configured: false
    });
    setDriveSettingsAlert('');
    renderLucideIcons();

    return loadDriveSettingsModal().catch(function(error) {
        var message = error && error.message ? error.message : appLang('settings.load_error');
        setDriveSettingsAlert(message, 'error');
        showToast(message, 'error');
        throw error;
    });
}

function closeDriveSettingsModal(options) {
    options = options || {};
    var modal = document.getElementById('modalDriveSettings');
    var form = document.getElementById('formDriveSettings');
    var clientSecret = document.getElementById('driveSettingsClientSecret');
    var preserve = Boolean(options.preserveState);
    if (preserve) {
        driveSettingsDraft = readDriveSettingsDraft();
    } else {
        driveSettingsDraft = null;
    }
    if (form && !preserve) {
        form.reset();
    }
    if (clientSecret && !preserve) {
        clientSecret.value = '';
    }
    if (!preserve) {
        setDriveSettingsAlert('');
    }
    if (modal) {
        modal.style.display = 'none';
    }
}
function openDriveGuideModal() {
    var modal = document.getElementById('modalDriveGuide');
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
    renderLucideIcons();
}
function closeDriveGuideModal() {
    var modal = document.getElementById('modalDriveGuide');
    if (modal) {
        modal.style.display = 'none';
    }
}
function openDriveGuideFromSettings() {
    closeDriveSettingsModal({ preserveState: true });
    openDriveGuideModal();
}
function returnToDriveSettingsFromGuide() {
    var modal = document.getElementById('modalDriveSettings');
    closeDriveGuideModal();
    if (!modal) {
        return;
    }
    modal.style.display = 'flex';
    if (driveSettingsDraft) {
        applyDriveSettingsDraft(driveSettingsDraft);
    } else {
        openDriveSettingsModal();
    }
    renderLucideIcons();
}
function copyDriveRedirectUri() {
    var input = document.getElementById('driveSettingsRedirectUri');
    if (!input || !input.value) {
        showToast(appLang('messages.redirect_uri_copy_failed', 'Redirect URI kopyalanamad?.'), 'error');
        return;
    }
    var value = input.value;
    var fallbackCopy = function() {
        input.focus();
        input.select();
        input.setSelectionRange(0, value.length);
        if (document.execCommand('copy')) {
            showToast(appLang('messages.redirect_uri_copied', 'Redirect URI kopyaland?.'), 'success');
            return true;
        }
        return false;
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value)
            .then(function() {
                showToast(appLang('messages.redirect_uri_copied', 'Redirect URI kopyaland?.'), 'success');
            })
            .catch(function() {
                if (!fallbackCopy()) {
                    showToast(appLang('messages.redirect_uri_copy_failed', 'Redirect URI kopyalanamad?.'), 'error');
                }
            });
        return;
    }
    if (!fallbackCopy()) {
        showToast(appLang('messages.redirect_uri_copy_failed', 'Redirect URI kopyalanamad?.'), 'error');
    }
}

function getOauthStatusMessage(messageKey) {
    if (!messageKey) {
        return appLang('settings.callback_error', 'Google Drive yetkilendirmesi tamamlanamad?.');
    }

    return appLang(messageKey, appLang('settings.callback_error', 'Google Drive yetkilendirmesi tamamlanamad?.'));
}

function consumeDriveSettingsQueryParams() {
    var url = new URL(window.location.href);
    var shouldOpenModal = url.searchParams.get('drive_settings') === '1';
    var oauthSuccess = url.searchParams.get('oauth_success') === '1';
    var oauthError = url.searchParams.get('oauth_error');

    if (!shouldOpenModal && !oauthSuccess && !oauthError) {
        return;
    }

    var cleanedUrl = new URL(window.location.href);
    cleanedUrl.searchParams.delete('drive_settings');
    cleanedUrl.searchParams.delete('oauth_success');
    cleanedUrl.searchParams.delete('oauth_error');
    var nextUrl = cleanedUrl.pathname + (cleanedUrl.search ? cleanedUrl.search : '') + cleanedUrl.hash;

    openDriveSettingsModal()
        .then(function() {
            if (oauthSuccess) {
                var successMessage = appLang('settings.connect_success', 'Google Drive ba?lant?s? ba?ar?yla tamamland?.');
                setDriveSettingsAlert(successMessage, 'success');
                showToast(successMessage, 'success');
            } else if (oauthError) {
                var errorMessage = getOauthStatusMessage(oauthError);
                setDriveSettingsAlert(errorMessage, 'error');
                showToast(errorMessage, 'error');
            }
        })
        .catch(function() {
            if (oauthError) {
                setDriveSettingsAlert(getOauthStatusMessage(oauthError), 'error');
            }
        })
        .finally(function() {
            window.history.replaceState({}, document.title, nextUrl);
        });
}

function setupDriveSettingsModal() {
    var openBtn = document.getElementById('btnDriveSettings');
    var closeBtn = document.getElementById('btnCloseDriveSettings');
    var closeIconBtn = document.getElementById('btnCloseDriveSettingsIcon');
    var modal = document.getElementById('modalDriveSettings');
    var guideModal = document.getElementById('modalDriveGuide');
    var form = document.getElementById('formDriveSettings');
    var oauthBtn = document.getElementById('btnStartDriveOauth');
    var saveBtn = document.getElementById('btnSaveDriveSettings');
    var guideBtn = document.getElementById('btnOpenDriveGuide');
    var copyRedirectBtn = document.getElementById('btnCopyDriveRedirectUri');
    var guideCloseBtn = document.getElementById('btnCloseDriveGuide');
    var guideCloseIconBtn = document.getElementById('btnCloseDriveGuideIcon');
    var returnBtn = document.getElementById('btnReturnDriveSettings');
    if (!openBtn || !modal || !form) {
        return;
    }
    openBtn.onclick = function() {
        openDriveSettingsModal();
    };
    [closeBtn, closeIconBtn].forEach(function(btn) {
        if (btn) {
            btn.onclick = function() {
                closeDriveSettingsModal();
            };
        }
    });
    [guideCloseBtn, guideCloseIconBtn].forEach(function(btn) {
        if (btn) {
            btn.onclick = function() {
                closeDriveGuideModal();
            };
        }
    });
    modal.onclick = function(e) {
        if (e.target === modal) {
            closeDriveSettingsModal();
        }
    };
    if (guideModal) {
        guideModal.onclick = function(e) {
            if (e.target === guideModal) {
                closeDriveGuideModal();
            }
        };
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && guideModal && guideModal.style.display === 'flex') {
            closeDriveGuideModal();
            return;
        }
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeDriveSettingsModal();
        }
    });
    if (guideBtn) {
        guideBtn.onclick = function() {
            openDriveGuideFromSettings();
        };
    }
    if (returnBtn) {
        returnBtn.onclick = function() {
            returnToDriveSettingsFromGuide();
        };
    }
    if (copyRedirectBtn) {
        copyRedirectBtn.onclick = function() {
            copyDriveRedirectUri();
        };
    }
    if (oauthBtn) {
        oauthBtn.onclick = function() {
            window.location.href = 'google_oauth_start.php';
        };
    }
    form.onsubmit = function(e) {
        e.preventDefault();
        var formData = new FormData(form);
        setDriveSettingsAlert('');
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        fetch('google_settings_save.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                if (!data || !data.success) {
                    var errorMessage = data && data.message ? data.message : appLang('settings.integration_save_error');
                    setDriveSettingsAlert(errorMessage, 'error');
                    showToast(errorMessage, 'error');
                    return;
                }
                var successMessage = data.message || appLang('settings.integration_saved');
                setDriveSettingsAlert(successMessage, 'success');
                showToast(successMessage, 'success');
                loadDriveSettingsModal().catch(function() {
                    setDriveSettingsAlert(appLang('settings.load_error'), 'error');
                    showToast(appLang('settings.load_error'), 'error');
                });
            })
            .catch(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                setDriveSettingsAlert(appLang('settings.integration_save_error'), 'error');
                showToast(appLang('settings.integration_save_error'), 'error');
            });
    };
}
// MODAL AÃ‡/KAPA (EKLEME)
document.getElementById("btnAdd").onclick = function() {
    fetch('get_categories.php')
      .then(res=>res.json())
      .then(list=>{
          var sel = document.getElementById('addCategory');
          sel.innerHTML = `<option value="">${appLang('form.select_prompt', 'SeÃ§iniz')}</option>`;
          list.forEach(function(cat){
              sel.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
          });
          initCustomSelects(document.getElementById('modalAdd'));
      });
    var addForm = document.getElementById('formAdd');
    if (addForm) {
        addForm.reset();
    }
    clearAddImagePreview();
    document.getElementById("modalAdd").style.display = "flex";
    setTimeout(function() {
        var addInput = document.getElementById('addImageInput');
        var addTrigger = document.getElementById('addImageTrigger');
        var emptyState = document.getElementById('addImageEmptyState');
        var secondaryDropzone = document.getElementById('addImageSecondaryDropzone');
        var bindOpenPicker = function(el) {
            if (!el || el.dataset.bound) {
                return;
            }
            el.addEventListener('click', function() {
                if (addInput) {
                    addInput.value = '';
                    addInput.click();
                }
            });
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (addInput) {
                        addInput.value = '';
                        addInput.click();
                    }
                }
            });
            ['dragenter', 'dragover'].forEach(function(evtName) {
                el.addEventListener(evtName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.add('is-dragover');
                });
            });
            ['dragleave', 'dragend', 'drop'].forEach(function(evtName) {
                el.addEventListener(evtName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('is-dragover');
                });
            });
            el.addEventListener('drop', function(e) {
                if (e.dataTransfer && e.dataTransfer.files) {
                    addFilesToSelection(e.dataTransfer.files);
                }
            });
            el.dataset.bound = true;
        };

        if (addInput && addTrigger && !addTrigger.dataset.bound) {
            addTrigger.addEventListener('click', function() {
                addInput.value = '';
                addInput.click();
            });
            addTrigger.dataset.bound = true;
        }

        if (addInput && !addInput.dataset.bound) {
            addInput.addEventListener('change', function() {
                addFilesToSelection(this.files);
                this.value = '';
            });
            addInput.dataset.bound = true;
        }

        bindOpenPicker(emptyState);
        bindOpenPicker(secondaryDropzone);
    }, 50);
};

document.getElementById("btnCancelAdd").onclick = function() {
    clearAddImagePreview();
    document.getElementById("modalAdd").style.display = "none";
};
if (document.getElementById('btnCloseAddIcon')) {
    document.getElementById('btnCloseAddIcon').onclick = function() {
        document.getElementById('btnCancelAdd').click();
    };
}
document.getElementById("modalAdd").onclick = function(e) {
    if (e.target === this) {
        clearAddImagePreview();
        this.style.display = "none";
    }
};

// AJAX ile EKLEME (KAYIT)
document.addEventListener('DOMContentLoaded', function() {
    loadDriveStorageCard();
    setupDriveSettingsModal();
    consumeDriveSettingsQueryParams();
    var formAdd = document.getElementById('formAdd');
    if (formAdd) {
        formAdd.onsubmit = function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var btn = this.querySelector('[type="submit"]');
            btn.disabled = true;

            formData.delete('images[]');
            if (addSelectedFiles.length) {
                addSelectedFiles.forEach(function(file) {
                    formData.append('images[]', file, file.name);
                });
            }

            fetch('add.php', {
                method: 'POST',
                body: formData
            })
            .then(r=>r.text())
            .then(res=>{
                btn.disabled = false;
                if (res.trim() == "OK") {
                    showToast(appLang('messages.add_success', 'BaÅŸarÄ±yla kaydedildi!'), "add");
                    document.getElementById('modalAdd').style.display = "none";
                    reloadGallery();
                    this.reset();
                    clearAddImagePreview();
                } else {
                    showToast(appLang('messages.add_error_prefix', 'KayÄ±t baÅŸarÄ±sÄ±z:') + ' ' + res, "error");
                }
            })
            .catch(()=>{
                btn.disabled = false;
                showToast(appLang('messages.generic_error', 'Bir hata oluÅŸtu!'), "error");
            });
        };
    }
});

// TARÄ°H FONKSÄ°YONU
function formatDate(dt) {
    if(!dt) return '-';
    var t = dt.split(' ');
    if(t.length<2) return dt;
    var d = t[0].split('-');
    var h = t[1].substring(0,5);
    return d[2]+'.'+d[1]+'.'+d[0]+' '+h;
}

function getFileType(data) {
    var sources = [data.download, data.title, data.filename];
    for (var i = 0; i < sources.length; i++) {
        var value = String(sources[i] || '');
        var match = value.match(/\.([a-z0-9]{2,5})(?:$|\?)/i);
        if (match) {
            return '.' + match[1].toLowerCase();
        }
    }
    return 'Dosya';
}

function getFileTypeInfo(data) {
    var ext = getFileType(data).replace(/^\./, '').toLowerCase();

    if (ext === 'zip' || ext === 'rar' || ext === '7z') {
        return { label: ext.toUpperCase(), icon: 'archive' };
    }
    if (ext === 'stl' || ext === '3mf' || ext === 'obj') {
        return { label: ext.toUpperCase(), icon: 'box' };
    }
    if (ext === 'step' || ext === 'stp') {
        return { label: ext.toUpperCase(), icon: 'file-cog' };
    }
    if (ext) {
        return { label: ext.toUpperCase(), icon: 'file' };
    }

    return { label: 'DOSYA', icon: 'file' };
}

function parseGalleryImages(rawValue, fallbackSrc, fallbackFilename, fallbackTitle) {
    var images = [];

    if (rawValue) {
        try {
            var parsed = JSON.parse(rawValue);
            if (Array.isArray(parsed)) {
                images = parsed
                    .filter(function(item) {
                        return item && typeof item === 'object' && item.src;
                    })
                    .map(function(item, index) {
                        return {
                            id: Number(item.id || 0),
                            filename: item.filename || '',
                            src: item.src,
                            is_cover: Number(item.is_cover) === 1 ? 1 : 0,
                            sort_order: Number(item.sort_order || 0),
                            alt: fallbackTitle || '',
                            _order: index
                        };
                    });
            }
        } catch (error) {
            images = [];
        }
    }

    if (!images.length && fallbackSrc) {
        images.push({
            id: 0,
            filename: fallbackFilename || '',
            src: fallbackSrc,
            is_cover: 1,
            sort_order: 0,
            alt: fallbackTitle || '',
            _order: 0
        });
    }

    images.sort(function(a, b) {
        if ((a.sort_order || 0) !== (b.sort_order || 0)) {
            return (a.sort_order || 0) - (b.sort_order || 0);
        }
        return (a._order || 0) - (b._order || 0);
    });

    return images.map(function(item) {
        return {
            id: Number(item.id || 0),
            filename: item.filename || '',
            src: item.src,
            is_cover: item.is_cover || 0,
            sort_order: item.sort_order || 0,
            alt: item.alt || fallbackTitle || ''
        };
    });
}

function renderLucideIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons({
            attrs: {
                width: 18,
                height: 18,
                strokeWidth: 1.9
            }
        });
    }
}

function renderDriveButtonLabel(button, text) {
    if (!button) return;
    var label = button.querySelector('.drive-label');
    if (label) {
        label.textContent = text;
        return;
    }
    button.textContent = text;
}

var addSelectedFiles = [];
var uploadTokenCounter = 0;

function getAddFileSignature(file) {
    return [file.name || '', file.size || 0, file.lastModified || 0].join('|');
}

function ensureUploadToken(file) {
    if (!file) {
        return '';
    }

    if (!file._uploadToken) {
        uploadTokenCounter += 1;
        file._uploadToken = 'upload-' + uploadTokenCounter;
    }

    return file._uploadToken;
}

function moveArrayItemByToken(list, fromToken, toToken, tokenGetter) {
    if (!Array.isArray(list) || !fromToken || !toToken || fromToken === toToken) {
        return;
    }

    var fromIndex = -1;
    var toIndex = -1;

    list.forEach(function(item, index) {
        var token = tokenGetter(item, index);
        if (token === fromToken) {
            fromIndex = index;
        }
        if (token === toToken) {
            toIndex = index;
        }
    });

    if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) {
        return;
    }

    var moved = list.splice(fromIndex, 1)[0];
    list.splice(toIndex, 0, moved);
}

function bindPreviewDragEvents(item, token, onMove) {
    if (!item || !token || typeof onMove !== 'function') {
        return;
    }

    item.draggable = true;
    item.dataset.mediaToken = token;

    item.addEventListener('dragstart', function(e) {
        item.classList.add('is-dragging');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', token);
        }
    });

    item.addEventListener('dragover', function(e) {
        e.preventDefault();
        item.classList.add('is-drag-target');
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'move';
        }
    });

    item.addEventListener('dragleave', function() {
        item.classList.remove('is-drag-target');
    });

    item.addEventListener('drop', function(e) {
        e.preventDefault();
        item.classList.remove('is-drag-target');
        var fromToken = e.dataTransfer ? e.dataTransfer.getData('text/plain') : '';
        if (fromToken) {
            onMove(fromToken, token);
        }
    });

    item.addEventListener('dragend', function() {
        item.classList.remove('is-dragging');
        item.classList.remove('is-drag-target');
    });
}

function protectPreviewActionButton(button) {
    if (!button) {
        return;
    }

    ['mousedown', 'pointerdown', 'touchstart', 'dragstart'].forEach(function(eventName) {
        button.addEventListener(eventName, function(e) {
            e.stopPropagation();
        });
    });
}

function clearAddImagePreview() {
    var previewList = document.getElementById('addImagePreviewList');
    var addFileName = document.getElementById('addFileName');
    var addInput = document.getElementById('addImageInput');
    var emptyState = document.getElementById('addImageEmptyState');
    var selectedState = document.getElementById('addImageSelectedState');
    var selectedTitle = document.getElementById('addImageSelectedTitle');
    var orderInput = document.getElementById('addNewImageOrder');

    addSelectedFiles = [];

    if (previewList) {
        previewList.innerHTML = '';
    }
    if (addFileName) {
        addFileName.textContent = appLang('form.no_file_selected', 'Dosya seÃ§ilmedi');
    }
    if (selectedTitle) {
        selectedTitle.textContent = appLang('form.selected_images', 'Seï¿½ili Gï¿½rseller');
    }
    if (emptyState) {
        emptyState.hidden = false;
    }
    if (selectedState) {
        selectedState.hidden = true;
    }
    if (addInput) {
        addInput.value = '';
    }
    if (orderInput) {
        orderInput.value = '[]';
    }
}

function renderAddImageUI() {
    var previewList = document.getElementById('addImagePreviewList');
    var addFileName = document.getElementById('addFileName');
    var emptyState = document.getElementById('addImageEmptyState');
    var selectedState = document.getElementById('addImageSelectedState');
    var selectedTitle = document.getElementById('addImageSelectedTitle');
    var orderInput = document.getElementById('addNewImageOrder');

    if (!previewList || !addFileName || !emptyState || !selectedState || !selectedTitle) {
        return;
    }

    previewList.innerHTML = '';

    if (!addSelectedFiles.length) {
        addFileName.textContent = appLang('form.no_file_selected', 'Dosya seÃ§ilmedi');
        selectedTitle.textContent = appLang('form.selected_images', 'Seï¿½ili Gï¿½rseller');
        emptyState.hidden = false;
        selectedState.hidden = true;
        return;
    }

    emptyState.hidden = true;
    selectedState.hidden = false;
    selectedTitle.textContent = appLang('form.selected_images', 'Seï¿½ili Gï¿½rseller') + ' (' + addSelectedFiles.length + ')';
    addFileName.textContent = addSelectedFiles.length === 1
        ? addSelectedFiles[0].name
        : addSelectedFiles.length + ' ' + appLang('form.image_select_count', 'gÃ¶rsel seÃ§ilidi');

    addSelectedFiles.forEach(function(file, index) {
        var token = ensureUploadToken(file);
        var item = document.createElement('div');
        item.className = 'upload-preview-item';

        var img = document.createElement('img');
        img.alt = file.name || ('preview-' + index);
        var objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        img.onload = function() {
            URL.revokeObjectURL(objectUrl);
        };
        item.appendChild(img);

        if (index === 0) {
            var badge = document.createElement('span');
            badge.className = 'upload-preview-badge';
            badge.textContent = appLang('form.cover_label', 'Kapak');
            item.appendChild(badge);
        }

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'upload-preview-remove';
        removeBtn.setAttribute('aria-label', appLang('actions.delete', 'Sil'));
        removeBtn.innerHTML = '<i data-lucide="x"></i>';
        protectPreviewActionButton(removeBtn);
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            addSelectedFiles.splice(index, 1);
            renderAddImageUI();
        });
        item.appendChild(removeBtn);

        bindPreviewDragEvents(item, token, function(fromToken, toToken) {
            moveArrayItemByToken(addSelectedFiles, fromToken, toToken, function(entry) {
                return ensureUploadToken(entry);
            });
            renderAddImageUI();
        });

        previewList.appendChild(item);
    });

    if (orderInput) {
        orderInput.value = JSON.stringify(addSelectedFiles.map(function(file, index) {
            return index;
        }));
    }

    renderLucideIcons();
}

function addFilesToSelection(fileList) {
    if (!fileList || !fileList.length) {
        return;
    }

    var existing = {};
    addSelectedFiles.forEach(function(file) {
        existing[getAddFileSignature(file)] = true;
    });

    Array.prototype.forEach.call(fileList, function(file) {
        if (!file || !String(file.type || '').match(/^image\//i)) {
            return;
        }

        var signature = getAddFileSignature(file);
        if (existing[signature]) {
            return;
        }

        existing[signature] = true;
        ensureUploadToken(file);
        addSelectedFiles.push(file);
    });

    renderAddImageUI();
}

function appendUniqueImageFiles(targetList, fileList) {
    if (!fileList || !fileList.length) {
        return;
    }

    var existing = {};
    targetList.forEach(function(file) {
        existing[getAddFileSignature(file)] = true;
    });

    Array.prototype.forEach.call(fileList, function(file) {
        if (!file || !String(file.type || '').match(/^image\//i)) {
            return;
        }

        var signature = getAddFileSignature(file);
        if (existing[signature]) {
            return;
        }

        existing[signature] = true;
        ensureUploadToken(file);
        targetList.push(file);
    });
}

var activeCustomSelect = null;

function closeCustomSelects(exceptWrap) {
    document.querySelectorAll('.custom-select.is-open').forEach(function(wrap) {
        if (exceptWrap && wrap === exceptWrap) {
            return;
        }
        wrap.classList.remove('is-open');
        var trigger = wrap.querySelector('.custom-select__trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    if (!exceptWrap) {
        activeCustomSelect = null;
    }
}

function updateCustomSelectState(select) {
    var wrap = select && select._customSelectWrap;
    if (!wrap) return;

    var selectedOption = select.options[select.selectedIndex] || select.options[0] || null;
    var label = selectedOption ? selectedOption.textContent : '';
    var value = selectedOption ? selectedOption.value : '';
    var labelEl = wrap.querySelector('.custom-select__label');

    if (labelEl) {
        labelEl.textContent = label;
        labelEl.classList.toggle('is-placeholder', value === '');
    }

    wrap.querySelectorAll('.custom-select__option').forEach(function(optionBtn) {
        var isSelected = optionBtn.dataset.value === value;
        optionBtn.classList.toggle('is-selected', isSelected);
        optionBtn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });
}

function buildCustomSelect(select) {
    if (!select) return;

    if (select._customSelectWrap && select._customSelectWrap.parentNode) {
        select._customSelectWrap.parentNode.removeChild(select._customSelectWrap);
    }

    var wrap = document.createElement('div');
    wrap.className = 'custom-select';

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'custom-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var label = document.createElement('span');
    label.className = 'custom-select__label';

    var icon = document.createElement('span');
    icon.className = 'custom-select__icon';
    icon.innerHTML = '<i data-lucide="chevron-down"></i>';

    trigger.appendChild(label);
    trigger.appendChild(icon);

    var menu = document.createElement('div');
    menu.className = 'custom-select__menu';
    menu.setAttribute('role', 'listbox');

    Array.from(select.options).forEach(function(option) {
        var optionBtn = document.createElement('button');
        optionBtn.type = 'button';
        optionBtn.className = 'custom-select__option';
        optionBtn.dataset.value = option.value;
        optionBtn.setAttribute('role', 'option');
        optionBtn.textContent = option.textContent;

        if (option.value === '') {
            optionBtn.classList.add('is-placeholder');
        }

        optionBtn.addEventListener('click', function() {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updateCustomSelectState(select);
            closeCustomSelects();
            trigger.focus();
        });

        menu.appendChild(optionBtn);
    });

    trigger.addEventListener('click', function() {
        var isOpen = wrap.classList.contains('is-open');
        closeCustomSelects(isOpen ? null : wrap);

        if (!isOpen) {
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            activeCustomSelect = wrap;
        } else {
            trigger.setAttribute('aria-expanded', 'false');
            activeCustomSelect = null;
        }
    });

    trigger.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCustomSelects();
        }
    });

    select.addEventListener('change', function() {
        updateCustomSelectState(select);
    });

    wrap.appendChild(trigger);
    wrap.appendChild(menu);

    select.classList.add('custom-select__native');
    select.setAttribute('tabindex', '-1');
    select.parentNode.insertBefore(wrap, select);

    select._customSelectWrap = wrap;
    updateCustomSelectState(select);
    renderLucideIcons();
}

function initCustomSelects(scope) {
    (scope || document).querySelectorAll('select[name="category_id"]').forEach(function(select) {
        buildCustomSelect(select);
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        closeCustomSelects();
    }
});

function ensureLightboxRoot() {
    var lightbox = document.getElementById('imgLightbox');
    if (lightbox && lightbox.parentElement !== document.body) {
        document.body.appendChild(lightbox);
    }

    if (lightbox && !lightbox.querySelector('.lightbox-nav')) {
        lightbox.insertAdjacentHTML('beforeend', `
            <button type="button" class="lightbox-nav lightbox-nav--prev" data-lightbox-nav="prev" aria-label="Previous image">
                <i data-lucide="chevron-left"></i>
            </button>
            <button type="button" class="lightbox-nav lightbox-nav--next" data-lightbox-nav="next" aria-label="Next image">
                <i data-lucide="chevron-right"></i>
            </button>
            <div class="lightbox-counter" id="lightboxCounter" aria-live="polite"></div>
        `);
        renderLucideIcons();
    }

    return lightbox;
}

var lastLightboxTrigger = null;
var currentGalleryImages = [];
var currentGalleryIndex = 0;

function resetGalleryState() {
    currentGalleryImages = [];
    currentGalleryIndex = 0;
}

function normalizeGalleryIndex(index) {
    var total = currentGalleryImages.length;
    if (!total) {
        return 0;
    }

    var normalized = index % total;
    if (normalized < 0) {
        normalized += total;
    }
    return normalized;
}

function setCurrentGallery(images, index) {
    currentGalleryImages = Array.isArray(images) ? images.filter(function(image) {
        return image && image.src;
    }) : [];
    currentGalleryIndex = normalizeGalleryIndex(typeof index === 'number' ? index : 0);
}

function getCurrentGalleryImage() {
    if (!currentGalleryImages.length) {
        return null;
    }
    return currentGalleryImages[normalizeGalleryIndex(currentGalleryIndex)] || null;
}

function updateGalleryCounter(counterEl) {
    if (!counterEl) {
        return;
    }

    if (currentGalleryImages.length > 1) {
        counterEl.textContent = (normalizeGalleryIndex(currentGalleryIndex) + 1) + ' / ' + currentGalleryImages.length;
        counterEl.style.display = 'inline-flex';
    } else {
        counterEl.textContent = '';
        counterEl.style.display = 'none';
    }
}

function updateDetailGalleryUI() {
    var detailPreview = document.getElementById('detailImgPreview');
    if (!detailPreview) {
        return;
    }

    var currentImage = getCurrentGalleryImage();
    if (currentImage && currentImage.src) {
        detailPreview.src = currentImage.src;
        detailPreview.alt = currentImage.alt || detailPreview.alt || '';
    }

    document.querySelectorAll('.detail-thumb').forEach(function(thumb, thumbIndex) {
        var isActive = thumbIndex === normalizeGalleryIndex(currentGalleryIndex);
        thumb.classList.toggle('active', isActive);
        if (isActive) {
            thumb.setAttribute('aria-current', 'true');
            if (typeof thumb.scrollIntoView === 'function') {
                thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        } else {
            thumb.removeAttribute('aria-current');
        }
    });

    document.querySelectorAll('.detail-gallery-nav').forEach(function(btn) {
        btn.style.display = currentGalleryImages.length > 1 ? 'inline-flex' : 'none';
    });

    updateGalleryCounter(document.getElementById('detailGalleryCounter'));
}

function setDetailGalleryIndex(index) {
    currentGalleryIndex = normalizeGalleryIndex(index);
    updateDetailGalleryUI();
    if (isLightboxOpen()) {
        renderLightboxImage();
    }
}

function changeDetailGallery(step) {
    setDetailGalleryIndex(currentGalleryIndex + step);
}

function isLightboxOpen() {
    var lightbox = document.getElementById('imgLightbox');
    return !!(lightbox && lightbox.style.display === 'flex');
}

function renderLightboxImage() {
    var lightbox = ensureLightboxRoot();
    var lightboxImg = document.getElementById('lightboxImg');
    if (!lightbox || !lightboxImg) {
        return;
    }

    var currentImage = getCurrentGalleryImage();
    if (currentImage && currentImage.src) {
        lightboxImg.src = currentImage.src;
        lightboxImg.alt = currentImage.alt || lightboxImg.alt || '';
    }

    lightbox.querySelectorAll('.lightbox-nav').forEach(function(btn) {
        btn.style.display = currentGalleryImages.length > 1 ? 'inline-flex' : 'none';
    });

    updateGalleryCounter(document.getElementById('lightboxCounter'));
    renderLucideIcons();
}

function openLightboxAtIndex(index, triggerEl) {
    var lightbox = ensureLightboxRoot();
    var lightboxImg = document.getElementById('lightboxImg');
    if (!lightbox || !lightboxImg) return;

    currentGalleryIndex = normalizeGalleryIndex(index);
    lastLightboxTrigger = triggerEl || null;
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
    }

    renderLightboxImage();
    lightbox.style.display = 'flex';
    lightbox.setAttribute('tabindex', '-1');
    lightbox.focus();
}

function openLightboxFromSrc(src, triggerEl) {
    if (currentGalleryImages.length) {
        var matchedIndex = currentGalleryImages.findIndex(function(image) {
            return image && image.src === src;
        });
        openLightboxAtIndex(matchedIndex >= 0 ? matchedIndex : currentGalleryIndex, triggerEl);
        return;
    }

    var lightbox = ensureLightboxRoot();
    var lightboxImg = document.getElementById('lightboxImg');
    if (!lightbox || !lightboxImg || !src) return;

    resetGalleryState();
    currentGalleryImages = [{ src: src, alt: '' }];
    openLightboxAtIndex(0, triggerEl);
}

function changeLightboxImage(step) {
    if (!isLightboxOpen() || currentGalleryImages.length <= 1) {
        return;
    }
    currentGalleryIndex = normalizeGalleryIndex(currentGalleryIndex + step);
    renderLightboxImage();
    updateDetailGalleryUI();
}

function closeLightbox() {
    var lightbox = document.getElementById('imgLightbox');
    var lightboxImg = document.getElementById('lightboxImg');
    if (lightbox) {
        lightbox.style.display = 'none';
    }
    if (lightboxImg) {
        lightboxImg.src = '';
    }
    var lightboxCounter = document.getElementById('lightboxCounter');
    if (lightboxCounter) {
        lightboxCounter.textContent = '';
    }
    if (lastLightboxTrigger && typeof lastLightboxTrigger.focus === 'function') {
        lastLightboxTrigger.focus();
    }
    lastLightboxTrigger = null;
}

document.addEventListener('click', function(e) {
    var lightboxNav = e.target.closest('[data-lightbox-nav]');
    if (lightboxNav) {
        e.preventDefault();
        e.stopPropagation();
        changeLightboxImage(lightboxNav.getAttribute('data-lightbox-nav') === 'next' ? 1 : -1);
    }
});

// SÄ°LME MODALI
function showConfirmModal(message, callback) {
    var modal = document.getElementById('confirmModal');
    var text = document.getElementById('confirmModalText');
    var btnYes = document.getElementById('confirmModalYes');
    var btnNo = document.getElementById('confirmModalNo');
    text.textContent = message;

    modal.style.display = 'flex';
    btnYes.onclick = function() {
        modal.style.display = 'none';
        callback(true);
    };
    btnNo.onclick = function() {
        modal.style.display = 'none';
        callback(false);
    };
    modal.onclick = function(e) {
        if(e.target === modal) {
            modal.style.display = 'none';
            callback(false);
        }
    }
}

// KARTLARA EVENT BAÄLA (her AJAX sonrasÄ± tekrar Ã§aÄŸÄ±r!)
function bindGalleryItems() {
    document.querySelectorAll('.gallery-item, .recent-openable').forEach(function(item){
        item.onclick = function() {
            if (!document.getElementById('detailContent') || !document.getElementById('modalDetail')) return;
            var data = this.dataset;
            var html = `
                <div class="modal-title">${data.title}</div>
                <div style="text-align:center;">
                    <img src="upload/${data.filename}" alt="${data.title}" class="detail-img" id="detailImgPreview">
                </div>
                <div class="detail-meta"><b>${appLang('detail.category', 'Kategori')}:</b> ${data.category}</div>
                <div class="detail-meta"><b>${appLang('detail.title', 'Başlık')}:</b> ${data.title}</div>
                <div class="detail-meta"><b>${appLang('form.size', 'Boyut')}:</b> ${data.size}</div>
                <div class="detail-meta"><b>${appLang('detail.created_at', 'Eklenme Tarihi')}:</b> ${formatDate(data.created)}</div>
                <div class="detail-meta"><b>${appLang('detail.download', 'Download')}:</b>
                    <a href="${data.download}" class="btn-download" target="_blank">${appLang('actions.download', 'İndir')}</a>
                </div>
                <div class="modal-actions" style="margin-top:20px;">
                    <button class="btn-main" id="btnEditDetail">${appLang('actions.edit', 'Düzenle')}</button>
                    <button class="btn-danger" id="btnDeleteDetail">${appLang('actions.delete', 'Sil')}</button>
                    <button class="btn-cancel" id="btnCloseDetail">${appLang('actions.close', 'Kapat')}</button>
                </div>
            `;
            document.getElementById('detailContent').innerHTML = html;
            document.getElementById('modalDetail').style.display = "flex";
            // Kapat
            document.getElementById('btnCloseDetail').onclick = function() {
                document.getElementById('modalDetail').style.display = "none";
            };
            // DÃœZENLE
            document.getElementById('btnEditDetail').onclick = function() {
                var editHtml = `
<div class="modal-title">${appLang('modal.edit_record', 'Kayıt Düzenle')}</div>
<form method="post" id="formEdit" enctype="multipart/form-data">
    <input type="hidden" name="id" value="${data.id}">
    <label>${appLang('form.category', 'Kategori')}:</label>
    <select name="category_id" required id="editCategory"></select>
    <label>${appLang('form.title', 'Başlık')}:</label>
    <input type="text" name="title" maxlength="100" required value="${data.title}">
    <label>${appLang('form.size', 'Boyut')}:</label>
    <input type="text" name="size" maxlength="50" required value="${data.size}">
    <label>${appLang('form.download_link', 'Download Linki')}:</label>
    <input type="url" name="download" maxlength="255" required value="${data.download}">
    <label>${appLang('form.image', 'Görsel')}:</label>
<img id="editImagePreview" src="upload/${data.filename}" alt="${appLang('form.image', 'Görsel')}" style="max-width:120px;max-height:120px;display:block;margin-bottom:6px;">
<div class="file-upload-wrapper" style="margin-bottom: 10px;">
    <input type="file" id="editImageInput" name="image" class="file-input" accept="image/*" hidden>
    <label for="editImageInput" class="custom-file-btn">${appLang('form.image_select', 'Görsel Seç')}</label>
    <span id="fileName" class="file-name-label">${appLang('form.no_file_selected', 'Dosya seçilmedi')}</span>
</div>
    <div class="modal-actions" style="margin-top:20px;">
        <button type="submit" class="btn-main">${appLang('actions.save', 'Kaydet')}</button>
        <button type="button" class="btn-cancel" id="btnCancelEdit">${appLang('actions.cancel', 'İptal')}</button>
    </div>
</form>
`;
                document.getElementById('detailContent').innerHTML = editHtml;
                fetch('get_categories.php')
                  .then(res=>res.json())
                  .then(list=>{
                      var sel = document.getElementById('editCategory');
                      list.forEach(function(cat){
                        sel.innerHTML += `<option value="${cat.id}"${cat.id==data.categoryid?' selected':''}>${cat.name}</option>`;
                      });
                  });
                document.getElementById('editImageInput').addEventListener('change', function(e) {
                  const preview = document.getElementById('editImagePreview');
                  if (e.target.files && e.target.files[0]) {
                    preview.src = URL.createObjectURL(e.target.files[0]);
                  }
                });
                document.getElementById('btnCancelEdit').onclick = function() {
                    document.getElementById('modalDetail').style.display = "none";
                };
                document.getElementById('formEdit').onsubmit = function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var btn = this.querySelector('[type="submit"]');
                    btn.disabled = true;
                    fetch('update.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(r=>r.text())
                    .then(res=>{
                        btn.disabled = false;
                        if(res.trim() == "OK") {
                            showToast(appLang('messages.update_success', 'KayÄ±t gÃ¼ncellendi!'), "update");
                            document.getElementById('modalDetail').style.display = "none";
                            reloadGallery();
                        } else {
                            showToast(appLang('messages.update_error_prefix', 'GÃ¼ncelleme baÅŸarÄ±sÄ±z:') + ' ' + res, "error");
                        }
                    })
                    .catch(()=>{
                        btn.disabled = false;
                        showToast(appLang('messages.generic_error', 'Bir hata oluÅŸtu!'), "error");
                    });
                };
            };

            // Sil
            document.getElementById('btnDeleteDetail').onclick = function() {
                showConfirmModal(appLang('modal.confirm_delete', 'Bu kaydÄ± silmek istediÄŸinize emin misiniz?'), function(confirmed) {
                    if (confirmed) {
                        fetch('delete.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'id=' + encodeURIComponent(data.id)
                        })
                        .then(r=>r.text())
                        .then(res=>{
                            if(res.trim() == 'OK') {
                                document.getElementById('modalDetail').style.display = "none";
                                reloadGallery();
                                showToast(appLang('messages.delete_success', 'KayÄ±t silindi!'), "delete");
                            } else {
                                showToast(appLang('messages.delete_error_prefix', 'Silinemedi:') + ' ' + res, "error");
                            }
                        });
                    }
                });
            };
        }
    });
}
bindGalleryItems();

// AJAX ile galeri arama/yenileme
function reloadGallery(val = '') {
    var val = typeof val === 'string' ? val : document.getElementById('searchInput').value.trim();
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'includes/gallery_list.php?search=' + encodeURIComponent(val), true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            document.getElementById('galleryResults').innerHTML = xhr.responseText;
            bindGalleryItems();
            loadDriveStorageCard();
        }
    };
    xhr.send();
}
document.getElementById('searchInput').addEventListener('input', function() {
    reloadGallery(this.value);
});

// Modal dÄ±ÅŸÄ±na tÄ±klayÄ±nca kapan
if(document.getElementById('modalDetail')) {
    document.getElementById('modalDetail').onclick = function(e) {
        if(e.target === this) {
            if (isLightboxOpen()) {
                closeLightbox();
            }
            resetGalleryState();
            this.style.display = "none";
        }
    };
}
if(document.getElementById('modalCategory')) {
    document.getElementById('modalCategory').onclick = function(e) {
        if(e.target === this) this.style.display = "none";
    };
}

// Kategori yÃ¶netimi (toast entegre!)
document.getElementById('btnCategory').onclick = function() {
    fetch('get_categories.php')
      .then(res=>res.json())
      .then(list=>{
          var html = `
            <button type="button" class="modal-corner-close" id="btnCloseCategoryIcon" aria-label="${appLang('actions.close', 'Kapat')}"><i data-lucide="x"></i></button>
            <div class="modal-title">${appLang('category.title', 'Kategoriler')}</div>
            <form id="formAddCat" style="display:flex;gap:6px;margin-bottom:12px;">
              <input type="text" name="name" maxlength="40" placeholder="${appLang('category.add_new', 'Yeni kategori ekle')}" aria-label="${appLang('category.name', 'Kategori adÄ±')}" required style="flex:1;">
              <button class="btn-main" type="submit">${appLang('actions.add', 'Ekle')}</button>
            </form>
            <div class="cat-list">
          `;
          list.forEach(function(cat){
            html += `
              <div class="cat-row" data-id="${cat.id}">
                <span class="cat-drag-handle" draggable="true" aria-label="${appLang('category.reorder_handle', 'Sürükle bırak ile sırala')}" title="${appLang('category.reorder_handle', 'Sürükle bırak ile sırala')}"><i data-lucide="grip-vertical"></i></span>
                <span class="cat-name">${cat.name}</span>
                <div class="cat-row-actions">
                  <button class="btn-mini" type="button" onclick="editCat(${cat.id},'${cat.name.replace(/'/g,"\\'")}')">${appLang('actions.edit', 'DÃ¼zenle')}</button>
                  <button class="btn-mini btn-danger" type="button" onclick="delCat(${cat.id})">${appLang('actions.delete', 'Sil')}</button>
                </div>
              </div>
            `;
          });
          html += `</div>
            <div class="modal-actions" style="margin-top:16px;">
              <button class="btn-main" type="button" id="btnSaveCategoryOrder" disabled>${appLang('category.save_order', 'Sıralamayı Kaydet')}</button>
              <button class="btn-cancel" type="button" id="btnCatClose">${appLang('actions.close', 'Kapat')}</button>
            </div>`;
          document.getElementById('categoryContent').innerHTML = html;
          document.getElementById('modalCategory').style.display = "flex";
          renderLucideIcons();
          initCategoryReorderUI();

          document.getElementById('formAddCat').onsubmit = function(e){
            e.preventDefault();
            fetch('cat_add.php',{
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body: 'name='+encodeURIComponent(this.name.value)
            }).then(r=>r.text()).then(res=>{
              if(res.trim() == 'OK') {
                showToast(appLang('messages.category_add_success', 'Kategori eklendi!'), "add");
                document.getElementById('modalCategory').style.display = "none";
                setTimeout(()=>location.reload(), 1200);
              } else {
                showToast(appLang('messages.category_add_error_prefix', 'Kategori eklenemedi:') + ' ' + res, "error");
              }
            });
          };
          document.getElementById('btnCatClose').onclick = function() {
            document.getElementById('modalCategory').style.display = "none";
          };
          document.getElementById('btnCloseCategoryIcon').onclick = function() {
            document.getElementById('btnCatClose').click();
          };
      });
};
// KATEGORÄ° DÃœZENLEME TOAST
window.editCat = function(id, name) {
    var row = document.querySelector('.cat-row[data-id="'+id+'"]');
    if(!row) return;
    var prevHtml = row.innerHTML;
    row.innerHTML = `
        <input type="text" class="cat-edit-input" value="${name.replace(/"/g, '&quot;')}" aria-label="${appLang('category.name', 'Kategori adÄ±')}" style="flex:1;min-width:0;">
        <button class="btn-mini btn-main" type="button" id="btnSaveCat${id}">${appLang('actions.save', 'Kaydet')}</button>
        <button class="btn-mini btn-cancel" type="button" id="btnCancelCat${id}">${appLang('actions.cancel', 'Ä°ptal')}</button>
    `;
    document.getElementById('btnSaveCat'+id).onclick = function() {
        var newName = row.querySelector('.cat-edit-input').value.trim();
        if(newName.length<2) {
            showToast(appLang('messages.category_name_short', 'Kategori adÄ± Ã§ok kÄ±sa!'), "error");
            return;
        }
        fetch('cat_edit.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id='+id+'&name='+encodeURIComponent(newName)
        }).then(r=>r.text()).then(res=>{
            if(res.trim() == 'OK') {
                showToast(appLang('messages.category_update_success', 'Kategori gÃ¼ncellendi!'), "update");
                setTimeout(()=>location.reload(), 1200);
            } else {
                showToast(appLang('messages.category_update_error_prefix', 'Kategori gÃ¼ncellenemedi:') + ' ' + res, "error");
            }
        });
    };
    document.getElementById('btnCancelCat'+id).onclick = function() {
        row.innerHTML = prevHtml;
    };
};
// KATEGORÄ° SÄ°LME TOAST ve MODAL ile!
window.delCat = function(id) {
    showConfirmModal(appLang('category.confirm_delete', 'Bu kategoriyi silmek istediÄŸinize emin misiniz?'), function(confirmed) {
        if (!confirmed) return;
        fetch('cat_delete.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'id=' + encodeURIComponent(id)
        })
        .then(r=>r.text())
        .then(res=>{
            if(res.trim() == 'OK') {
                showToast(appLang('messages.category_delete_success', 'Kategori silindi!'), "delete");
                setTimeout(()=>location.reload(), 1200);
            } else {
                showToast(appLang('messages.category_delete_error_prefix', 'Kategori silinemedi:') + ' ' + res, "error");
            }
        });
    });
};

function getCategoryOrderIds() {
    return Array.from(document.querySelectorAll('#categoryContent .cat-row[data-id]')).map(function(row) {
        return Number(row.getAttribute('data-id') || 0);
    }).filter(function(id) {
        return id > 0;
    });
}

function updateCategoryOrderSaveState(initialOrderKey) {
    var saveBtn = document.getElementById('btnSaveCategoryOrder');
    if (!saveBtn) {
        return;
    }

    var currentOrderKey = getCategoryOrderIds().join(',');
    var isDirty = currentOrderKey !== initialOrderKey;
    saveBtn.disabled = !isDirty;
    saveBtn.classList.toggle('is-enabled', isDirty);
}

function initCategoryReorderUI() {
    var listEl = document.querySelector('#categoryContent .cat-list');
    var saveBtn = document.getElementById('btnSaveCategoryOrder');
    if (!listEl || !saveBtn) {
        return;
    }

    var draggedRow = null;
    var initialOrderKey = getCategoryOrderIds().join(',');

    var getDragAfterElement = function(container, clientY) {
        var draggableRows = Array.from(container.querySelectorAll('.cat-row:not(.is-dragging)'));

        return draggableRows.reduce(function(closest, child) {
            var box = child.getBoundingClientRect();
            var offset = clientY - box.top - (box.height / 2);

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    };

    listEl.querySelectorAll('.cat-drag-handle').forEach(function(handle) {
        var row = handle.closest('.cat-row');
        if (!row) {
            return;
        }

        handle.addEventListener('dragstart', function(e) {
            draggedRow = row;
            row.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', row.getAttribute('data-id') || '');
            }
        });

        handle.addEventListener('dragend', function() {
            row.classList.remove('is-dragging');
            draggedRow = null;
            updateCategoryOrderSaveState(initialOrderKey);
        });
    });

    listEl.addEventListener('dragover', function(e) {
        if (!draggedRow) {
            return;
        }

        e.preventDefault();
        var afterElement = getDragAfterElement(listEl, e.clientY);
        if (!afterElement) {
            listEl.appendChild(draggedRow);
        } else if (afterElement !== draggedRow) {
            listEl.insertBefore(draggedRow, afterElement);
        }
    });

    saveBtn.addEventListener('click', function() {
        var ids = getCategoryOrderIds();
        if (!ids.length) {
            return;
        }

        saveBtn.disabled = true;

        fetch('cat_reorder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(res) {
            if (res && res.success) {
                showToast(appLang('messages.category_reorder_saved', 'Kategori sıralaması kaydedildi!'), 'update');
                document.getElementById('modalCategory').style.display = 'none';
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                saveBtn.disabled = false;
                saveBtn.classList.add('is-enabled');
                showToast((res && res.message) ? res.message : appLang('messages.category_reorder_save_error', 'Kategori sıralaması kaydedilemedi.'), 'error');
            }
        })
        .catch(function() {
            saveBtn.disabled = false;
            saveBtn.classList.add('is-enabled');
            showToast(appLang('messages.category_reorder_save_error', 'Kategori sıralaması kaydedilemedi.'), 'error');
        });
    });
}


if(document.getElementById('imgLightbox')) {
    document.getElementById('imgLightbox').onclick = function(e) {
        if(e.target === this) {
            closeLightbox();
        }
    };
}
function gotoPage(page) {
    var searchVal = document.getElementById('searchInput').value.trim();
    var url = 'includes/gallery_list.php?page=' + page + '&search=' + encodeURIComponent(searchVal);
    var catEl = document.querySelector('.sidebar li.active[data-category]');
    if (catEl) {
        url += '&category=' + encodeURIComponent(catEl.getAttribute('data-category'));
    }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() {
        if (xhr.status == 200) {
            document.getElementById('galleryResults').innerHTML = xhr.responseText;
            bindGalleryItems();
        }
    };
    xhr.send();
}
// ESC ile tÃ¼m aÃ§Ä±k modal pencereleri kapat
document.addEventListener('keydown', function(e) {
    if (isLightboxOpen()) {
        if (e.key === "ArrowRight") {
            e.preventDefault();
            changeLightboxImage(1);
            return;
        }
        if (e.key === "ArrowLeft") {
            e.preventDefault();
            changeLightboxImage(-1);
            return;
        }
        if (e.key === "Escape") {
            e.preventDefault();
            closeLightbox();
            return;
        }
    }

    if (e.key === "Escape") {
        // AÃ§Ä±k modal var mÄ± kontrol et
        var modals = [
            document.getElementById('modalAdd'),
            document.getElementById('modalDetail'),
            document.getElementById('modalCategory'),
            document.getElementById('confirmModal'),
            document.getElementById('imgLightbox')
        ];
        modals.forEach(function(modal){
            if(modal && modal.style.display === "flex"){
                // Kapat
                modal.style.display = "none";
                if (modal.id === 'modalDetail') {
                    resetGalleryState();
                }
                // IÅŸlem tamam, eÄŸer onay modalÄ±ysa callback tetiklenmez, sadece gÃ¶rsel kapanÄ±r.
            }
        });
    }
});

// Google Drive auth URL geldiyse toast yerine kalici modal kullan
document.getElementById('btnDriveSync').onclick = function() {
    var btn = this;
    btn.disabled = true;
    renderDriveButtonLabel(btn, appLang('messages.drive_syncing', 'GÃ¼ncelleniyor...'));

    fetch('drive_sync.php')
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            renderDriveButtonLabel(btn, appLang('actions.google_drive_update', 'Google Drive dan GÃ¼ncelle'));

            if (res.status === "success") {
                showToast(res.message, "add");
                reloadGallery();
            } else if (showDriveAuthModal(res.message)) {
                showToast(appLang('messages.drive_auth_prompt_opened', 'Drive baÄŸlantÄ±sÄ±nÄ± yenilemek iÃ§in pencere aÃ§Ä±ldÄ±.'), "update");
            } else {
                showToast(res.message, "error");
            }
        })
        .catch(() => {
            btn.disabled = false;
            renderDriveButtonLabel(btn, appLang('actions.google_drive_update', 'Google Drive dan GÃ¼ncelle'));
            showToast(appLang('messages.drive_sync_error', 'Drive gÃ¼ncelleme sÄ±rasÄ±nda bir hata oluÅŸtu.'), "error");
        });
};

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        var authModal = document.getElementById('modalDriveAuth');
        if (authModal && authModal.style.display === "flex") {
            authModal.style.display = "none";
        }
    }
});

function bindGalleryItems() {
    document.querySelectorAll('.gallery-item, .recent-openable').forEach(function(item){
        item.onclick = function() {
            if (!document.getElementById('detailContent') || !document.getElementById('modalDetail')) return;
            var data = this.dataset;
            var previewSrc = data.imagesrc || 'assets/no-image.png';
            var fileType = getFileType(data);
            var fileTypeInfo = getFileTypeInfo(data);
            var galleryImages = parseGalleryImages(data.galleryImages, previewSrc, data.filename || '', data.title || '');
            var primaryImage = galleryImages[0] || null;
            if (primaryImage && primaryImage.src) {
                previewSrc = primaryImage.src;
            }
            setCurrentGallery(galleryImages, 0);
            var editPreviewSrc = previewSrc;
            var showThumbStrip = galleryImages.length > 1;
            var html = `
                <div class="detail-shell">
                    <button type="button" class="detail-close-icon" id="btnCloseDetail" aria-label="${appLang('actions.close', 'Kapat')}"><i data-lucide="x"></i></button>
                    <div class="detail-hero">
                        <div class="detail-preview-panel">
                            <div class="detail-preview-frame">
                                <span class="detail-file-badge"><i data-lucide="${fileTypeInfo.icon}"></i><span>${fileTypeInfo.label}</span></span>
                                ${showThumbStrip ? `
                                <button type="button" class="detail-gallery-nav detail-gallery-nav--prev" data-detail-nav="prev" aria-label="Previous image">
                                    <i data-lucide="chevron-left"></i>
                                </button>
                                <button type="button" class="detail-gallery-nav detail-gallery-nav--next" data-detail-nav="next" aria-label="Next image">
                                    <i data-lucide="chevron-right"></i>
                                </button>
                                <div class="detail-gallery-counter" id="detailGalleryCounter" aria-live="polite"></div>` : ''}
                                <button type="button" class="detail-preview-ghost" aria-label="${appLang('actions.preview_enlarge', 'GÃ¶rseli bÃ¼yÃ¼t')}"><i data-lucide="expand"></i></button>
                                <img src="${previewSrc}" alt="${data.title}" class="detail-img" id="detailImgPreview">
                            </div>
                            ${showThumbStrip ? `
                            <div class="detail-thumb-strip">
                                ${galleryImages.map(function(image, index) {
                                    return `<button type="button" class="detail-thumb${index === 0 ? ' active' : ''}" data-gallery-index="${index}"${index === 0 ? ' aria-current="true"' : ''}>
                                        <img src="${image.src}" alt="${image.alt || data.title}">
                                    </button>`;
                                }).join('')}
                            </div>` : ''}
                        </div>
                        <div class="detail-sidebar">
                            <div class="detail-header-block">
                                <div class="modal-title detail-title">${data.title}</div>
                                <div class="detail-badges">
                                    <span class="detail-badge">${data.category || appLang('common.uncategorized', 'Kategorisiz')}</span>
                                </div>
                            </div>
                            <div class="detail-info-grid">
                                <div class="detail-info-card">
                                    <span class="detail-info-label">${appLang('detail.file_size', 'Dosya Boyutu')}</span>
                                    <strong class="detail-info-value">${data.size || '-'}</strong>
                                </div>
                                <div class="detail-info-card">
                                    <span class="detail-info-label">${appLang('detail.file_type', 'Dosya TÃ¼rÃ¼')}</span>
                                    <strong class="detail-info-value detail-filetype-value"><i data-lucide="${fileTypeInfo.icon}"></i><span>${fileTypeInfo.label}</span></strong>
                                </div>
                                <div class="detail-info-card">
                                    <span class="detail-info-label">${appLang('detail.created_at', 'Eklenme Tarihi')}</span>
                                    <strong class="detail-info-value">${formatDate(data.created)}</strong>
                                </div>
                                <div class="detail-info-card">
                                    <span class="detail-info-label">${appLang('detail.category', 'Kategori')}</span>
                                    <strong class="detail-info-value">${data.category || '-'}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-actions-panel">
                        <div class="detail-actions-title">${appLang('detail.operations', 'Ä°ÅŸlemler')}</div>
                        <div class="detail-actions-grid">
                            <a href="${data.download}" class="btn-download detail-action-btn detail-action-card" target="_blank">
                                <span class="detail-action-icon"><i data-lucide="download"></i></span>
                                <span class="detail-action-copy">
                                    <strong>${appLang('actions.download', 'Ä°ndir')}</strong>
                                    <small>${appLang('actions.download_desc', 'DosyayÄ± indir')}</small>
                                </span>
                            </a>
                            <button class="btn-cancel detail-action-btn detail-action-card" id="btnEditDetail">
                                <span class="detail-action-icon"><i data-lucide="pencil"></i></span>
                                <span class="detail-action-copy">
                                    <strong>${appLang('actions.edit', 'DÃ¼zenle')}</strong>
                                    <small>${appLang('actions.edit_desc', 'Bilgileri dÃ¼zenle')}</small>
                                </span>
                            </button>
                            <button class="btn-danger detail-action-btn detail-action-card" id="btnDeleteDetail">
                                <span class="detail-action-icon"><i data-lucide="trash-2"></i></span>
                                <span class="detail-action-copy">
                                    <strong>${appLang('actions.delete', 'Sil')}</strong>
                                    <small>${appLang('actions.delete_desc', 'DosyayÄ± sil')}</small>
                                </span>
                            </button>
                            <button class="btn-cancel detail-action-btn detail-action-card detail-close-btn-secondary" type="button">
                                <span class="detail-action-icon"><i data-lucide="x"></i></span>
                                <span class="detail-action-copy">
                                    <strong>${appLang('actions.close', 'Kapat')}</strong>
                                    <small>${appLang('actions.close_desc', 'Pencereyi kapat')}</small>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('detailContent').innerHTML = html;
            document.getElementById('modalDetail').style.display = "flex";
            renderLucideIcons();

            var detailPreview = document.getElementById('detailImgPreview');
            var detailThumbs = document.querySelectorAll('.detail-thumb');

            detailThumbs.forEach(function(thumb, index) {
                thumb.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    setDetailGalleryIndex(index);
                };
            });

            document.querySelectorAll('.detail-gallery-nav').forEach(function(btn) {
                btn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    changeDetailGallery(btn.getAttribute('data-detail-nav') === 'next' ? 1 : -1);
                };
            });

            updateDetailGalleryUI();

            var previewGhost = document.querySelector('.detail-preview-ghost');
            if (previewGhost) {
                previewGhost.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openLightboxAtIndex(currentGalleryIndex, previewGhost);
                };
            }

            document.querySelectorAll('#btnCloseDetail, .detail-close-btn-secondary').forEach(function(btn) {
                btn.onclick = function() {
                    if (isLightboxOpen()) {
                        closeLightbox();
                    }
                    resetGalleryState();
                    document.getElementById('modalDetail').style.display = "none";
                };
            });

            document.getElementById('btnEditDetail').onclick = function() {
                var existingMedia = galleryImages.map(function(image) {
                    return {
                        id: Number(image.id || 0),
                        filename: image.filename || '',
                        src: image.src,
                        is_cover: Number(image.is_cover) === 1 ? 1 : 0,
                        sort_order: Number(image.sort_order || 0)
                    };
                });
                existingMedia.forEach(function(item, index) {
                    item._token = item.id > 0 ? 'existing:' + item.id : 'fallback:' + index;
                });
                var removedMediaIds = [];
                var removedExistingTokens = [];
                var newFiles = [];
                var editMediaOrder = existingMedia.map(function(item) {
                    return item._token;
                });
                var coverSelection = {
                    type: '',
                    existingId: 0,
                    newToken: ''
                };
                var initialCover = existingMedia.find(function(item) {
                    return Number(item.is_cover) === 1 && Number(item.id || 0) > 0;
                });
                if (initialCover) {
                    coverSelection.type = 'existing';
                    coverSelection.existingId = Number(initialCover.id || 0);
                }
                var editHtml = `
<button type="button" class="modal-corner-close" id="btnCloseEditIcon" aria-label="${appLang('actions.close', 'Kapat')}"><i data-lucide="x"></i></button>
<div class="modal-title">${appLang('modal.edit_record', 'KayÄ±t DÃ¼zenle')}</div>
<form method="post" id="formEdit" enctype="multipart/form-data">
    <input type="hidden" name="id" value="${data.id}">
    <input type="hidden" name="cover_image_id" id="editCoverImageId" value="">
    <input type="hidden" name="cover_new_upload_index" id="editCoverNewUploadIndex" value="">
    <input type="hidden" name="media_order" id="editMediaOrder" value="[]">
    <label>${appLang('form.category', 'Kategori')}:</label>
    <select name="category_id" required id="editCategory"></select>
    <label>${appLang('form.title', 'BaÅŸlÄ±k')}:</label>
    <input type="text" name="title" maxlength="100" required value="${data.title}">
    <label>${appLang('form.size', 'Boyut')}:</label>
    <input type="text" name="size" maxlength="50" required value="${data.size}">
    <label>${appLang('form.download_link', 'Download Linki')}:</label>
    <input type="url" name="download" maxlength="255" required value="${data.download}">
    <label>${appLang('form.image', 'Gï¿½rsel')}:</label>
    <div class="edit-media-manager">
        <div class="upload-selected-header">
            <span class="upload-selected-subtitle">${appLang('form.cover_image', 'Kapak gï¿½rseli')}</span>
            <strong id="editMediaTitle">${appLang('form.selected_images', 'Seï¿½ili Gï¿½rseller')}</strong>
        </div>
        <div id="editMediaPreviewList" class="upload-preview-list" aria-live="polite"></div>
        <input type="file" id="editImageInput" name="new_images[]" class="file-input" accept="image/*" multiple>
        <div id="editImageDropzone" class="upload-dropzone upload-dropzone--compact" tabindex="0" role="button" aria-controls="editImageInput">
            <strong>${appLang('form.images_dropzone_title', 'DosyalarÄ± buraya sÃ¼rÃ¼kleyin veya tÄ±klayÄ±n')}</strong>
            <span>${appLang('form.images_dropzone_hint', 'JPG, PNG, WEBP - Ã‡oklu seÃ§ilebilir')}</span>
        </div>
        <span id="editFileName" class="file-name-label">${appLang('form.no_file_selected', 'Dosya seÃ§ilmedi')}</span>
    </div>
    <div class="modal-actions" style="margin-top:20px;">
        <button type="submit" class="btn-main">${appLang('actions.update', 'Gï¿½ncelle')}</button>
        <button type="button" class="btn-cancel" id="btnCancelEdit">${appLang('actions.cancel', 'Ä°ptal')}</button>
    </div>
</form>
`;
                document.getElementById('detailContent').innerHTML = editHtml;
                fetch('get_categories.php')
                  .then(res=>res.json())
                  .then(list=>{
                      var sel = document.getElementById('editCategory');
                      sel.innerHTML = `<option value="">${appLang('form.select_prompt', 'SeÃ§iniz')}</option>`;
                      list.forEach(function(cat){
                        sel.innerHTML += `<option value="${cat.id}"${cat.id==data.categoryid?' selected':''}>${cat.name}</option>`;
                      });
                      initCustomSelects(document.getElementById('detailContent'));
                  });
                var renderEditMedia = function() {
                    var previewList = document.getElementById('editMediaPreviewList');
                    var titleEl = document.getElementById('editMediaTitle');
                    var fileNameEl = document.getElementById('editFileName');
                    var coverImageInput = document.getElementById('editCoverImageId');
                    var coverNewInput = document.getElementById('editCoverNewUploadIndex');
                    var mediaOrderInput = document.getElementById('editMediaOrder');
                    if (!previewList || !titleEl || !fileNameEl) {
                        return;
                    }

                    previewList.innerHTML = '';

                    var activeExisting = existingMedia.filter(function(item) {
                        return removedExistingTokens.indexOf(item._token) === -1;
                    });
                    var hasManualExisting = activeExisting.some(function(item) {
                        return item.id > 0;
                    });
                    activeExisting = activeExisting.filter(function(item) {
                        return !(item.id <= 0 && (hasManualExisting || newFiles.length > 0));
                    });

                    var visibleTokens = activeExisting.map(function(item) {
                        return item._token;
                    }).concat(newFiles.map(function(file) {
                        return ensureUploadToken(file);
                    }));

                    editMediaOrder = editMediaOrder.filter(function(token) {
                        return visibleTokens.indexOf(token) !== -1;
                    });
                    visibleTokens.forEach(function(token) {
                        if (editMediaOrder.indexOf(token) === -1) {
                            editMediaOrder.push(token);
                        }
                    });

                    var totalCount = activeExisting.length + newFiles.length;
                    titleEl.textContent = appLang('form.selected_images', 'Seï¿½ili Gï¿½rseller') + ' (' + totalCount + ')';
                    fileNameEl.textContent = totalCount
                        ? totalCount + ' ' + appLang('form.image_select_count', 'gÃ¶rsel seÃ§ildi')
                        : appLang('form.no_file_selected', 'Dosya seÃ§ilmedi');

                    var activeExistingById = {};
                    activeExisting.forEach(function(item) {
                        if (item.id > 0) {
                            activeExistingById[item.id] = item;
                        }
                    });

                    var hasSelectedExistingCover = coverSelection.type === 'existing'
                        && coverSelection.existingId > 0
                        && !!activeExistingById[coverSelection.existingId];
                    var hasSelectedNewCover = coverSelection.type === 'new'
                        && newFiles.some(function(file) {
                            return ensureUploadToken(file) === coverSelection.newToken;
                        });

                    if (!hasSelectedExistingCover && !hasSelectedNewCover) {
                        if (activeExisting.length) {
                            coverSelection = {
                                type: 'existing',
                                existingId: Number(activeExisting[0].id || 0),
                                newToken: ''
                            };
                        } else if (newFiles.length) {
                            coverSelection = {
                                type: 'new',
                                existingId: 0,
                                newToken: ensureUploadToken(newFiles[0])
                            };
                        } else {
                            coverSelection = {
                                type: '',
                                existingId: 0,
                                newToken: ''
                            };
                        }
                    }

                    var coverNewIndex = -1;
                    if (coverSelection.type === 'new' && coverSelection.newToken) {
                        coverNewIndex = newFiles.findIndex(function(file) {
                            return ensureUploadToken(file) === coverSelection.newToken;
                        });
                    }

                    if (coverImageInput) {
                        coverImageInput.value = coverSelection.type === 'existing' && coverSelection.existingId > 0
                            ? String(coverSelection.existingId)
                            : '';
                    }
                    if (coverNewInput) {
                        coverNewInput.value = coverSelection.type === 'new' && coverNewIndex >= 0
                            ? String(coverNewIndex)
                            : '';
                    }
                    if (mediaOrderInput) {
                        mediaOrderInput.value = JSON.stringify(editMediaOrder.map(function(token) {
                            if (token.indexOf('existing:') === 0) {
                                return {
                                    type: 'existing',
                                    id: Number(token.split(':')[1] || 0)
                                };
                            }

                            var newIndex = newFiles.findIndex(function(file) {
                                return ensureUploadToken(file) === token;
                            });

                            return {
                                type: 'new',
                                index: newIndex
                            };
                        }).filter(function(item) {
                            return (item.type === 'existing' && item.id > 0) || (item.type === 'new' && item.index >= 0);
                        }));
                    }

                    var setCoverSelection = function(type, value) {
                        if (type === 'existing') {
                            coverSelection = {
                                type: 'existing',
                                existingId: value > 0 ? value : 0,
                                newToken: ''
                            };
                        } else if (type === 'new') {
                            coverSelection = {
                                type: 'new',
                                existingId: 0,
                                newToken: value || ''
                            };
                        } else {
                            coverSelection = {
                                type: '',
                                existingId: 0,
                                newToken: ''
                            };
                        }
                        renderEditMedia();
                    };

                    var existingByToken = {};
                    activeExisting.forEach(function(item) {
                        existingByToken[item._token] = item;
                    });
                    var newByToken = {};
                    newFiles.forEach(function(file) {
                        newByToken[ensureUploadToken(file)] = file;
                    });

                    editMediaOrder.forEach(function(token) {
                        if (existingByToken[token]) {
                            var item = existingByToken[token];
                            var card = document.createElement('div');
                            card.className = 'upload-preview-item';

                            var img = document.createElement('img');
                            img.src = item.src;
                            img.alt = item.filename || data.title || 'media';
                            card.appendChild(img);

                            if (coverSelection.type === 'existing' && item.id === coverSelection.existingId) {
                                var coverBadge = document.createElement('span');
                                coverBadge.className = 'upload-preview-badge';
                                coverBadge.textContent = appLang('form.cover_label', 'Kapak');
                                card.appendChild(coverBadge);
                            } else if (item.id > 0) {
                                var makeCoverBtn = document.createElement('button');
                                makeCoverBtn.type = 'button';
                                makeCoverBtn.className = 'upload-preview-cover-action';
                                makeCoverBtn.textContent = appLang('form.make_cover', 'Kapak yap');
                                protectPreviewActionButton(makeCoverBtn);
                                makeCoverBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    setCoverSelection('existing', Number(item.id || 0));
                                });
                                card.appendChild(makeCoverBtn);
                            }

                            var removeBtn = document.createElement('button');
                            removeBtn.type = 'button';
                            removeBtn.className = 'upload-preview-remove';
                            removeBtn.innerHTML = '<i data-lucide="x"></i>';
                            removeBtn.setAttribute('aria-label', appLang('actions.delete', 'Sil'));
                            protectPreviewActionButton(removeBtn);
                            removeBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                if (removedExistingTokens.indexOf(item._token) === -1) {
                                    removedExistingTokens.push(item._token);
                                }
                                if (item.id > 0 && removedMediaIds.indexOf(item.id) === -1) {
                                    removedMediaIds.push(item.id);
                                }
                                renderEditMedia();
                            });
                            card.appendChild(removeBtn);

                            bindPreviewDragEvents(card, token, function(fromToken, toToken) {
                                var fromIndex = editMediaOrder.indexOf(fromToken);
                                var toIndex = editMediaOrder.indexOf(toToken);
                                if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) {
                                    return;
                                }
                                var movedToken = editMediaOrder.splice(fromIndex, 1)[0];
                                editMediaOrder.splice(toIndex, 0, movedToken);
                                renderEditMedia();
                            });

                            previewList.appendChild(card);
                            return;
                        }

                        if (!newByToken[token]) {
                            return;
                        }

                        var file = newByToken[token];
                        var card = document.createElement('div');
                        card.className = 'upload-preview-item';

                        var img = document.createElement('img');
                        var objectUrl = URL.createObjectURL(file);
                        img.src = objectUrl;
                        img.alt = file.name || 'new-media';
                        img.onload = function() {
                            URL.revokeObjectURL(objectUrl);
                        };
                        card.appendChild(img);

                        if (coverSelection.type === 'new' && coverSelection.newToken === token) {
                            var coverBadge = document.createElement('span');
                            coverBadge.className = 'upload-preview-badge';
                            coverBadge.textContent = appLang('form.cover_label', 'Kapak');
                            card.appendChild(coverBadge);
                        } else {
                            var makeCoverBtn = document.createElement('button');
                            makeCoverBtn.type = 'button';
                            makeCoverBtn.className = 'upload-preview-cover-action';
                            makeCoverBtn.textContent = appLang('form.make_cover', 'Kapak yap');
                            protectPreviewActionButton(makeCoverBtn);
                            makeCoverBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                setCoverSelection('new', token);
                            });
                            card.appendChild(makeCoverBtn);
                        }

                        var newBadge = document.createElement('span');
                        newBadge.className = 'upload-preview-badge upload-preview-badge--secondary';
                        newBadge.textContent = appLang('actions.add', 'Yeni');
                        card.appendChild(newBadge);

                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'upload-preview-remove';
                        removeBtn.innerHTML = '<i data-lucide="x"></i>';
                        removeBtn.setAttribute('aria-label', appLang('actions.delete', 'Sil'));
                        protectPreviewActionButton(removeBtn);
                        removeBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            var removeIndex = newFiles.findIndex(function(entry) {
                                return ensureUploadToken(entry) === token;
                            });
                            if (removeIndex !== -1) {
                                newFiles.splice(removeIndex, 1);
                            }
                            renderEditMedia();
                        });
                        card.appendChild(removeBtn);

                        bindPreviewDragEvents(card, token, function(fromToken, toToken) {
                            var fromIndex = editMediaOrder.indexOf(fromToken);
                            var toIndex = editMediaOrder.indexOf(toToken);
                            if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) {
                                return;
                            }
                            var movedToken = editMediaOrder.splice(fromIndex, 1)[0];
                            editMediaOrder.splice(toIndex, 0, movedToken);
                            renderEditMedia();
                        });

                        previewList.appendChild(card);
                    });

                    renderLucideIcons();
                };

                var editInput = document.getElementById('editImageInput');
                var editDropzone = document.getElementById('editImageDropzone');
                var openEditPicker = function() {
                    if (editInput) {
                        editInput.value = '';
                        editInput.click();
                    }
                };
                if (editDropzone) {
                    editDropzone.onclick = function() {
                        openEditPicker();
                    };
                    editDropzone.onkeydown = function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            openEditPicker();
                        }
                    };
                    ['dragenter', 'dragover'].forEach(function(evtName) {
                        editDropzone.addEventListener(evtName, function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            editDropzone.classList.add('is-dragover');
                        });
                    });
                    ['dragleave', 'dragend', 'drop'].forEach(function(evtName) {
                        editDropzone.addEventListener(evtName, function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            editDropzone.classList.remove('is-dragover');
                        });
                    });
                    editDropzone.addEventListener('drop', function(e) {
                        if (e.dataTransfer && e.dataTransfer.files) {
                            appendUniqueImageFiles(newFiles, e.dataTransfer.files);
                            renderEditMedia();
                        }
                    });
                }
                if (editInput) {
                    editInput.addEventListener('change', function(e) {
                        appendUniqueImageFiles(newFiles, e.target.files);
                        renderEditMedia();
                        e.target.value = '';
                    });
                }
                renderEditMedia();
                document.getElementById('btnCancelEdit').onclick = function() {
                    document.getElementById('modalDetail').style.display = "none";
                };
                document.getElementById('btnCloseEditIcon').onclick = function() {
                    document.getElementById('btnCancelEdit').click();
                };
                document.getElementById('formEdit').onsubmit = function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var btn = this.querySelector('[type="submit"]');
                    btn.disabled = true;
                    removedMediaIds.forEach(function(mediaId) {
                        formData.append('removed_media_ids[]', mediaId);
                    });
                    newFiles.forEach(function(file) {
                        formData.append('new_images[]', file, file.name);
                    });
                    fetch('update.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(r=>r.text())
                    .then(res=>{
                        btn.disabled = false;
                        if(res.trim() == "OK") {
                            showToast(appLang('messages.update_success', 'KayÄ±t gÃ¼ncellendi!'), "update");
                            document.getElementById('modalDetail').style.display = "none";
                            reloadGallery();
                        } else {
                            showToast(appLang('messages.update_error_prefix', 'GÃ¼ncelleme baÅŸarÄ±sÄ±z:') + ' ' + res, "error");
                        }
                    })
                    .catch(()=>{
                        btn.disabled = false;
                        showToast(appLang('messages.generic_error', 'Bir hata oluÅŸtu!'), "error");
                    });
                };
            };

            document.getElementById('btnDeleteDetail').onclick = function() {
                showConfirmModal(appLang('modal.confirm_delete', 'Bu kaydÄ± silmek istediÄŸinize emin misiniz?'), function(confirmed) {
                    if (confirmed) {
                        fetch('delete.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'id=' + encodeURIComponent(data.id)
                        })
                        .then(r=>r.text())
                        .then(res=>{
                            if(res.trim() == 'OK') {
                                document.getElementById('modalDetail').style.display = "none";
                                reloadGallery();
                                showToast(appLang('messages.delete_success', 'KayÄ±t silindi!'), "delete");
                            } else {
                                showToast(appLang('messages.delete_error_prefix', 'Silinemedi:') + ' ' + res, "error");
                            }
                        });
                    }
                });
            };
        };
    });
}

bindGalleryItems();

ensureLightboxRoot();
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'detailImgPreview') {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
    }
}, true);



