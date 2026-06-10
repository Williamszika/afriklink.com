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
    var videoInput = document.getElementById('video-input');
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

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            setError(photoError, '');
            var files = Array.prototype.slice.call(photoInput.files || []);
            photoInput.value = '';
            if (!files.length) { return; }
            if (photos.length + files.length > MAX_PHOTOS) {
                setError(photoError, photoError.dataset.max || ('Maximum ' + MAX_PHOTOS + ' photos.'));
                files = files.slice(0, Math.max(0, MAX_PHOTOS - photos.length));
            }
            files.forEach(function (original) {
                pending++; syncState();
                shrinkImage(original).then(function (file) {
                    return sign('image').then(function (params) { return uploadToCloudinary(file, params); });
                }).then(function (res) {
                    photos.push({ publicId: res.public_id });
                    addPhotoPreview(res.public_id, URL.createObjectURL(original));
                }).catch(function () {
                    setError(photoError, photoError.dataset.fail || "Échec de l'envoi d'une photo — réessaie.");
                }).finally(function () { pending--; syncState(); });
            });
        });
    }

    function videoDuration(file) {
        return new Promise(function (resolve) {
            var v = document.createElement('video');
            v.preload = 'metadata';
            v.onloadedmetadata = function () { var d = v.duration; URL.revokeObjectURL(v.src); resolve(d); };
            v.onerror = function () { URL.revokeObjectURL(v.src); resolve(NaN); };
            v.src = URL.createObjectURL(file);
        });
    }

    if (videoInput) {
        videoInput.addEventListener('change', function () {
            setError(videoError, '');
            var file = videoInput.files && videoInput.files[0];
            videoInput.value = '';
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
