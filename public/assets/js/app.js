(function () {
    'use strict';

    var panel = document.getElementById('converter');
    if (!panel) return;

    var toolId = panel.dataset.toolId;
    var csrf = panel.dataset.csrf;
    var maxBytes = parseInt(panel.dataset.maxBytes || '0', 10);
    var acceptExt = (panel.dataset.acceptExt || '')
        .split(',')
        .map(function (s) {
            return s.trim().toLowerCase();
        })
        .filter(Boolean);
    var acceptAttr = panel.dataset.acceptAttr || '';
    var apiUrl = panel.dataset.apiUrl || 'api/convert.php';
    var multi = panel.dataset.multi === '1';
    var minFiles = parseInt(panel.dataset.minFiles || '1', 10);
    var maxFiles = parseInt(panel.dataset.maxFiles || '1', 10);

    var dropzone = document.getElementById('dropzone');
    var fileInput = document.getElementById('file-input');
    var fileMeta = document.getElementById('file-meta');
    var fileNameEl = document.getElementById('file-name');
    var fileSizeEl = document.getElementById('file-size');
    var fileStatusEl = document.getElementById('file-status');
    var errorMsg = document.getElementById('error-msg');
    var btnConvert = document.getElementById('btn-convert');
    var btnDownload = document.getElementById('btn-download');
    var btnResultPage = document.getElementById('btn-result-page');
    var progressWrap = document.getElementById('progress-wrap');
    var progressBar = document.getElementById('progress-bar');
    var progressLabel = document.getElementById('progress-label');
    var qualityInput = document.getElementById('quality');
    var qualityOut = document.getElementById('quality-out');
    var maxSideSelect = document.getElementById('max_side');
    var splitEverySelect = document.getElementById('split_every');
    var compressLevelSelect = document.getElementById('compress_level');
    var watermarkTextInput = document.getElementById('watermark_text');
    var watermarkOpacityInput = document.getElementById('watermark_opacity');
    var watermarkOpacityOut = document.getElementById('watermark-opacity-out');

    var selectedFile = null;
    var selectedFiles = [];
    var progressTimer = null;

    if (acceptAttr && fileInput) {
        fileInput.setAttribute('accept', acceptAttr);
    }

    function formatBytes(n) {
        if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
        return Math.round(n / 1024) + ' KB';
    }

    function showError(msg) {
        errorMsg.textContent = msg;
        errorMsg.hidden = false;
    }

    function clearError() {
        errorMsg.hidden = true;
        errorMsg.textContent = '';
    }

    function extOf(name) {
        var i = name.lastIndexOf('.');
        return i >= 0 ? name.slice(i + 1).toLowerCase() : '';
    }

    function validateOne(file) {
        if (!file) return 'No file selected.';
        if (file.size > maxBytes) {
            return 'File is too large (max ' + formatBytes(maxBytes) + '): ' + file.name;
        }
        var ex = extOf(file.name);
        if (acceptExt.length && acceptExt.indexOf(ex) === -1) {
            return 'Wrong type for ' + file.name + ' (allowed: ' + acceptExt.join(', ') + ')';
        }
        return '';
    }

    function setSingleFile(file) {
        clearError();
        var err = validateOne(file);
        if (err) {
            showError(err);
            selectedFile = null;
            fileMeta.hidden = true;
            btnConvert.disabled = true;
            btnDownload.hidden = true;
            if (btnResultPage) btnResultPage.hidden = true;
            return;
        }
        selectedFile = file;
        fileNameEl.textContent = file.name;
        fileSizeEl.textContent = formatBytes(file.size);
        fileStatusEl.textContent = 'Ready';
        fileStatusEl.className = 'status';
        fileMeta.hidden = false;
        btnConvert.disabled = false;
        btnDownload.hidden = true;
        if (btnResultPage) btnResultPage.hidden = true;
    }

    function setMultiFiles(fileList) {
        clearError();
        var arr = [];
        if (fileList && fileList.length) {
            for (var i = 0; i < fileList.length; i++) {
                arr.push(fileList[i]);
            }
        }
        if (arr.length > maxFiles) {
            arr = arr.slice(0, maxFiles);
            showError('Only the first ' + maxFiles + ' files were kept (maximum allowed).');
        }
        var total = 0;
        var names = [];
        for (var j = 0; j < arr.length; j++) {
            var e = validateOne(arr[j]);
            if (e) {
                showError(e);
                selectedFiles = [];
                fileMeta.hidden = true;
                btnConvert.disabled = true;
                btnDownload.hidden = true;
                if (btnResultPage) btnResultPage.hidden = true;
                return;
            }
            total += arr[j].size;
            names.push(arr[j].name);
        }
        selectedFiles = arr;
        if (names.length === 0) {
            fileMeta.hidden = true;
            btnConvert.disabled = true;
            return;
        }
        fileNameEl.textContent =
            names.length + ' file' + (names.length === 1 ? '' : 's') + ': ' + names.slice(0, 3).join(', ') + (names.length > 3 ? '…' : '');
        fileSizeEl.textContent = formatBytes(total) + ' total';
        fileStatusEl.textContent = names.length >= minFiles ? 'Ready' : 'Need at least ' + minFiles + ' files';
        fileStatusEl.className = 'status';
        fileMeta.hidden = false;
        btnConvert.disabled = names.length < minFiles;
        btnDownload.hidden = true;
        if (btnResultPage) btnResultPage.hidden = true;
    }

    function stopProgress() {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    function startFakeProgress() {
        progressWrap.hidden = false;
        progressLabel.textContent = 'Processing…';
        var p = 5;
        progressBar.style.setProperty('--p', p + '%');
        stopProgress();
        progressTimer = setInterval(function () {
            if (p < 88) p += Math.random() * 12;
            if (p > 88) p = 88;
            progressBar.style.setProperty('--p', p + '%');
        }, 220);
    }

    function finishProgress(ok) {
        stopProgress();
        progressBar.style.setProperty('--p', ok ? '100%' : '0%');
        progressLabel.textContent = ok ? 'Done' : 'Idle';
        if (!ok) progressWrap.hidden = true;
    }

    dropzone.addEventListener('click', function () {
        fileInput.click();
    });

    dropzone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    fileInput.addEventListener('change', function () {
        if (multi) {
            setMultiFiles(fileInput.files);
        } else {
            var f = fileInput.files && fileInput.files[0];
            setSingleFile(f || null);
        }
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        var dt = e.dataTransfer && e.dataTransfer.files;
        if (!dt || !dt.length) return;
        fileInput.value = '';
        if (multi) {
            setMultiFiles(dt);
        } else {
            setSingleFile(dt[0]);
        }
    });

    if (qualityInput && qualityOut) {
        qualityInput.addEventListener('input', function () {
            qualityOut.textContent = qualityInput.value;
        });
    }

    if (watermarkOpacityInput && watermarkOpacityOut) {
        watermarkOpacityInput.addEventListener('input', function () {
            watermarkOpacityOut.textContent = watermarkOpacityInput.value;
        });
    }

    btnConvert.addEventListener('click', function () {
        if (toolId === 'watermark-pdf' && watermarkTextInput) {
            if (!watermarkTextInput.value.trim()) {
                showError('Enter watermark text.');
                return;
            }
        }

        if (multi) {
            if (selectedFiles.length < minFiles) return;
        } else {
            if (!selectedFile) return;
        }

        clearError();
        btnConvert.disabled = true;
        fileStatusEl.textContent = 'Converting…';
        fileStatusEl.className = 'status processing';
        startFakeProgress();

        var fd = new FormData();
        fd.append('csrf', csrf);
        fd.append('tool_id', toolId);
        if (multi) {
            for (var i = 0; i < selectedFiles.length; i++) {
                fd.append('files[]', selectedFiles[i]);
            }
        } else {
            fd.append('file', selectedFile);
        }
        if (qualityInput) fd.append('quality', qualityInput.value);
        if (maxSideSelect) fd.append('max_side', maxSideSelect.value);
        if (splitEverySelect) fd.append('split_every', splitEverySelect.value);
        if (compressLevelSelect) fd.append('compress_level', compressLevelSelect.value);
        if (watermarkTextInput) fd.append('watermark_text', watermarkTextInput.value);
        if (watermarkOpacityInput) fd.append('watermark_opacity', watermarkOpacityInput.value);

        fetch(apiUrl, {
            method: 'POST',
            body: fd,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var j = {};
                    try {
                        j = text ? JSON.parse(text) : {};
                    } catch (e) {
                        j = { error: 'Invalid server response.' };
                    }
                    return { ok: r.ok, body: j };
                });
            })
            .then(function (res) {
                finishProgress(!!(res.body && res.body.success));
                if (res.body && res.body.success && res.body.download_id) {
                    fileStatusEl.textContent = 'Complete';
                    fileStatusEl.className = 'status done';
                    var q = encodeURIComponent(res.body.download_id);
                    btnDownload.href = 'download.php?id=' + q;
                    btnDownload.hidden = false;
                    if (btnResultPage) {
                        btnResultPage.href = 'result.php?id=' + q;
                        btnResultPage.hidden = false;
                    }
                    btnConvert.disabled = false;
                } else {
                    var msg = (res.body && res.body.error) || 'Conversion failed.';
                    showError(msg);
                    fileStatusEl.textContent = 'Error';
                    fileStatusEl.className = 'status';
                    btnConvert.disabled = false;
                    progressWrap.hidden = true;
                }
            })
            .catch(function () {
                finishProgress(false);
                showError('Network error. Try again.');
                fileStatusEl.textContent = 'Error';
                fileStatusEl.className = 'status';
                btnConvert.disabled = false;
            });
    });
})();
