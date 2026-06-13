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
    // Éditeur (cadrage carré verrouillé) avant envoi ; remplace le fichier de
    // l'input par la version retouchée. ✕ = on vide l'input (envoi annulé).
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || input.dataset.edited === '1') { delete input.dataset.edited; return; }
        if (typeof window.alEditPhoto !== 'function') { return legacyShrink(file); }
        window.alEditPhoto(file, { aspect: 1, lockAspect: true, maxOut: 1024 }).then(function (res) {
            if (res === false) { input.value = ''; return; }
            if (res) {
                var dt = new DataTransfer();
                dt.items.add(res);
                input.dataset.edited = '1';
                input.files = dt.files;
            } else if (file.size >= 600 * 1024) {
                legacyShrink(file);
            }
        });
    });
    function legacyShrink(file) {
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
    }
})();

/* ---- Confirmation générique (CSP interdit les onclick inline) ---- */
document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-confirm]') : null;
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
        ev.preventDefault();
        ev.stopPropagation();
    }
}, true);

/* ---- Copie générique dans le presse-papiers (boutons [data-copy]) ----
   Copie le texte de l'attribut data-copy ; fallback execCommand pour les
   navigateurs sans Clipboard API. Affiche un retour bref « ✓ Copié ! ».
   data-open : ouvre en plus cette adresse dans un nouvel onglet (ex. TikTok,
   qui n'a pas d'URL de partage : on copie le lien puis on ouvre la page de
   publication). Ouverture synchrone pour rester dans le geste utilisateur. */
document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!el) { return; }
    ev.preventDefault();
    var text = el.getAttribute('data-copy') || '';
    var openUrl = el.getAttribute('data-open');
    if (openUrl) { window.open(openUrl, '_blank', 'noopener'); }

    function feedback() {
        var done = el.getAttribute('data-copied');
        if (!done) { return; }
        if (el.dataset.copyTimer) { window.clearTimeout(Number(el.dataset.copyTimer)); }
        else { el.setAttribute('data-copy-label', el.innerHTML); }
        el.innerHTML = done;
        el.classList.add('is-copied');
        el.dataset.copyTimer = String(window.setTimeout(function () {
            el.innerHTML = el.getAttribute('data-copy-label') || el.innerHTML;
            el.classList.remove('is-copied');
            delete el.dataset.copyTimer;
        }, 1600));
    }

    function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); feedback(); } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(feedback, fallback);
    } else {
        fallback();
    }
});

/* ---- « Autre » → champ libre (générique, tout le site) ----
   Un bloc [data-other-for="#controle"] s'affiche quand le contrôle visé est
   sur « autre » : <select> dont la valeur == data-other-value (défaut
   « autre »), ou case/radio cochée. Le bloc rendu côté serveur reste visible
   sans JavaScript si la valeur enregistrée est déjà « autre ». */
(function () {
    function refreshOtherBoxes() {
        document.querySelectorAll('[data-other-for]').forEach(function (box) {
            var ctrl = document.querySelector(box.getAttribute('data-other-for'));
            if (!ctrl) { return; }
            var on;
            if (ctrl.tagName === 'SELECT') {
                on = ctrl.value === (box.getAttribute('data-other-value') || 'autre');
            } else {
                on = !!ctrl.checked;
            }
            box.hidden = !on;
        });
    }
    document.addEventListener('change', function (ev) {
        if (ev.target && (ev.target.tagName === 'SELECT' || ev.target.type === 'checkbox' || ev.target.type === 'radio')) {
            refreshOtherBoxes();
        }
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshOtherBoxes);
    } else {
        refreshOtherBoxes();
    }
})();

/* ---- Carte restaurant : le formulaire d'ajout s'adapte à la catégorie ----
   Catégorie « boisson » → champs contenances + prix ; sinon → plat standard.
   Les champs du bloc caché sont désactivés pour ne pas être soumis/validés. */
(function () {
    var form = document.querySelector('[data-itemform]');
    var sel = form && form.querySelector('[data-itemcat]');
    if (!form || !sel) { return; }

    // Une contenance cochée → son champ prix s'active (sinon grisé et vidé).
    function syncVolRow(row) {
        var cb = row.querySelector('input[type="checkbox"]');
        var price = row.querySelector('.vol-price');
        if (!cb || !price) { return; }
        price.disabled = !cb.checked;
        if (!cb.checked) { price.value = ''; }
        row.classList.toggle('is-checked', cb.checked);
    }

    function apply() {
        var opt = sel.options[sel.selectedIndex];
        var kind = opt ? (opt.getAttribute('data-kind') || 'dish') : 'dish';
        form.querySelectorAll('[data-kind-block]').forEach(function (block) {
            var on = block.getAttribute('data-kind-block') === kind;
            block.hidden = !on;
            block.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
            // Dans le bloc boisson actif, le prix reste lié à la case cochée.
            if (on && block.getAttribute('data-kind-block') === 'drink') {
                block.querySelectorAll('[data-vol-row]').forEach(syncVolRow);
            }
        });
        var lbl = form.querySelector('[data-item-namelabel]');
        if (lbl) { lbl.textContent = kind === 'drink' ? (form.getAttribute('data-l-drink') || lbl.textContent) : (form.getAttribute('data-l-dish') || lbl.textContent); }
    }

    sel.addEventListener('change', apply);
    form.addEventListener('change', function (ev) {
        var row = ev.target && ev.target.closest ? ev.target.closest('[data-vol-row]') : null;
        if (row && ev.target.type === 'checkbox') {
            syncVolRow(row);
            if (ev.target.checked) {
                var pr = row.querySelector('.vol-price');
                if (pr) { pr.focus(); }
            }
        }
    });
    apply();
})();

/* ---- Carte restaurant : panier de commande ----
   Chaque plat / contenance de boisson est sélectionnable (stepper). Le total
   est calculé en direct ; à l'envoi, le panier est sérialisé (le serveur
   re-vérifie prix et disponibilité). */
(function () {
    var menu = document.querySelector('[data-resto-menu]');
    if (!menu) { return; }
    var curInt = menu.getAttribute('data-cur-int') === '1';
    var sym = menu.getAttribute('data-cur-sym') || '';
    var form = document.querySelector('[data-cart-form]');
    var bar = document.querySelector('[data-cart-bar]');
    var cart = {}; // clé id|size -> {name, price, qty}

    function fmt(cents) {
        var val = curInt ? Math.round(cents / 100) : cents / 100;
        var s = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: curInt ? 0 : 2 }).format(val);
        return s + ' ' + sym;
    }

    function render() {
        var count = 0, total = 0;
        var lines = form ? form.querySelector('[data-cart-lines]') : null;
        if (lines) { lines.textContent = ''; }
        Object.keys(cart).forEach(function (k) {
            var c = cart[k];
            if (c.qty <= 0) { return; }
            count += c.qty;
            total += c.qty * c.price;
            if (lines) {
                var li = document.createElement('li');
                li.className = 'cart-line';
                var l = document.createElement('span');
                l.textContent = c.qty + '× ' + c.name;
                var r = document.createElement('strong');
                r.textContent = fmt(c.qty * c.price);
                li.appendChild(l); li.appendChild(r);
                lines.appendChild(li);
            }
        });
        document.querySelectorAll('[data-cart-count]').forEach(function (e) { e.textContent = String(count); });
        document.querySelectorAll('[data-cart-total]').forEach(function (e) { e.textContent = fmt(total); });
        if (bar) { bar.hidden = count === 0; }
        if (form && count === 0) { form.hidden = true; }
    }

    function paint(stepper, qty) {
        var dec = stepper.querySelector('[data-qty-dec]');
        var val = stepper.querySelector('[data-qty-val]');
        var add = stepper.querySelector('[data-qty-inc]');
        if (val) { val.textContent = String(qty); val.hidden = qty === 0; }
        if (dec) { dec.hidden = qty === 0; }
        if (add) { add.classList.toggle('is-compact', qty > 0); add.textContent = qty > 0 ? '＋' : add.getAttribute('data-add-label'); }
        stepper.classList.toggle('is-active', qty > 0);
    }

    document.querySelectorAll('[data-order-item]').forEach(function (stepper) {
        var add = stepper.querySelector('[data-qty-inc]');
        if (add && !add.getAttribute('data-add-label')) { add.setAttribute('data-add-label', add.textContent.trim()); }
        var key = stepper.getAttribute('data-id') + '|' + stepper.getAttribute('data-size');
        cart[key] = { name: stepper.getAttribute('data-name'), price: parseInt(stepper.getAttribute('data-price'), 10) || 0, qty: 0 };

        stepper.addEventListener('click', function (ev) {
            var inc = ev.target.closest && ev.target.closest('[data-qty-inc]');
            var dec = ev.target.closest && ev.target.closest('[data-qty-dec]');
            if (!inc && !dec) { return; }
            ev.preventDefault();
            var c = cart[key];
            c.qty = Math.max(0, Math.min(99, c.qty + (inc ? 1 : -1)));
            paint(stepper, c.qty);
            render();
        });
    });

    if (bar) {
        var go = bar.querySelector('[data-cart-checkout]');
        if (go && form) {
            go.addEventListener('click', function () {
                form.hidden = false;
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var n = form.querySelector('#cl-name');
                if (n) { setTimeout(function () { n.focus(); }, 350); }
            });
        }
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            var out = [];
            Object.keys(cart).forEach(function (k) {
                var c = cart[k];
                if (c.qty > 0) {
                    var parts = k.split('|');
                    out.push({ id: parts[0], size: parts[1], qty: c.qty });
                }
            });
            if (out.length === 0) { ev.preventDefault(); return; }
            var hidden = form.querySelector('[data-cart-json]');
            if (hidden) { hidden.value = JSON.stringify(out); }
        });
    }
})();

/* ---- Géolocalisation générique (boutons [data-geolocate]) ----
   Méthode standard : navigator.geolocation demande la permission, fournit
   latitude/longitude ; notre serveur (/api/geo/reverse) convertit en ville,
   pays et continent. Cibles configurées par attributs :
   data-geo-city / data-geo-country / data-geo-continent / data-geo-address /
   data-geo-lat / data-geo-lng / data-geo-status (sélecteurs CSS). */
document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-geolocate]') : null;
    if (!btn) { return; }
    ev.preventDefault();
    runGeolocate(btn, false);
});

/* Relève la position et remplit les champs. silent = activation automatique
   (aucune invite, aucun message d'attente/erreur — utilisé au chargement quand
   la permission est déjà accordée). */
function runGeolocate(btn, silent) {
    function el(attr) {
        var sel = btn.getAttribute(attr);
        return sel ? document.querySelector(sel) : null;
    }
    var status = el('data-geo-status');
    function say(msg, isError) {
        if (status && !(silent && isError)) {
            status.textContent = msg || '';
            status.classList.toggle('is-error', !!isError);
        }
    }

    if (!navigator.geolocation) {
        if (!silent) { say(btn.getAttribute('data-msg-unsupported'), true); }
        return;
    }
    if (!silent) { btn.disabled = true; say(btn.getAttribute('data-msg-asking'), false); }

    navigator.geolocation.getCurrentPosition(function (position) {
        var lat = position.coords.latitude.toFixed(6);
        var lng = position.coords.longitude.toFixed(6);
        var latEl = el('data-geo-lat');
        var lngEl = el('data-geo-lng');
        if (latEl) { latEl.value = lat; }
        if (lngEl) { lngEl.value = lng; }

        fetch(btn.getAttribute('data-geo-url') + '?lat=' + lat + '&lng=' + lng, {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            if (!r.ok) { throw new Error('geo'); }
            return r.json();
        }).then(function (geo) {
            var city = el('data-geo-city');
            if (city && geo.city) { city.value = geo.city; }
            var country = el('data-geo-country');
            if (country && geo.country_code) {
                var opt = country.querySelector('option[value="' + geo.country_code + '"]');
                if (opt) { country.value = geo.country_code; }
            }
            var cont = el('data-geo-continent');
            if (cont && geo.continent_label) {
                cont.textContent = (cont.getAttribute('data-prefix') || '') + ' ' + geo.continent_label;
            }
            var addr = el('data-geo-address');
            if (addr && geo.formatted) { addr.value = geo.formatted; }

            // Indicatif téléphonique : on amorce WhatsApp/SMS avec l'indicatif du
            // pays détecté, tant que le champ est vide ou ne contient que l'indicatif.
            if (geo.dial) {
                document.querySelectorAll('[data-dialcode]').forEach(function (inp) {
                    var v = (inp.value || '').trim();
                    if (v === '' || /^\+?\d{1,4}$/.test(v.replace(/\s/g, ''))) {
                        inp.value = geo.dial + ' ';
                    }
                });
            }

            // Cases « Zones desservies » : libellés personnalisés en direct
            // (« 🏠 Dakar », « 🌍 Sénégal ») dès que la position est connue.
            var zoneCity = document.querySelector('[data-zone-label="city"]');
            if (zoneCity && geo.city) { zoneCity.textContent = '🏠 ' + geo.city; }
            var zoneCountry = document.querySelector('[data-zone-label="country"]');
            if (zoneCountry && geo.country) { zoneCountry.textContent = '🌍 ' + geo.country; }

            // Position fournie → ville/pays verrouillés (📍 reste le seul
            // moyen de les actualiser). Le select désactivé n'envoie rien :
            // un champ caché porte le pays à sa place.
            if (btn.getAttribute('data-geo-lock') === '1') {
                if (city) { city.readOnly = true; city.classList.add('is-locked'); }
                if (country) {
                    country.disabled = true;
                    country.classList.add('is-locked');
                    country.removeAttribute('name');
                    var hid = document.getElementById('shop-country-locked');
                    if (!hid) {
                        hid = document.createElement('input');
                        hid.type = 'hidden';
                        hid.name = 'country_code';
                        hid.id = 'shop-country-locked';
                        country.insertAdjacentElement('afterend', hid);
                    }
                    hid.value = geo.country_code || '';
                }
                var note = document.getElementById('geo-lock-note');
                if (note) { note.hidden = false; }
            }
            say('✓ ' + (geo.label || ''), false);
            btn.disabled = false;
        }).catch(function () {
            if (!silent) { say(btn.getAttribute('data-msg-error'), true); }
            btn.disabled = false;
        });
    }, function (err) {
        if (!silent) { say(btn.getAttribute(err && err.code === 1 ? 'data-msg-denied' : 'data-msg-error'), true); }
        btn.disabled = false;
    }, silent
        ? { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
        : { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
}

/* Auto-remplissage du formulaire SANS clic : si la permission est déjà
   accordée, on relève la position en silence dès le chargement. */
(function () {
    var autos = document.querySelectorAll('[data-geolocate][data-geo-auto]');
    if (!autos.length || !navigator.permissions || !navigator.permissions.query) { return; }
    navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
        if (p.state === 'granted') {
            autos.forEach(function (btn) { runGeolocate(btn, true); });
        }
    }).catch(function () { /* indisponible : le pré-remplissage serveur a déjà eu lieu */ });
})();

/* ---- Géolocalisation automatique du site ----
   1. La localisation par IP (sans permission) est déjà fournie par le serveur.
   2. Ici, on active la position GPS précise AUTOMATIQUEMENT quand c'est permis :
      - permission déjà accordée → on relève la position en silence ;
      - juste après connexion (data-geo-autoprompt) ou à la 1ʳᵉ visite → une
        seule invite ;
      les navigateurs interdisant l'invite répétée sans clic, un clic sur la
      puce « 📍 » relance toujours la demande. */
(function () {
    var url = document.body.getAttribute('data-geo-session-url');
    if (!url || !navigator.geolocation) { return; }

    function updateChips(text, flag) {
        document.querySelectorAll('[data-geo-chip]').forEach(function (chip) {
            var span = chip.querySelector('[data-geo-chip-text]');
            if (span && text) { span.textContent = text; }
            var fl = chip.querySelector('[data-geo-chip-flag]');
            if (fl && flag) { fl.textContent = flag; }
            if (text) { chip.hidden = false; }
        });
    }

    function locate() {
        navigator.geolocation.getCurrentPosition(function (pos) {
            try { localStorage.setItem('afrikGeoAsked', '1'); } catch (e) { /* privé */ }
            fetch(url + '?lat=' + pos.coords.latitude.toFixed(6) + '&lng=' + pos.coords.longitude.toFixed(6), {
                headers: { 'Accept': 'application/json' }
            }).then(function (r) { return r.ok ? r.json() : null; })
              .then(function (geo) { if (geo && geo.chip) { updateChips(geo.chip, geo.flag); } })
              .catch(function () { /* on garde la localisation IP */ });
        }, function () {
            try { localStorage.setItem('afrikGeoAsked', '1'); } catch (e) { /* privé */ }
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 600000 });
    }

    var asked = false;
    try { asked = localStorage.getItem('afrikGeoAsked') === '1'; } catch (e) { /* privé */ }
    var forcePrompt = document.body.hasAttribute('data-geo-autoprompt');

    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
            if (p.state === 'granted') { locate(); }                       // silencieux
            else if (p.state === 'prompt' && (forcePrompt || !asked)) { locate(); } // une invite
        }).catch(function () { if (forcePrompt || !asked) { locate(); } });
    } else if (forcePrompt || !asked) {
        locate();
    }

    // Clic sur la puce 📍 = relance explicite (geste utilisateur : marche toujours).
    document.addEventListener('click', function (ev) {
        if (ev.target && ev.target.closest && ev.target.closest('[data-geo-chip]')) {
            ev.preventDefault();
            locate();
        }
    });
})();

/* ---- Catalogue boutique : filtre « Tous / En ligne / Masqués » ----
   Filtrage instantané sans rechargement (les liens restent valides sans JS :
   ils rechargent la page avec ?filtre=…). Déclenché par les onglets et par les
   cartes d'indicateurs (« Produits en ligne »). */
(function () {
    var cat = document.querySelector('[data-catalogue]');
    if (!cat) { return; }

    function matches(status, filter) {
        return filter === 'tous'
            || (filter === 'en_ligne' && status === 'active')
            || (filter === 'masques' && status !== 'active');
    }

    function apply(filter, scroll) {
        var rows = cat.querySelectorAll('.product-row[data-status]');
        var visible = 0;
        rows.forEach(function (row) {
            var show = matches(row.getAttribute('data-status') || '', filter);
            row.classList.toggle('is-hidden', !show);
            if (show) { visible++; }
        });
        // Onglets : état actif + aria-selected.
        cat.querySelectorAll('.chip-filter[data-filter-to]').forEach(function (tab) {
            var on = tab.getAttribute('data-filter-to') === filter;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        // Message « aucun produit » propre au filtre.
        var empty = cat.querySelector('[data-filter-empty]');
        if (empty) {
            var msg = cat.getAttribute('data-empty-' + filter) || '';
            var p = empty.querySelector('p');
            if (p && msg) { p.textContent = msg; }
            empty.hidden = visible > 0;
        }
        // URL alignée sur le filtre (le rafraîchissement conserve la vue).
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('filtre', filter);
            window.history.replaceState(null, '', u.pathname + u.search + '#catalogue');
        } catch (e) { /* navigateurs anciens : on ignore */ }
        if (scroll) { cat.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    document.addEventListener('click', function (ev) {
        var trigger = ev.target && ev.target.closest ? ev.target.closest('[data-filter-to]') : null;
        if (!trigger) { return; }
        ev.preventDefault();
        apply(trigger.getAttribute('data-filter-to') || 'tous', !cat.contains(trigger));
    });
})();

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
        function uploadOne(original) {
            pending++; inflightPhotos++; syncState();
            shrinkImage(original).then(function (file) {
                return sign('image').then(function (params) { return uploadToCloudinary(file, params); });
            }).then(function (res) {
                photos.push({ publicId: res.public_id });
                addPhotoPreview(res.public_id, URL.createObjectURL(original));
            }).catch(function () {
                setError(photoError, photoError.dataset.fail || "Échec de l'envoi d'une photo — réessaie.");
            }).finally(function () { pending--; inflightPhotos--; syncState(); });
        }
        // Éditeur avant publication, photo par photo (Valider / Sans retouche / ✕).
        (function next(i) {
            if (i >= files.length) { return; }
            if (typeof window.alEditPhoto !== 'function') { uploadOne(files[i]); next(i + 1); return; }
            window.alEditPhoto(files[i], { watermark: true }).then(function (res) {
                if (res !== false) { uploadOne(res || files[i]); }
                next(i + 1);
            });
        })(0);
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
            input.value = '';
            if (!f) { return; }
            if (f.size > 10 * 1024 * 1024) { state.textContent = '⚠️ 10 Mo max'; return; }
            var edit = typeof window.alEditPhoto === 'function'
                ? window.alEditPhoto(f, { aspect: 1, lockAspect: true })   // logo : carré
                : Promise.resolve(null);
            edit.then(function (res) {
                if (res === false) { return; }
                var file = res || f;
                state.textContent = form.getAttribute('data-uploading') || '…';
                sign().then(function (p) { return uploadImg(file, p); })
                    .then(function (r) { hidden.value = r.public_id; state.textContent = '✅'; })
                    .catch(function () { state.textContent = '❌'; });
            });
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
            function uploadOne(file) {
                pending++; sync();
                sign().then(function (p) { return uploadImg(file, p); })
                    .then(function (res) { ids.push(res.public_id); addPreview(res.public_id, URL.createObjectURL(file)); })
                    .catch(function () {})
                    .finally(function () { pending--; sync(); });
            }
            (function next(i) {
                if (i >= files.length) { return; }
                var file = files[i];
                if (file.size > 10 * 1024 * 1024) { next(i + 1); return; }
                if (typeof window.alEditPhoto !== 'function') { uploadOne(file); next(i + 1); return; }
                // bannière : format large verrouillé (même cadre que la vitrine)
                window.alEditPhoto(file, { aspect: 1100 / 300, lockAspect: true }).then(function (res) {
                    if (res !== false) { uploadOne(res || file); }
                    next(i + 1);
                });
            })(0);
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
        function uploadOne(file) {
            pending++; syncPhotos(true);
            sign('image').then(function (p) { return upload(file, p); }).then(function (res) {
                photos.push(res.public_id); addPreview(res.public_id, URL.createObjectURL(file));
            }).catch(function () { photoErr.textContent = '❌'; photoErr.hidden = false; }).finally(function () { pending--; syncPhotos(true); });
        }
        (function next(i) {
            if (i >= files.length) { return; }
            if (typeof window.alEditPhoto !== 'function') { uploadOne(files[i]); next(i + 1); return; }
            window.alEditPhoto(files[i], { watermark: true }).then(function (res) {
                if (res !== false) { uploadOne(res || files[i]); }
                next(i + 1);
            });
        })(0);
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

/* ================================================================
   Éditeur de photo avant publication (maison, zéro dépendance).
   - Le CADRE est fixe (au format choisi) ; l'image se déplace/zoome
     dessous (doigt ou souris, molette/pincement pour zoomer).
   - Rotation 90°, miroir, curseurs luminosité/contraste/saturation,
   - ✨ amélioration auto (étirement d'histogramme local, hors ligne),
   - 🏷️ filigrane « afriklink.com » optionnel.
   Aperçu via filtres CSS (fluide) ; export par passe pixels (fidèle
   partout, même sans ctx.filter). Renvoie un nouveau File JPEG.
   window.alEditPhoto(file, opts) -> Promise<File|null|false>
     File  = photo retouchée  ·  null = « sans retouche »
     false = annulé (ne pas ajouter la photo)
   opts: { aspect: 'free'|number, lockAspect: bool,
           watermark: bool (toggle visible), maxOut: px } */
(function () {
    'use strict';

    var FR = document.documentElement.lang !== 'en';
    var T = {
        title:  FR ? 'Retoucher la photo' : 'Edit photo',
        free:   FR ? 'Libre' : 'Free',
        square: FR ? 'Carré' : 'Square',
        wide:   FR ? 'Paysage' : 'Wide',
        tall:   FR ? 'Portrait' : 'Tall',
        rotate: FR ? 'Pivoter' : 'Rotate',
        flip:   FR ? 'Miroir' : 'Flip',
        bright: FR ? 'Luminosité' : 'Brightness',
        contrast: FR ? 'Contraste' : 'Contrast',
        satur:  FR ? 'Saturation' : 'Saturation',
        improve: FR ? '✨ Améliorer' : '✨ Enhance',
        watermark: FR ? '🏷️ Filigrane Afriklink' : '🏷️ Afriklink watermark',
        bgRemove: FR ? '🪄 Retirer le fond' : '🪄 Remove background',
        bgWorking: FR ? 'Détourage en cours…' : 'Removing background…',
        bgRestore: FR ? '↩︎ Fond d’origine' : '↩︎ Original background',
        bgChoose: FR ? 'Nouveau fond :' : 'New background:',
        bgTransparent: FR ? 'Transparent' : 'Transparent',
        bgError: FR ? 'Détourage indisponible — active l’add-on « Cloudinary AI Background Removal » (gratuit) dans ton compte Cloudinary.' : 'Background removal unavailable — enable the free “Cloudinary AI Background Removal” add-on in your Cloudinary account.',
        cancel: FR ? 'Annuler' : 'Cancel',
        raw:    FR ? 'Sans retouche' : 'Use original',
        apply:  FR ? '✓ Valider' : '✓ Apply',
        hint:   FR ? 'Déplace et zoome la photo dans le cadre' : 'Drag and zoom the photo inside the frame'
    };

    window.alEditPhoto = function (file, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            if (!window.createImageBitmap || !file || file.type.indexOf('image/') !== 0) {
                resolve(null); return; // pas d'éditeur possible : utiliser telle quelle
            }
            createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
                open(bmp, file, opts, resolve);
            }).catch(function () { resolve(null); });
        });
    };

    function open(bmp, file, opts, resolve) {
        var rot = 0, flip = 1, zoom = 1, ox = 0, oy = 0;
        var b = 0, c = 0, s = 100, improve = false, wm = false;
        var aspect = (typeof opts.aspect === 'number') ? opts.aspect : 0; // 0 = libre (ratio image)
        var lock = !!opts.lockAspect;
        var improveLut = null;
        var cut = false;                    // un détourage a été appliqué
        var bg = { type: 'transparent' };   // fond après détourage
        var snap = null;                    // instantané d'avant détourage (rétablir)

        var ov = document.createElement('div');
        ov.className = 'pe-overlay';
        ov.innerHTML =
            '<div class="pe-box">' +
            '<p class="pe-title">' + T.title + '<span class="pe-hint">' + T.hint + '</span></p>' +
            '<div class="pe-stage"><canvas class="pe-canvas"></canvas></div>' +
            (lock ? '' :
            '<div class="pe-row pe-aspects">' +
              '<button type="button" data-a="0" class="btn btn-ghost btn-sm is-on">' + T.free + '</button>' +
              '<button type="button" data-a="1" class="btn btn-ghost btn-sm">' + T.square + ' 1:1</button>' +
              '<button type="button" data-a="1.7778" class="btn btn-ghost btn-sm">' + T.wide + ' 16:9</button>' +
              '<button type="button" data-a="0.8" class="btn btn-ghost btn-sm">' + T.tall + ' 4:5</button>' +
            '</div>') +
            '<div class="pe-row">' +
              '<button type="button" class="btn btn-ghost btn-sm pe-rot">🔄 ' + T.rotate + '</button>' +
              '<button type="button" class="btn btn-ghost btn-sm pe-flip">↔️ ' + T.flip + '</button>' +
              '<button type="button" class="btn btn-ghost btn-sm pe-improve">' + T.improve + '</button>' +
              (opts.watermark ? '<button type="button" class="btn btn-ghost btn-sm pe-wm">' + T.watermark + '</button>' : '') +
            '</div>' +
            '<div class="pe-row pe-bgrow">' +
              '<button type="button" class="btn btn-ghost btn-sm pe-bgrm">' + T.bgRemove + '</button>' +
              '<button type="button" class="btn btn-ghost btn-sm pe-bgrestore" hidden>' + T.bgRestore + '</button>' +
            '</div>' +
            '<div class="pe-bgpalette" hidden><span class="pe-bglabel">' + T.bgChoose + '</span><span class="pe-swatches"></span></div>' +
            '<p class="pe-bgerror field-error" hidden></p>' +
            '<div class="pe-sliders">' +
              '<label>☀️ ' + T.bright + '<input type="range" class="pe-b" min="-50" max="50" value="0"></label>' +
              '<label>◐ ' + T.contrast + '<input type="range" class="pe-c" min="-50" max="50" value="0"></label>' +
              '<label>🎨 ' + T.satur + '<input type="range" class="pe-s" min="0" max="200" value="100"></label>' +
            '</div>' +
            '<div class="pe-row pe-actions">' +
              '<button type="button" class="btn btn-ghost btn-sm pe-cancel">✕ ' + T.cancel + '</button>' +
              '<button type="button" class="btn btn-ghost btn-sm pe-raw">' + T.raw + '</button>' +
              '<button type="button" class="btn btn-primary pe-apply">' + T.apply + '</button>' +
            '</div></div>';
        document.body.appendChild(ov);

        var canvas = ov.querySelector('.pe-canvas');
        var ctx = canvas.getContext('2d');

        function imgW() { return (rot % 180 === 0) ? bmp.width : bmp.height; }
        function imgH() { return (rot % 180 === 0) ? bmp.height : bmp.width; }
        function ratio() { return aspect > 0 ? aspect : imgW() / imgH(); }

        function sizeCanvas() {
            var stage = ov.querySelector('.pe-stage');
            var maxW = Math.min(stage.clientWidth, 560);
            var maxH = Math.min(window.innerHeight * 0.45, 420);
            var r = ratio();
            var w = maxW, h = w / r;
            if (h > maxH) { h = maxH; w = h * r; }
            canvas.width = Math.round(w * 2);   // x2 : netteté écrans denses
            canvas.height = Math.round(h * 2);
            canvas.style.width = Math.round(w) + 'px';
            canvas.style.height = Math.round(h) + 'px';
        }

        // échelle de base : l'image COUVRE le cadre à zoom=1
        function baseScale() {
            return Math.max(canvas.width / imgW(), canvas.height / imgH());
        }
        function clampOffsets() {
            var sc = baseScale() * zoom;
            var mx = Math.max(0, (imgW() * sc - canvas.width) / 2);
            var my = Math.max(0, (imgH() * sc - canvas.height) / 2);
            ox = Math.min(mx, Math.max(-mx, ox));
            oy = Math.min(my, Math.max(-my, oy));
        }
        function paintBg(c2, w, h, forExport) {
            if (!cut) { c2.fillStyle = '#111'; c2.fillRect(0, 0, w, h); return; }
            if (bg.type === 'transparent') {
                if (forExport) { return; } // export PNG : on laisse transparent
                var sq = Math.max(10, Math.round(w / 22)); // damier d'aperçu
                for (var y = 0; y < h; y += sq) {
                    for (var x = 0; x < w; x += sq) {
                        c2.fillStyle = (((x / sq) + (y / sq)) % 2 === 0) ? '#e9eaec' : '#cfd2d6';
                        c2.fillRect(x, y, sq, sq);
                    }
                }
            } else if (bg.type === 'color') {
                c2.fillStyle = bg.value; c2.fillRect(0, 0, w, h);
            } else if (bg.type === 'gradient') {
                var g = c2.createLinearGradient(0, 0, w, h);
                g.addColorStop(0, bg.value[0]); g.addColorStop(1, bg.value[1]);
                c2.fillStyle = g; c2.fillRect(0, 0, w, h);
            }
        }
        function draw() {
            clampOffsets();
            var sc = baseScale() * zoom;
            ctx.save();
            paintBg(ctx, canvas.width, canvas.height, false);
            ctx.translate(canvas.width / 2 + ox, canvas.height / 2 + oy);
            ctx.rotate(rot * Math.PI / 180);
            ctx.scale(flip * sc, sc);
            ctx.drawImage(bmp, -bmp.width / 2, -bmp.height / 2);
            ctx.restore();
            canvas.style.filter = cssFilter(); // curseurs actifs aussi après détourage
        }
        function cssFilter() {
            var f = 'brightness(' + (1 + b / 100) + ') contrast(' + (1 + c / 100) + ') saturate(' + (s / 100) + ')';
            if (improve && improveLut) { f += ' brightness(' + improveLut.css.b + ') contrast(' + improveLut.css.c + ') saturate(1.08)'; }
            return f;
        }

        /* ✨ auto-niveaux : percentiles 2/98 de luminance sur une vignette */
        function computeImprove() {
            var t = document.createElement('canvas');
            var n = 64; t.width = n; t.height = n;
            var tc = t.getContext('2d');
            tc.drawImage(bmp, 0, 0, n, n);
            var d = tc.getImageData(0, 0, n, n).data;
            var hist = new Array(256).fill(0);
            for (var i = 0; i < d.length; i += 4) {
                hist[Math.round(0.2126 * d[i] + 0.7152 * d[i + 1] + 0.0722 * d[i + 2])]++;
            }
            var total = n * n, lo = 0, hi = 255, acc = 0;
            for (var j = 0; j < 256; j++) { acc += hist[j]; if (acc >= total * 0.02) { lo = j; break; } }
            acc = 0;
            for (var k = 255; k >= 0; k--) { acc += hist[k]; if (acc >= total * 0.02) { hi = k; break; } }
            if (hi - lo < 20) { lo = 0; hi = 255; }
            var gain = 255 / (hi - lo);
            improveLut = { lo: lo, gain: gain,
                css: { b: (1 + (128 - (lo + hi) / 2) / 600).toFixed(3), c: Math.min(1.35, gain).toFixed(3) } };
        }

        /* interactions : glisser + molette + pincement */
        var pts = {};
        var lastDist = 0;
        canvas.addEventListener('pointerdown', function (e) { pts[e.pointerId] = e; canvas.setPointerCapture(e.pointerId); });
        canvas.addEventListener('pointermove', function (e) {
            if (!pts[e.pointerId]) { return; }
            var keys = Object.keys(pts);
            if (keys.length === 1) {
                ox += (e.clientX - pts[e.pointerId].clientX) * 2;
                oy += (e.clientY - pts[e.pointerId].clientY) * 2;
                draw();
            } else if (keys.length === 2) {
                pts[e.pointerId] = e;
                var a = pts[keys[0]], z = pts[keys[1]];
                var dist = Math.hypot(a.clientX - z.clientX, a.clientY - z.clientY);
                if (lastDist) { zoom = Math.min(5, Math.max(1, zoom * dist / lastDist)); draw(); }
                lastDist = dist;
                return;
            }
            pts[e.pointerId] = e;
        });
        function up(e) { delete pts[e.pointerId]; lastDist = 0; }
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointercancel', up);
        canvas.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoom = Math.min(5, Math.max(1, zoom * (e.deltaY < 0 ? 1.08 : 0.93)));
            draw();
        }, { passive: false });

        /* contrôles */
        Array.prototype.forEach.call(ov.querySelectorAll('.pe-aspects button'), function (btn) {
            btn.addEventListener('click', function () {
                Array.prototype.forEach.call(ov.querySelectorAll('.pe-aspects button'), function (x) { x.classList.remove('is-on'); });
                btn.classList.add('is-on');
                aspect = parseFloat(btn.getAttribute('data-a')) || 0;
                zoom = 1; ox = oy = 0; sizeCanvas(); draw();
            });
        });
        ov.querySelector('.pe-rot').addEventListener('click', function () { rot = (rot + 90) % 360; zoom = 1; ox = oy = 0; sizeCanvas(); draw(); });
        ov.querySelector('.pe-flip').addEventListener('click', function () { flip *= -1; draw(); });
        ov.querySelector('.pe-improve').addEventListener('click', function () {
            improve = !improve;
            if (improve && !improveLut) { computeImprove(); }
            this.classList.toggle('is-on', improve); draw();
        });
        var wmBtn = ov.querySelector('.pe-wm');
        if (wmBtn) { wmBtn.addEventListener('click', function () { wm = !wm; this.classList.toggle('is-on', wm); }); }
        ov.querySelector('.pe-b').addEventListener('input', function () { b = +this.value; draw(); });
        ov.querySelector('.pe-c').addEventListener('input', function () { c = +this.value; draw(); });
        ov.querySelector('.pe-s').addEventListener('input', function () { s = +this.value; draw(); });

        function close(result) { ov.remove(); resolve(result); }
        ov.querySelector('.pe-cancel').addEventListener('click', function () { close(false); });
        ov.querySelector('.pe-raw').addEventListener('click', function () { close(null); });
        ov.querySelector('.pe-apply').addEventListener('click', function () {
            var transparent = cut && bg.type === 'transparent';
            var out = exportImage();
            out.toBlob(function (blob) {
                if (!blob) { close(null); return; }
                close(new File([blob], transparent ? 'photo.png' : 'photo.jpg', { type: transparent ? 'image/png' : 'image/jpeg' }));
            }, transparent ? 'image/png' : 'image/jpeg', 0.9);
        });

        /* ---- Détourage (Cloudinary AI) + nouveau fond ---- */
        var bgErr = ov.querySelector('.pe-bgerror');
        var palette = ov.querySelector('.pe-bgpalette');
        var restoreBtn = ov.querySelector('.pe-bgrestore');
        var rmBtn = ov.querySelector('.pe-bgrm');

        function signImage() {
            var fd = new FormData(); fd.append('resource_type', 'image');
            // window.fetch est wrappé : il ajoute X-CSRF-Token automatiquement.
            return fetch('/api/media/sign', { method: 'POST', body: fd })
                .then(function (r) { if (!r.ok) { throw new Error('sign'); } return r.json(); });
        }
        function uploadTo(blob, params) {
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/image/upload');
                xhr.onload = function () { if (xhr.status >= 200 && xhr.status < 300) { try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(e); } } else { reject(new Error('up')); } };
                xhr.onerror = function () { reject(new Error('net')); };
                var fd = new FormData();
                fd.append('file', blob); fd.append('api_key', params.api_key); fd.append('timestamp', params.timestamp);
                fd.append('folder', params.folder); fd.append('signature', params.signature);
                xhr.send(fd);
            });
        }
        // Charge l'image détourée, avec relances (Cloudinary traite la 1ʳᵉ fois).
        function loadCutout(url, tries) {
            return new Promise(function (resolve, reject) {
                var attempt = function (left) {
                    var img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = function () { resolve(img); };
                    img.onerror = function () {
                        if (left <= 0) { reject(new Error('bg')); return; }
                        setTimeout(function () { attempt(left - 1); }, 1800);
                    };
                    img.src = url + (url.indexOf('?') < 0 ? '?t=' : '&t=') + Date.now();
                };
                attempt(tries);
            });
        }

        rmBtn.addEventListener('click', function () {
            bgErr.hidden = true;
            rmBtn.disabled = true;
            var prev = rmBtn.textContent;
            rmBtn.textContent = T.bgWorking;
            var src = exportImage(); // vue actuelle (recadrée + réglée)
            src.toBlob(function (blob) {
                signImage()
                    .then(function (p) { return uploadTo(blob, p); })
                    .then(function (res) {
                        var url = (res.secure_url || '').replace('/upload/', '/upload/e_background_removal/q_auto/').replace(/\.[a-z0-9]+$/i, '.png');
                        return loadCutout(url, 14);
                    })
                    .then(function (img) { return createImageBitmap(img); })
                    .then(function (newbmp) {
                        snap = { bmp: bmp, rot: rot, flip: flip, zoom: zoom, ox: ox, oy: oy, b: b, c: c, s: s, improve: improve, lut: improveLut };
                        bmp = newbmp; cut = true; bg = { type: 'transparent' };
                        rot = 0; flip = 1; zoom = 1; ox = 0; oy = 0; b = 0; c = 0; s = 100;
                        improve = false; improveLut = null; // base neuve : LUT à recalculer
                        ov.querySelector('.pe-improve').classList.remove('is-on');
                        ov.querySelector('.pe-b').value = 0; ov.querySelector('.pe-c').value = 0; ov.querySelector('.pe-s').value = 100;
                        palette.hidden = false; restoreBtn.hidden = false;
                        sizeCanvas(); draw();
                    })
                    .catch(function () { bgErr.textContent = T.bgError; bgErr.hidden = false; })
                    .finally(function () { rmBtn.disabled = false; rmBtn.textContent = prev; });
            }, 'image/jpeg', 0.92);
        });

        restoreBtn.addEventListener('click', function () {
            if (!snap) { return; }
            bmp = snap.bmp; rot = snap.rot; flip = snap.flip; zoom = snap.zoom; ox = snap.ox; oy = snap.oy;
            b = snap.b; c = snap.c; s = snap.s; improve = snap.improve; improveLut = snap.lut || null;
            ov.querySelector('.pe-improve').classList.toggle('is-on', improve);
            ov.querySelector('.pe-b').value = b; ov.querySelector('.pe-c').value = c; ov.querySelector('.pe-s').value = s;
            cut = false; snap = null; palette.hidden = true; restoreBtn.hidden = true; bgErr.hidden = true;
            sizeCanvas(); draw();
        });

        // Nuancier des fonds (après détourage)
        (function buildSwatches() {
            var sw = ov.querySelector('.pe-swatches');
            var items = [
                { label: T.bgTransparent, css: 'repeating-conic-gradient(#cfd2d6 0% 25%, #fff 0% 50%) 0/14px 14px', set: { type: 'transparent' } },
                { css: '#ffffff', set: { type: 'color', value: '#ffffff' } },
                { css: '#f3f4f6', set: { type: 'color', value: '#f3f4f6' } },
                { css: '#111827', set: { type: 'color', value: '#111827' } },
                { css: '#0b7a4b', set: { type: 'color', value: '#0b7a4b' } },
                { css: '#f5a623', set: { type: 'color', value: '#f5a623' } },
                { css: 'linear-gradient(135deg,#fde68a,#fb7185)', set: { type: 'gradient', value: ['#fde68a', '#fb7185'] } },
                { css: 'linear-gradient(135deg,#a7f3d0,#60a5fa)', set: { type: 'gradient', value: ['#a7f3d0', '#60a5fa'] } },
                { css: 'linear-gradient(135deg,#e5e7eb,#9ca3af)', set: { type: 'gradient', value: ['#e5e7eb', '#9ca3af'] } }
            ];
            items.forEach(function (it) {
                var btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'pe-swatch';
                btn.style.background = it.css;
                if (it.label) { btn.title = it.label; btn.classList.add('pe-swatch--transp'); }
                btn.addEventListener('click', function () {
                    bg = it.set;
                    Array.prototype.forEach.call(sw.children, function (x) { x.classList.remove('is-on'); });
                    btn.classList.add('is-on');
                    draw();
                });
                sw.appendChild(btn);
            });
            sw.firstChild.classList.add('is-on');
        })();

        /* export fidèle : géométrie + passe pixels (B/C/S + auto-niveaux) + filigrane */
        function exportImage() {
            var maxOut = opts.maxOut || 1600;
            var sc = baseScale() * zoom;
            var srcW = canvas.width / sc, srcH = canvas.height / sc;        // zone visible, en px image
            var scale = Math.min(1, maxOut / Math.max(srcW, srcH));
            var w = Math.round(srcW * scale), h = Math.round(srcH * scale);
            var out = document.createElement('canvas');
            out.width = w; out.height = h;
            var octx = out.getContext('2d');
            paintBg(octx, w, h, true);           // fond choisi (rien si transparent)
            octx.translate(w / 2 + (ox / sc) * scale, h / 2 + (oy / sc) * scale);
            octx.rotate(rot * Math.PI / 180);
            octx.scale(flip * scale, scale);
            octx.drawImage(bmp, -bmp.width / 2, -bmp.height / 2);
            octx.setTransform(1, 0, 0, 1, 0, 0);

            // Passe pixels (B/C/S + auto-niveaux), fidèle à l'aperçu. Le canal
            // alpha n'est jamais touché : la transparence du détourage survit.
            if (b !== 0 || c !== 0 || s !== 100 || improve) {
                var imgd = octx.getImageData(0, 0, w, h), d = imgd.data;
                var bb = b * 2.55, cc = (1 + c / 100), ss = s / 100;
                var lo = improve && improveLut ? improveLut.lo : 0;
                var gain = improve && improveLut ? improveLut.gain : 1;
                for (var i = 0; i < d.length; i += 4) {
                    var r = d[i], g = d[i + 1], bl = d[i + 2];
                    if (improve) { r = (r - lo) * gain; g = (g - lo) * gain; bl = (bl - lo) * gain; }
                    r = (r - 128) * cc + 128 + bb; g = (g - 128) * cc + 128 + bb; bl = (bl - 128) * cc + 128 + bb;
                    if (ss !== 1) {
                        var gray = 0.2989 * r + 0.587 * g + 0.114 * bl;
                        r = gray + (r - gray) * ss; g = gray + (g - gray) * ss; bl = gray + (bl - gray) * ss;
                    }
                    d[i] = r < 0 ? 0 : r > 255 ? 255 : r;
                    d[i + 1] = g < 0 ? 0 : g > 255 ? 255 : g;
                    d[i + 2] = bl < 0 ? 0 : bl > 255 ? 255 : bl;
                }
                octx.putImageData(imgd, 0, 0);
            }

            if (wm) {
                var fs = Math.max(14, Math.round(w * 0.035));
                octx.font = '700 ' + fs + 'px system-ui, sans-serif';
                octx.textAlign = 'right'; octx.textBaseline = 'bottom';
                octx.fillStyle = 'rgba(0,0,0,.35)';
                octx.fillText('afriklink.com', w - fs * 0.6 + 1, h - fs * 0.5 + 1);
                octx.fillStyle = 'rgba(255,255,255,.85)';
                octx.fillText('afriklink.com', w - fs * 0.6, h - fs * 0.5);
            }
            return out;
        }

        sizeCanvas();
        draw();
        window.addEventListener('resize', function onR() {
            if (!document.body.contains(ov)) { window.removeEventListener('resize', onR); return; }
            sizeCanvas(); draw();
        });
    }
})();
