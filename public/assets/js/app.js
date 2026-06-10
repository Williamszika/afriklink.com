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

// Precise city detection on the registration form (target ≤100 m).
// Two layers: the server pre-fills from IP (approximate); here the browser's
// Geolocation API (GPS — the permission prompt IS the user's consent) refines the
// fix until accuracy ≤100 m (or 12 s max), then a free, key-less reverse-geocoding
// API (BigDataCloud, allowed in our CSP) turns coordinates into city + country.
// On denial/failure the IP-based values stay; every field remains editable.
(function () {
    'use strict';

    var city = document.getElementById('city');
    var country = document.getElementById('country_code');
    var status = document.getElementById('geo-status');
    if (!city || !country || !status || !('geolocation' in navigator) || !window.fetch) {
        return;
    }

    var TARGET_M = 100;        // requested max accuracy
    var REFINE_MS = 12000;     // give the GPS up to 12 s to reach it
    var msg = {
        detecting: status.getAttribute('data-detecting') || '',
        detected:  status.getAttribute('data-detected') || '',
        denied:    status.getAttribute('data-denied') || '',
        error:     status.getAttribute('data-error') || ''
    };

    var best = null;
    var watchId = null;
    var done = false;

    function setStatus(text, ok) {
        status.textContent = text;
        status.className = 'hint' + (ok ? ' geo-ok' : '');
    }

    function reverseGeocode(pos) {
        var acc = Math.round(pos.coords.accuracy);
        var url = 'https://api.bigdatacloud.net/data/reverse-geocode-client'
            + '?latitude=' + encodeURIComponent(pos.coords.latitude)
            + '&longitude=' + encodeURIComponent(pos.coords.longitude)
            + '&localityLanguage=fr';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var name = d.city || d.locality || '';
                if (name) { city.value = name; }
                if (d.countryCode && country.querySelector('option[value="' + d.countryCode + '"]')) {
                    country.value = d.countryCode;
                }
                setStatus(msg.detected.replace(':city', name || '—').replace(':acc', String(acc)), true);
            })
            .catch(function () { setStatus(msg.error, false); });
    }

    function finish() {
        if (done) { return; }
        done = true;
        if (watchId !== null) { navigator.geolocation.clearWatch(watchId); }
        if (best) { reverseGeocode(best); } else { setStatus(msg.error, false); }
    }

    setStatus(msg.detecting, false);
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            best = pos;
            if (pos.coords.accuracy <= TARGET_M) { finish(); return; }
            // First fix too coarse — keep watching until ≤100 m or the time budget ends.
            watchId = navigator.geolocation.watchPosition(
                function (p) {
                    if (!best || p.coords.accuracy < best.coords.accuracy) { best = p; }
                    if (p.coords.accuracy <= TARGET_M) { finish(); }
                },
                function () { finish(); },
                { enableHighAccuracy: true, maximumAge: 0 }
            );
            setTimeout(finish, REFINE_MS);
        },
        function (err) {
            setStatus(err && err.code === 1 ? msg.denied : msg.error, false);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
})();
