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
                if (d.countryCode && country.querySelector('option[value="' + d.countryCode + '"]')) {
                    country.value = d.countryCode;
                }
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
