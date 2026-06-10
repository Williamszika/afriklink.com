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
