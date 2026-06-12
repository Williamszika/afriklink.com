// Afriklink — minimal progressive enhancement.
// Loaded with `defer`; no inline scripts (kept compatible with a strict CSP).
(function () {
    'use strict';

    // Make the CSRF token available to fetch()-based requests via X-CSRF-Token.
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : null;

    if (token && window.fetch) {
        var original = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var method = (init.method || 'GET').toUpperCase();
            if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
                init.headers = new Headers(init.headers || {});
                if (!init.headers.has('X-CSRF-Token')) {
                    init.headers.set('X-CSRF-Token', token);
                }
            }
            return original(input, init);
        };
    }
})();

// Escape hatch for the geolocation-locked country/indicatif: the discreet
// "Ce n'est pas mon pays ?" link re-enables both selects (and removes the hidden
// inputs so the user's own choice is what gets submitted). Once unlocked, the
// GPS refinement below stops touching the country.
(function () {
    'use strict';

    var unlockBtn = document.getElementById('geo-unlock');
    if (!unlockBtn) { return; }

    unlockBtn.addEventListener('click', function () {
        ['country_code', 'dial_country'].forEach(function (id) {
            var sel = document.getElementById(id);
            if (sel) {
                sel.disabled = false;
                sel.removeAttribute('tabindex');
                sel.removeAttribute('aria-disabled');
                sel.classList.remove('locked-field');
                sel.name = id;                 // re-enabled select submits itself
                sel.dataset.unlocked = '1';    // tells the GPS code to back off
            }
            var hidden = document.getElementById(id + '_value');
            if (hidden && hidden.parentNode) { hidden.parentNode.removeChild(hidden); }
        });
        if (unlockBtn.parentNode) { unlockBtn.parentNode.removeChild(unlockBtn); }
    });
})();

// Precise city detection on the registration form (target ≤100 m) — fully silent.
// The server pre-fills from IP (approximate); the browser's Geolocation API (the
// permission prompt IS the user's consent) refines until accuracy ≤100 m (12 s
// budget), then a free key-less reverse-geocoding API (BigDataCloud, in our CSP)
// turns coordinates into city + country.
// Quality gate: a fix coarser than 2 km is almost certainly an IP/WiFi fallback —
// the city is then left untouched. By design NOTHING is ever displayed: the
// fields just get corrected quietly and always stay editable.
(function () {
    'use strict';

    var city = document.getElementById('city');
    var country = document.getElementById('country_code');
    if (!city || !country || !('geolocation' in navigator) || !window.fetch) {
        return;
    }

    var GOOD_M = 100;      // requested precision
    var MAX_M = 2000;      // beyond this the fix is IP/WiFi-grade: don't trust it
    var REFINE_MS = 12000; // GPS refinement budget

    // Update a (possibly locked/disabled) <select> + its hidden submit input, so a
    // precise GPS fix corrects the locked country/flag/indicatif to the real one.
    function setLocked(id, iso) {
        var sel = document.getElementById(id);
        if (sel && sel.querySelector('option[value="' + iso + '"]')) { sel.value = iso; }
        var hidden = document.getElementById(id + '_value');
        if (hidden) { hidden.value = iso; }
    }

    function applyCountry(iso) {
        iso = (iso || '').toUpperCase();
        if (!iso) { return; }
        // The user chose their country manually after unlocking — respect it.
        if (country.dataset.unlocked === '1') { return; }
        setLocked('country_code', iso);
        setLocked('dial_country', iso);
    }

    function conclude(best) {
        if (!best) { return; } // silent failure
        var acc = Math.round(best.coords.accuracy);
        if (acc > MAX_M) { return; } // IP/WiFi-grade fix: leave the field as-is
        var url = 'https://api.bigdatacloud.net/data/reverse-geocode-client'
            + '?latitude=' + encodeURIComponent(best.coords.latitude)
            + '&longitude=' + encodeURIComponent(best.coords.longitude)
            + '&localityLanguage=fr';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var name = d.city || d.locality || '';
                if (name) { city.value = name; }
                applyCountry(d.countryCode);
            })
            .catch(function () { /* silent */ });
    }

    var best = null;
    var watchId = null;
    var pollId = null;
    var done = false;

    function consider(p) {
        if (!best || p.coords.accuracy < best.coords.accuracy) { best = p; }
        if (p.coords.accuracy <= GOOD_M) { finish(); }
    }

    function finish() {
        if (done) { return; }
        done = true;
        if (watchId !== null) { navigator.geolocation.clearWatch(watchId); }
        if (pollId !== null) { clearInterval(pollId); }
        conclude(best);
    }

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            best = pos;
            if (pos.coords.accuracy <= GOOD_M) { finish(); return; }
            // First fix too coarse — refine until ≤100 m or the budget ends.
            // watchPosition AND an active 2.5 s re-poll: some browsers/WebViews
            // never push watch updates, so polling is the reliable fallback.
            watchId = navigator.geolocation.watchPosition(
                consider,
                // GPS often emits transient POSITION_UNAVAILABLE/TIMEOUT right
                // before locking — only a permission denial is final here; the
                // deadline below bounds everything else.
                function (e) { if (e && e.code === 1) { finish(); } },
                { enableHighAccuracy: true, maximumAge: 0 }
            );
            pollId = setInterval(function () {
                navigator.geolocation.getCurrentPosition(
                    consider,
                    function () {},
                    { enableHighAccuracy: true, maximumAge: 0, timeout: 2000 }
                );
            }, 2500);
            setTimeout(finish, REFINE_MS);
        },
        function () { /* denied or unavailable — silent, IP estimate stays editable */ },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
})();

/* ---- Photo de profil : réduction côté navigateur avant envoi ----
   Les photos de téléphone font souvent 3-8 Mo ; on les ramène à ~200 Ko
   (max 1024 px, JPEG) pour passer la limite d'upload du serveur et
   économiser la connexion de l'utilisateur. En cas d'échec, le serveur
   garde ses propres garde-fous. */
(function () {
    var input = document.getElementById('avatar-input');
    if (!input || typeof window.createImageBitmap !== 'function' || typeof DataTransfer === 'undefined') {
        return;
    }
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || file.size < 600 * 1024) { return; } // déjà léger
        createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
            var scale = Math.min(1, 1024 / Math.max(bmp.width, bmp.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(bmp.width * scale));
            canvas.height = Math.max(1, Math.round(bmp.height * scale));
            canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(function (blob) {
                if (!blob || blob.size >= file.size) { return; }
                var dt = new DataTransfer();
                dt.items.add(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
                input.files = dt.files;
            }, 'image/jpeg', 0.85);
        }).catch(function () { /* le serveur validera l'original */ });
    });
})();

/* ---- Confirmation générique (CSP interdit les onclick inline) ---- */
document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
        ev.stopPropagation();
    }
}, true);

/* ---- Page annonce : clic sur une vignette = grande photo ---- */
document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-gallery-full]') : null;
    var main = document.getElementById('listing-main-photo');
    if (btn && main) { main.src = btn.getAttribute('data-gallery-full'); }
});

/* ---- Dépôt d'annonce : envoi direct navigateur → Cloudinary ----
   1. demande une signature au serveur (/api/media/sign) ;
   2. envoie le fichier directement à Cloudinary (la limite Vercel ne s'applique pas) ;
   3. range les identifiants retournés dans les champs cachés du formulaire.
   Les photos lourdes sont réduites avant envoi ; la durée de la vidéo est
   contrôlée ici ET re-vérifiée par le serveur après envoi. */
(function () {
    var form = document.getElementById('listing-form');
    if (!form) { return; }

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var photosJson = document.getElementById('photos_json');
    var videoIdInput = document.getElementById('video_public_id');
    var photoInput = document.getElementById('photo-input');
    var photoCamera = document.getElementById('photo-camera');
    var videoInput = document.getElementById('video-input');
    var videoCamera = document.getElementById('video-camera');
    var photoZone = document.getElementById('photo-zone');
    var videoZone = document.getElementById('video-zone');
    var photoPreviews = document.getElementById('photo-previews');
    var videoPreview = document.getElementById('video-preview');
    var photoError = document.getElementById('photo-error');
    var videoError = document.getElementById('video-error');
    var submitBtn = document.getElementById('listing-submit');
    var statusLine = document.getElementById('upload-status');
    var MAX_PHOTOS = 5;
    var MAX_VIDEO_S = 60;
    var photos = []; // {publicId, url}
    var pending = 0;
    var inflightPhotos = 0; // envois photo en cours (pour la limite de 5)

    function setError(node, msg) { node.textContent = msg || ''; node.hidden = !msg; }
    function syncState() {
        photosJson.value = JSON.stringify(photos.map(function (p) { return p.publicId; }));
        var busy = pending > 0;
        submitBtn.disabled = busy;
        statusLine.hidden = !busy;
        if (busy) { statusLine.textContent = statusLine.dataset.msg || 'Envoi en cours… ' + pending + ' fichier(s)'; }
    }

    function sign(resourceType) {
        var body = new FormData();
        body.append('resource_type', resourceType);
        return fetch('/api/media/sign', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            body: body
        }).then(function (r) {
            if (!r.ok) { throw new Error('sign ' + r.status); }
            return r.json();
        });
    }

    function uploadToCloudinary(file, params, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/' + params.resource_type + '/upload');
            xhr.upload.onprogress = function (e) {
                if (e.lengthComputable && onProgress) { onProgress(Math.round(e.loaded * 100 / e.total)); }
            };
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); }
                } else { reject(new Error('upload ' + xhr.status)); }
            };
            xhr.onerror = function () { reject(new Error('réseau')); };
            var fd = new FormData();
            fd.append('file', file);
            fd.append('api_key', params.api_key);
            fd.append('timestamp', params.timestamp);
            fd.append('folder', params.folder);
            fd.append('signature', params.signature);
            xhr.send(fd);
        });
    }

    /* réduit une photo à 1600 px max (JPEG) pour un envoi rapide */
    function shrinkImage(file) {
        if (file.size < 500 * 1024 || typeof window.createImageBitmap !== 'function') {
            return Promise.resolve(file);
        }
        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
            var scale = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
            if (scale === 1) { return file; }
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bmp.width * scale);
            canvas.height = Math.round(bmp.height * scale);
            canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    resolve(blob && blob.size < file.size ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file);
                }, 'image/jpeg', 0.85);
            });
        }).catch(function () { return file; });
    }

    function addPhotoPreview(publicId, objectUrl) {
        var wrap = document.createElement('div');
        wrap.className = 'preview';
        var img = document.createElement('img');
        img.src = objectUrl;
        img.alt = '';
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'preview-remove';
        del.textContent = '✕';
        del.addEventListener('click', function () {
            photos = photos.filter(function (p) { return p.publicId !== publicId; });
            wrap.remove();
            syncState();
        });
        wrap.appendChild(img);
        wrap.appendChild(del);
        photoPreviews.appendChild(wrap);
    }

    /* Accepte des photos venant de N'IMPORTE QUELLE source :
       sélecteur de fichiers, appareil photo, glisser-déposer, collage. */
    function addPhotoFiles(fileList) {
        setError(photoError, '');
        var files = Array.prototype.filter.call(fileList || [], function (f) {
            return f && f.type.indexOf('image/') === 0;
        });
        if (!files.length) { return; }
        var room = MAX_PHOTOS - photos.length - inflightPhotos;
        if (files.length > room) {
            setError(photoError, photoError.dataset.max || ('Maximum ' + MAX_PHOTOS + ' photos.'));
            files = files.slice(0, Math.max(0, room));
        }
        files.forEach(function (original) {
            pending++; inflightPhotos++; syncState();
            shrinkImage(original).then(function (file) {
                return sign('image').then(function (params) { return uploadToCloudinary(file, params); });
            }).then(function (res) {
                photos.push({ publicId: res.public_id });
                addPhotoPreview(res.public_id, URL.createObjectURL(original));
            }).catch(function () {
                setError(photoError, photoError.dataset.fail || "Échec de l'envoi d'une photo — réessaie.");
            }).finally(function () { pending--; inflightPhotos--; syncState(); });
        });
    }

    [photoInput, photoCamera].forEach(function (input) {
        if (!input) { return; }
        input.addEventListener('change', function () {
            addPhotoFiles(input.files);
            input.value = '';
        });
    });

    function videoDuration(file) {
        return new Promise(function (resolve) {
            var v = document.createElement('video');
            v.preload = 'metadata';
            v.onloadedmetadata = function () { var d = v.duration; URL.revokeObjectURL(v.src); resolve(d); };
            v.onerror = function () { URL.revokeObjectURL(v.src); resolve(NaN); };
            v.src = URL.createObjectURL(file);
        });
    }

    /* Accepte une vidéo venant du sélecteur, de la caméra ou d'un dépôt. */
    function setVideoFile(file) {
        setError(videoError, '');
        if (!file) { return; }
        if (file.size > 100 * 1024 * 1024) {
            setError(videoError, videoError.dataset.big || 'Vidéo trop lourde (100 Mo max).');
            return;
        }
        videoDuration(file).then(function (d) {
                if (isNaN(d) || d > MAX_VIDEO_S + 1) {
                    setError(videoError, videoError.dataset.long || ('Vidéo trop longue : ' + Math.round(d) + ' s (max ' + MAX_VIDEO_S + ' s).'));
                    return;
                }
                pending++; syncState();
                sign('video').then(function (params) {
                    return uploadToCloudinary(file, params, function (pct) {
                        statusLine.dataset.msg = 'Vidéo : ' + pct + ' %';
                        statusLine.textContent = statusLine.dataset.msg;
                    });
                }).then(function (res) {
                    videoIdInput.value = res.public_id;
                    videoPreview.innerHTML = '';
                    var wrap = document.createElement('div');
                    wrap.className = 'preview preview-video';
                    var v = document.createElement('video');
                    v.controls = true;
                    v.src = URL.createObjectURL(file);
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'preview-remove';
                    del.textContent = '✕';
                    del.addEventListener('click', function () {
                        videoIdInput.value = '';
                        videoPreview.innerHTML = '';
                    });
                    wrap.appendChild(v);
                    wrap.appendChild(del);
                    videoPreview.appendChild(wrap);
                }).catch(function () {
                    setError(videoError, videoError.dataset.fail || "Échec de l'envoi de la vidéo — réessaie.");
                }).finally(function () {
                    delete statusLine.dataset.msg;
                    pending--; syncState();
                });
        });
    }

    [videoInput, videoCamera].forEach(function (input) {
        if (!input) { return; }
        input.addEventListener('change', function () {
            setVideoFile(input.files && input.files[0]);
            input.value = '';
        });
    });

    /* Route un lot de fichiers (déposés ou collés) : images → photos,
       première vidéo → vidéo de l'annonce. */
    function handleIncomingFiles(fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        if (!files.length) { return; }
        addPhotoFiles(files);
        var vid = files.filter(function (f) { return f.type.indexOf('video/') === 0; })[0];
        if (vid) { setVideoFile(vid); }
    }

    /* Glisser-déposer sur les deux zones */
    [photoZone, videoZone].forEach(function (zone) {
        if (!zone) { return; }
        ['dragover', 'dragenter'].forEach(function (evName) {
            zone.addEventListener(evName, function (ev) {
                ev.preventDefault();
                zone.classList.add('dragover');
            });
        });
        ['dragleave', 'dragend'].forEach(function (evName) {
            zone.addEventListener(evName, function () { zone.classList.remove('dragover'); });
        });
        zone.addEventListener('drop', function (ev) {
            ev.preventDefault();
            zone.classList.remove('dragover');
            handleIncomingFiles(ev.dataTransfer && ev.dataTransfer.files);
        });
    });

    /* Collage (Ctrl+V / Cmd+V) d'images ou de vidéos n'importe où sur la page.
       Le collage de texte dans les champs reste intact (files vide). */
    document.addEventListener('paste', function (ev) {
        var files = ev.clipboardData && ev.clipboardData.files;
        if (files && files.length) { handleIncomingFiles(files); }
    });

    /* ---- Caméra intégrée (getUserMedia) ----------------------------
       Clic sur « Prendre une photo » / « Filmer maintenant » : le navigateur
       demande la permission caméra (+ micro pour la vidéo), un aperçu en
       direct s'affiche, puis la capture rejoint le même tuyau d'envoi que
       les fichiers. Si la caméra est refusée/indisponible, on retombe sur
       l'input natif (qui ouvre l'app caméra sur la plupart des téléphones). */
    var camTxt = {
        capture: form.dataset.camCapture || 'Capturer la photo',
        done:    form.dataset.camDone || 'Terminer',
        start:   form.dataset.camStart || 'Démarrer la vidéo',
        stop:    form.dataset.camStop || 'Arrêter',
        flip:    form.dataset.camFlip || 'Changer de caméra',
        added:   form.dataset.camAdded || 'Photo ajoutée ✓',
        error:   form.dataset.camError || 'Caméra inaccessible — utilise « Choisir un fichier ».',
        maxS:    form.dataset.camMax || '60 s maximum'
    };

    function cameraSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    function openCamera(mode) { // 'photo' | 'video'
        var facing = 'environment';
        var stream = null;
        var recorder = null;
        var chunks = [];
        var timerId = null;

        var overlay = document.createElement('div');
        overlay.className = 'cam-overlay';
        overlay.innerHTML =
            '<div class="cam-box">' +
            '  <video class="cam-preview" autoplay playsinline muted></video>' +
            '  <p class="cam-status">' + (mode === 'video' ? camTxt.maxS : '') + '</p>' +
            '  <div class="cam-controls">' +
            '    <button type="button" class="btn btn-ghost btn-sm cam-flip">🔄 ' + camTxt.flip + '</button>' +
            '    <button type="button" class="btn btn-primary cam-main">' +
                     (mode === 'photo' ? '📸 ' + camTxt.capture : '⏺ ' + camTxt.start) + '</button>' +
            '    <button type="button" class="btn btn-ghost btn-sm cam-close">✕ ' + camTxt.done + '</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(overlay);

        var vid = overlay.querySelector('.cam-preview');
        var status = overlay.querySelector('.cam-status');
        var btnMain = overlay.querySelector('.cam-main');
        var btnFlip = overlay.querySelector('.cam-flip');
        var btnClose = overlay.querySelector('.cam-close');

        function stopTracks() {
            if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        }
        function close() {
            if (timerId) { clearInterval(timerId); timerId = null; }
            if (recorder && recorder.state === 'recording') { try { recorder.stop(); } catch (e) {} }
            stopTracks();
            overlay.remove();
        }
        function fallbackNative() {
            close();
            setError(mode === 'photo' ? photoError : videoError, camTxt.error);
            var native = mode === 'photo' ? photoCamera : videoCamera;
            if (native) { try { native.click(); } catch (e) {} }
        }
        function start() {
            stopTracks();
            var constraints = { video: { facingMode: facing }, audio: mode === 'video' };
            navigator.mediaDevices.getUserMedia(constraints).then(function (s) {
                stream = s;
                vid.srcObject = s;
                vid.play().catch(function () {});
            }).catch(fallbackNative);
        }

        btnFlip.addEventListener('click', function () {
            if (recorder && recorder.state === 'recording') { return; }
            facing = facing === 'environment' ? 'user' : 'environment';
            start();
        });
        btnClose.addEventListener('click', close);

        if (mode === 'photo') {
            btnMain.addEventListener('click', function () {
                if (!stream) { return; }
                if (photos.length + inflightPhotos >= MAX_PHOTOS) {
                    setError(photoError, photoError.dataset.max || ('Maximum ' + MAX_PHOTOS + ' photos.'));
                    close();
                    return;
                }
                var canvas = document.createElement('canvas');
                canvas.width = vid.videoWidth || 1280;
                canvas.height = vid.videoHeight || 720;
                canvas.getContext('2d').drawImage(vid, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function (blob) {
                    if (!blob) { return; }
                    addPhotoFiles([new File([blob], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg' })]);
                    status.textContent = camTxt.added + ' (' + (photos.length + inflightPhotos) + '/' + MAX_PHOTOS + ')';
                }, 'image/jpeg', 0.9);
            });
        } else {
            btnMain.addEventListener('click', function () {
                if (!stream) { return; }
                if (recorder && recorder.state === 'recording') {
                    recorder.stop();
                    return;
                }
                var mime = ['video/mp4', 'video/webm;codecs=vp8,opus', 'video/webm'].filter(function (m) {
                    return window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(m);
                })[0];
                if (!window.MediaRecorder) { fallbackNative(); return; }
                chunks = [];
                try {
                    recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
                } catch (e) { fallbackNative(); return; }
                recorder.ondataavailable = function (ev) { if (ev.data && ev.data.size) { chunks.push(ev.data); } };
                recorder.onstop = function () {
                    if (timerId) { clearInterval(timerId); timerId = null; }
                    var type = (recorder.mimeType || mime || 'video/webm').split(';')[0];
                    var ext = type.indexOf('mp4') >= 0 ? 'mp4' : 'webm';
                    var file = new File([new Blob(chunks, { type: type })], 'camera-video.' + ext, { type: type });
                    close();
                    setVideoFile(file);
                };
                recorder.start();
                btnMain.textContent = '⏹ ' + camTxt.stop;
                btnMain.classList.add('recording');
                var startedAt = Date.now();
                timerId = setInterval(function () {
                    var s = Math.floor((Date.now() - startedAt) / 1000);
                    status.textContent = s + ' s / ' + MAX_VIDEO_S + ' s';
                    if (s >= MAX_VIDEO_S && recorder.state === 'recording') { recorder.stop(); }
                }, 250);
            });
        }

        start();
    }

    var openPhotoCam = document.getElementById('open-photo-camera');
    var openVideoCam = document.getElementById('open-video-camera');
    if (openPhotoCam) {
        openPhotoCam.addEventListener('click', function () {
            if (cameraSupported()) { openCamera('photo'); } else if (photoCamera) { photoCamera.click(); }
        });
    }
    if (openVideoCam) {
        openVideoCam.addEventListener('click', function () {
            if (cameraSupported()) { openCamera('video'); } else if (videoCamera) { videoCamera.click(); }
        });
    }

    form.addEventListener('submit', function (ev) {
        if (pending > 0) { ev.preventDefault(); return; }
        if (photos.length === 0) {
            ev.preventDefault();
            setError(photoError, photoError.dataset.need || 'Ajoute au moins une photo.');
            photoError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();

/* ---- Vérification KYC : envoi PRIVÉ des pièces vers Cloudinary ----
   Chaque champ fichier d'un niveau est envoyé en « authenticated » (jamais
   d'URL publique). On range {slot, public_id, version, format} dans le champ
   caché docs_json du formulaire du niveau. */
(function () {
    var forms = document.querySelectorAll('.kyc-form');
    if (!forms.length) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function signKyc() {
        return fetch('/api/kyc/sign', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: new FormData() })
            .then(function (r) { if (!r.ok) { throw new Error('sign ' + r.status); } return r.json(); });
    }

    function uploadAuth(file, params) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/image/upload');
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); }
                } else { reject(new Error('upload ' + xhr.status)); }
            };
            xhr.onerror = function () { reject(new Error('réseau')); };
            var fd = new FormData();
            fd.append('file', file);
            fd.append('api_key', params.api_key);
            fd.append('timestamp', params.timestamp);
            fd.append('folder', params.folder);
            fd.append('type', params.type);
            fd.append('signature', params.signature);
            xhr.send(fd);
        });
    }

    Array.prototype.forEach.call(forms, function (form) {
        var hidden = form.querySelector('.kyc-docs');
        var submit = form.querySelector('button[type=submit]');
        var docs = {};   // slot -> {public_id, version, format}
        var pending = 0;

        function sync() {
            hidden.value = JSON.stringify(Object.keys(docs).map(function (slot) {
                return {
                    slot: slot,
                    public_id: docs[slot].public_id,
                    version: docs[slot].version,
                    format: docs[slot].format
                };
            }));
            submit.disabled = pending > 0;
        }

        Array.prototype.forEach.call(form.querySelectorAll('.kyc-slot'), function (wrap) {
            var slot = wrap.getAttribute('data-slot');
            var input = wrap.querySelector('.kyc-input');
            var state = wrap.querySelector('.kyc-slot-state');
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) { return; }
                if (file.size > 10 * 1024 * 1024) { state.textContent = '⚠️ 10 Mo max'; return; }
                pending++; sync();
                state.textContent = form.getAttribute('data-uploading') || '…';
                signKyc().then(function (params) { return uploadAuth(file, params); })
                    .then(function (res) {
                        docs[slot] = { public_id: res.public_id, version: res.version, format: res.format };
                        state.textContent = '✅';
                    })
                    .catch(function () { state.textContent = '❌'; })
                    .finally(function () { pending--; sync(); });
            });
        });

        form.addEventListener('submit', function (ev) {
            if (pending > 0) { ev.preventDefault(); return; }
            var missing = false;
            Array.prototype.forEach.call(form.querySelectorAll('.kyc-slot'), function (wrap) {
                if (wrap.getAttribute('data-required') === '1' && !docs[wrap.getAttribute('data-slot')]) {
                    missing = true;
                    wrap.querySelector('.kyc-slot-state').textContent = '⚠️';
                }
            });
            if (missing) { ev.preventDefault(); }
        });
    });
})();

/* ---- Création de boutique : slug en direct + logo/bannière ---- */
(function () {
    var form = document.getElementById('shop-form');
    if (!form) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // Disponibilité du slug (afriklink.com/boutique/<slug>)
    var slug = document.getElementById('shop-slug');
    var status = document.getElementById('slug-status');
    if (slug && status) {
        var url = form.getAttribute('data-slug-url');
        var timer;
        var check = function () {
            var v = slug.value.trim();
            if (v.length < 3) {
                status.textContent = form.getAttribute('data-slug-short') || '';
                status.className = 'hint';
                return;
            }
            fetch(url + '?slug=' + encodeURIComponent(v))
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (typeof d.slug === 'string') { slug.value = d.slug; }
                    if (d.available) {
                        status.textContent = '✓ ' + (form.getAttribute('data-slug-ok') || '');
                        status.className = 'hint slug-ok';
                    } else {
                        status.textContent = '✗ ' + (form.getAttribute('data-slug-taken') || '');
                        status.className = 'hint slug-taken';
                    }
                })
                .catch(function () {});
        };
        slug.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 450); });
        if (slug.value) { check(); }
    }

    // Envoi direct logo/bannière → Cloudinary (image publique)
    function sign() {
        var fd = new FormData();
        fd.append('resource_type', 'image');
        return fetch('/api/media/sign', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(function (r) { if (!r.ok) { throw new Error('sign'); } return r.json(); });
    }
    function uploadImg(file, params) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/image/upload');
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) { try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); } }
                else { reject(new Error('upload ' + xhr.status)); }
            };
            xhr.onerror = function () { reject(new Error('réseau')); };
            var fd = new FormData();
            fd.append('file', file);
            fd.append('api_key', params.api_key);
            fd.append('timestamp', params.timestamp);
            fd.append('folder', params.folder);
            fd.append('signature', params.signature);
            xhr.send(fd);
        });
    }
    function wire(inputId, hiddenId, stateId) {
        var input = document.getElementById(inputId);
        var hidden = document.getElementById(hiddenId);
        var state = document.getElementById(stateId);
        if (!input) { return; }
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { return; }
            if (f.size > 10 * 1024 * 1024) { state.textContent = '⚠️ 10 Mo max'; return; }
            state.textContent = form.getAttribute('data-uploading') || '…';
            sign().then(function (p) { return uploadImg(f, p); })
                .then(function (res) { hidden.value = res.public_id; state.textContent = '✅'; })
                .catch(function () { state.textContent = '❌'; });
        });
    }
    wire('logo-input', 'logo-public-id', 'logo-state');

    /* Bannière = diaporama : plusieurs images (jusqu'à data-max), aperçus
       réordonnables par suppression, identifiants dans #banners-json. */
    (function () {
        var zone = document.getElementById('banner-zone');
        var input = document.getElementById('banner-input');
        var hidden = document.getElementById('banners-json');
        var previews = document.getElementById('banner-previews');
        var state = document.getElementById('banner-state');
        if (!zone || !input || !hidden || !previews) { return; }
        var max = parseInt(zone.getAttribute('data-max'), 10) || 10;
        var ids = [];
        var pending = 0;

        function sync() {
            hidden.value = JSON.stringify(ids);
            if (state) { state.textContent = pending > 0 ? (form.getAttribute('data-uploading') || '…') : (ids.length ? ids.length + ' ✅' : ''); }
        }
        function wireRemove(el, id) {
            var btn = el.querySelector('.preview-remove');
            if (btn) { btn.addEventListener('click', function () { ids = ids.filter(function (x) { return x !== id; }); el.remove(); sync(); }); }
        }
        function addPreview(id, src) {
            var wrap = document.createElement('div'); wrap.className = 'preview'; wrap.setAttribute('data-public-id', id);
            var img = document.createElement('img'); img.src = src; img.alt = '';
            var del = document.createElement('button'); del.type = 'button'; del.className = 'preview-remove'; del.textContent = '✕';
            wrap.appendChild(img); wrap.appendChild(del); previews.appendChild(wrap); wireRemove(wrap, id);
        }
        // graine : aperçus déjà rendus côté serveur
        Array.prototype.forEach.call(previews.querySelectorAll('.preview[data-public-id]'), function (el) {
            var id = el.getAttribute('data-public-id'); ids.push(id); wireRemove(el, id);
        });
        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files || []); input.value = '';
            var room = max - ids.length - pending;
            files = files.slice(0, Math.max(0, room));
            files.forEach(function (file) {
                if (file.size > 10 * 1024 * 1024) { return; }
                pending++; sync();
                sign().then(function (p) { return uploadImg(file, p); })
                    .then(function (res) { ids.push(res.public_id); addPreview(res.public_id, URL.createObjectURL(file)); })
                    .catch(function () {})
                    .finally(function () { pending--; sync(); });
            });
        });
        form.addEventListener('submit', function (ev) { if (pending > 0) { ev.preventDefault(); } });
    })();
})();

/* ---- Diaporama de bannière (vitrine publique) : fondu automatique ---- */
(function () {
    var shows = document.querySelectorAll('[data-banner-slideshow]');
    if (!shows.length) { return; }
    Array.prototype.forEach.call(shows, function (box) {
        var imgs = box.querySelectorAll('img');
        var dots = box.querySelectorAll('.shop-banner-dots i');
        if (imgs.length < 2) { return; }
        var i = 0;
        setInterval(function () {
            imgs[i].classList.remove('is-active');
            if (dots[i]) { dots[i].classList.remove('is-active'); }
            i = (i + 1) % imgs.length;
            imgs[i].classList.add('is-active');
            if (dots[i]) { dots[i].classList.add('is-active'); }
        }, 4000);
    });
})();

/* ---- Création de boutique : physique vs 100 % en ligne ----
   Physique → champ adresse affiché (obligatoire), retrait en main propre
   disponible. En ligne → pas d'adresse, retrait masqué/décoché (tout en
   livraison). Le serveur impose les mêmes règles. */
(function () {
    var form = document.getElementById('shop-form');
    if (!form) { return; }
    var radios = form.querySelectorAll('input[name=shop_type]');
    if (!radios.length) { return; }
    var addrWrap = document.getElementById('shop-address-wrap');
    var addr = document.getElementById('shop-address');
    var pickupPill = form.querySelector('[data-pickup-pill]');
    var onlineHint = document.getElementById('online-methods-hint');

    function apply() {
        var type = (form.querySelector('input[name=shop_type]:checked') || {}).value;
        var physical = type === 'physical';
        if (addrWrap) { addrWrap.hidden = !physical; }
        if (addr) { addr.required = physical; if (!physical) { addr.value = addr.value; } }
        if (pickupPill) {
            pickupPill.hidden = !physical;
            if (!physical) {
                var cb = pickupPill.querySelector('input');
                if (cb) { cb.checked = false; }
            }
        }
        if (onlineHint) { onlineHint.hidden = physical || !type; }
    }
    Array.prototype.forEach.call(radios, function (r) { r.addEventListener('change', apply); });
    apply();
})();

/* ---- Catalogue : formulaire produit (photos + vidéo 2 min) ---- */
(function () {
    var form = document.getElementById('product-form');
    if (!form) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var isEdit = /\/modifier(\?|$)/.test(form.getAttribute('action') || '');
    var maxPhotos = parseInt(form.getAttribute('data-max'), 10) || 6;

    function sign(type) {
        var fd = new FormData(); fd.append('resource_type', type);
        return fetch('/api/media/sign', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(function (r) { if (!r.ok) { throw new Error('sign'); } return r.json(); });
    }
    function upload(file, params, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/' + params.resource_type + '/upload');
            if (onProgress) { xhr.upload.onprogress = function (e) { if (e.lengthComputable) { onProgress(Math.round(e.loaded * 100 / e.total)); } }; }
            xhr.onload = function () { if (xhr.status >= 200 && xhr.status < 300) { try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); } } else { reject(new Error('up')); } };
            xhr.onerror = function () { reject(new Error('net')); };
            var fd = new FormData();
            fd.append('file', file); fd.append('api_key', params.api_key); fd.append('timestamp', params.timestamp);
            fd.append('folder', params.folder); fd.append('signature', params.signature);
            xhr.send(fd);
        });
    }

    /* Photos */
    var photosJson = document.getElementById('product-photos-json');
    var touched = document.getElementById('product-photos-touched');
    var previews = document.getElementById('product-previews');
    var photoInput = document.getElementById('product-photo-input');
    var photoErr = document.getElementById('product-photo-error');
    var submit = document.getElementById('product-submit');
    var photos = [];
    var pending = 0;

    // graine : photos existantes (édition)
    Array.prototype.forEach.call(previews.querySelectorAll('.preview[data-public-id]'), function (el) {
        var id = el.getAttribute('data-public-id');
        photos.push(id);
        wireRemove(el, id);
    });
    function syncPhotos(markTouched) {
        photosJson.value = JSON.stringify(photos);
        if (markTouched) { touched.value = '1'; }
        submit.disabled = pending > 0;
    }
    function wireRemove(el, id) {
        var btn = el.querySelector('.preview-remove');
        if (btn) { btn.addEventListener('click', function () { photos = photos.filter(function (x) { return x !== id; }); el.remove(); syncPhotos(true); }); }
    }
    function addPreview(id, src) {
        var wrap = document.createElement('div'); wrap.className = 'preview'; wrap.setAttribute('data-public-id', id);
        var img = document.createElement('img'); img.src = src; img.alt = '';
        var del = document.createElement('button'); del.type = 'button'; del.className = 'preview-remove'; del.textContent = '✕';
        wrap.appendChild(img); wrap.appendChild(del); previews.appendChild(wrap); wireRemove(wrap, id);
    }
    photoInput.addEventListener('change', function () {
        photoErr.hidden = true;
        var files = Array.prototype.slice.call(photoInput.files || []);
        photoInput.value = '';
        var room = maxPhotos - photos.length - pending;
        if (files.length > room) { photoErr.textContent = 'Maximum ' + maxPhotos + ' photos.'; photoErr.hidden = false; files = files.slice(0, Math.max(0, room)); }
        files.forEach(function (file) {
            pending++; syncPhotos(true);
            sign('image').then(function (p) { return upload(file, p); }).then(function (res) {
                photos.push(res.public_id); addPreview(res.public_id, URL.createObjectURL(file));
            }).catch(function () { photoErr.textContent = '❌'; photoErr.hidden = false; }).finally(function () { pending--; syncPhotos(true); });
        });
    });

    /* Vidéo (2 min max) */
    var vZone = document.getElementById('product-video-zone');
    var vInput = document.getElementById('product-video-input');
    var vId = document.getElementById('product-video-id');
    var vPrev = document.getElementById('product-video-preview');
    var vErr = document.getElementById('product-video-error');
    var maxSec = parseInt(vZone.getAttribute('data-max-seconds'), 10) || 120;
    function vClear() { vId.value = ''; vPrev.innerHTML = ''; }
    var existingRemove = document.getElementById('product-video-remove');
    if (existingRemove) { existingRemove.addEventListener('click', vClear); }
    function videoDuration(file) {
        return new Promise(function (resolve) {
            var v = document.createElement('video'); v.preload = 'metadata';
            v.onloadedmetadata = function () { var d = v.duration; URL.revokeObjectURL(v.src); resolve(d); };
            v.onerror = function () { URL.revokeObjectURL(v.src); resolve(NaN); };
            v.src = URL.createObjectURL(file);
        });
    }
    vInput.addEventListener('change', function () {
        vErr.hidden = true;
        var file = vInput.files && vInput.files[0]; vInput.value = '';
        if (!file) { return; }
        if (file.size > 200 * 1024 * 1024) { vErr.textContent = vZone.getAttribute('data-big'); vErr.hidden = false; return; }
        videoDuration(file).then(function (d) {
            if (isNaN(d) || d > maxSec + 1) { vErr.textContent = vZone.getAttribute('data-long'); vErr.hidden = false; return; }
            pending++; submit.disabled = true;
            sign('video').then(function (p) { return upload(file, p, function () {}); }).then(function (res) {
                vId.value = res.public_id;
                vPrev.innerHTML = '';
                var wrap = document.createElement('div'); wrap.className = 'preview preview-video';
                var v = document.createElement('video'); v.controls = true; v.src = URL.createObjectURL(file);
                var del = document.createElement('button'); del.type = 'button'; del.className = 'preview-remove'; del.textContent = '✕';
                del.addEventListener('click', vClear);
                wrap.appendChild(v); wrap.appendChild(del); vPrev.appendChild(wrap);
            }).catch(function () { vErr.textContent = vZone.getAttribute('data-fail'); vErr.hidden = false; })
              .finally(function () { pending--; submit.disabled = pending > 0; });
        });
    });

    form.addEventListener('submit', function (ev) {
        if (pending > 0) { ev.preventDefault(); return; }
        if (!isEdit && photos.length === 0) { ev.preventDefault(); photoErr.textContent = form.getAttribute('data-need') || 'Ajoute au moins une photo.'; photoErr.hidden = false; }
    });
})();
