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
// Layers: the server pre-fills from IP (approximate — can be the carrier/VPN exit,
// e.g. "Milan" while the user is elsewhere); the browser's Geolocation API (the
// permission prompt IS the user's consent) refines until accuracy ≤100 m (12 s
// budget), then a free key-less reverse-geocoding API (BigDataCloud, in our CSP)
// turns coordinates into city + country.
// Quality gate: a fix coarser than 2 km is almost certainly an IP/WiFi fallback —
// we then REFUSE to overwrite the city and say so, instead of asserting a wrong
// town. A retry button lets the user re-run after enabling GPS/permission.
(function () {
    'use strict';

    var city = document.getElementById('city');
    var country = document.getElementById('country_code');
    var status = document.getElementById('geo-status');
    var retry = document.getElementById('geo-retry');
    if (!city || !country || !status || !('geolocation' in navigator) || !window.fetch) {
        if (retry) { retry.hidden = true; }
        return;
    }

    var GOOD_M = 100;      // requested precision: fill + green confirmation
    var MAX_M = 2000;      // beyond this the fix is IP/WiFi-grade: don't trust it
    var REFINE_MS = 12000; // GPS refinement budget
    var msg = {
        detecting: status.getAttribute('data-detecting') || '',
        detected:  status.getAttribute('data-detected') || '',
        approx:    status.getAttribute('data-approx') || '',
        coarse:    status.getAttribute('data-coarse') || '',
        denied:    status.getAttribute('data-denied') || '',
        error:     status.getAttribute('data-error') || ''
    };
    var running = false;

    function setStatus(text, cls) {
        status.textContent = text;
        status.className = 'hint' + (cls ? ' ' + cls : '');
    }

    function fillFrom(data, acc) {
        var name = data.city || data.locality || '';
        if (name) { city.value = name; }
        if (data.countryCode && country.querySelector('option[value="' + data.countryCode + '"]')) {
            country.value = data.countryCode;
        }
        if (acc <= GOOD_M) {
            setStatus(msg.detected.replace(':city', name || '—').replace(':acc', String(acc)), 'geo-ok');
        } else {
            setStatus(msg.approx.replace(':city', name || '—').replace(':acc', String(acc)), 'geo-warn');
        }
    }

    function conclude(best) {
        running = false;
        if (!best) { setStatus(msg.error, ''); return; }
        var acc = Math.round(best.coords.accuracy);
        if (acc > MAX_M) {
            // IP/WiFi-grade fix: keep the field as-is rather than claim a wrong city.
            setStatus(msg.coarse.replace(':acc', String(acc)), 'geo-warn');
            return;
        }
        var url = 'https://api.bigdatacloud.net/data/reverse-geocode-client'
            + '?latitude=' + encodeURIComponent(best.coords.latitude)
            + '&longitude=' + encodeURIComponent(best.coords.longitude)
            + '&localityLanguage=fr';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) { fillFrom(d, acc); })
            .catch(function () { setStatus(msg.error, ''); });
    }

    function start() {
        if (running) { return; }
        running = true;
        setStatus(msg.detecting, '');
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
            function (err) {
                running = false;
                setStatus(err && err.code === 1 ? msg.denied : msg.error, '');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    if (retry) { retry.addEventListener('click', start); }
    start();
})();
