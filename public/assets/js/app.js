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

// Toast éphémère (confirmation « Ajouté au panier », etc.). CSP-safe : créé en
// JS, animé en CSS. Accessible (role=status). Auto-disparaît.
function showToast(msg) {
    if (!msg) { return; }
    var host = document.querySelector('.toast-host');
    if (!host) {
        host = document.createElement('div');
        host.className = 'toast-host';
        document.body.appendChild(host);
    }
    var el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    host.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-in'); });
    setTimeout(function () {
        el.classList.remove('is-in');
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300);
    }, 2200);
}

// Escape hatch for the geolocation-locked location block: the discreet
// "Ce n'est pas ma position ?" link re-enables the country/indicatif selects AND
// the city (removing the hidden inputs so the user's own choice is submitted).
// Once unlocked, the GPS refinement below stops touching the fields.
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
                sel.classList.remove('locked-field', 'is-locked');
                sel.name = id;                 // re-enabled select submits itself
                sel.dataset.unlocked = '1';    // tells the GPS code to back off
            }
            var hidden = document.getElementById(id + '_value');
            if (hidden && hidden.parentNode) { hidden.parentNode.removeChild(hidden); }
        });
        // The city is locked (read-only) after a precise GPS fix — release it too.
        var cityEl = document.getElementById('city');
        if (cityEl) { cityEl.readOnly = false; cityEl.classList.remove('is-locked'); cityEl.dataset.unlocked = '1'; }
        var note = document.getElementById('geo-lock-note');
        if (note) { note.hidden = true; }
    });
})();

// Same escape hatch for the boutique/restaurant location block (geo_fields
// partial, which uses shop-* ids and the [data-geolocate] flow). The location is
// locked as soon as a position is known — even an IP-coarse one — so this lets
// the seller correct a wrong city/country by hand, and tells the auto-geoloc to
// stop re-locking the fields.
(function () {
    'use strict';

    var unlockBtn = document.getElementById('shop-geo-unlock');
    if (!unlockBtn) { return; }

    unlockBtn.addEventListener('click', function () {
        var city = document.getElementById('shop-city');
        if (city) { city.readOnly = false; city.classList.remove('is-locked'); city.dataset.unlocked = '1'; }
        var country = document.getElementById('shop-country');
        if (country) {
            country.disabled = false;
            country.removeAttribute('aria-disabled');
            country.classList.remove('is-locked', 'locked-field');
            country.name = 'country_code';   // re-enabled select submits itself
            country.dataset.unlocked = '1';  // tells runGeolocate to back off
        }
        var hidden = document.getElementById('shop-country-locked');
        if (hidden && hidden.parentNode) { hidden.parentNode.removeChild(hidden); }
        // Drop the detected coordinates: a hand-typed city must not carry the
        // previously detected lat/lng (which belonged to another place).
        ['geo-lat', 'geo-lng'].forEach(function (id) {
            var f = document.getElementById(id);
            if (f) { f.value = ''; }
        });
        var note = document.getElementById('geo-lock-note');
        if (note) { note.hidden = true; }
    });
})();

// Precise city detection on the registration form (target ≤100 m) — fully silent.
// The server pre-fills from IP (approximate); the browser's Geolocation API (the
// permission prompt IS the user's consent) refines until accuracy ≤100 m (12 s
// budget), then a free key-less reverse-geocoding API (BigDataCloud, in our CSP)
// turns coordinates into city + country.
// Quality gate: a fix coarser than 2 km is almost certainly an IP/WiFi fallback —
// the city is then left untouched. On a good fix, city + country are filled AND
// locked (read-only input / disabled select + hidden submit input); the
// "Ce n'est pas ma position ?" link restores manual editing.
// Two entry points so geolocation works EVERYWHERE:
//   • a silent attempt on load (desktop / already-granted permission),
//   • a visible "Utiliser ma position" button (#geo-detect) — REQUIRED on mobile
//     Safari & several mobile browsers that ignore getCurrentPosition unless it
//     follows a user gesture (otherwise the prompt never appears → feature looks
//     disabled). The browser permission prompt IS the user's consent.
// The server pre-fills from IP (approximate); the Geolocation API refines until
// ≤100 m, then a key-less reverse-geocoder (BigDataCloud, in our CSP) turns
// coordinates into city + country. Fields always stay editable.
(function () {
    'use strict';

    var city = document.getElementById('city');
    var country = document.getElementById('country_code');
    if (!city || !('geolocation' in navigator) || !window.fetch) {
        return;
    }

    var GOOD_M = 100;      // requested precision
    var MAX_M = 2000;      // beyond this the fix is IP/WiFi-grade: don't trust it
    var REFINE_MS = 12000; // GPS refinement budget
    var btn = document.getElementById('geo-detect');
    var statusEl = document.getElementById('geo-detect-status');

    function attr(name) { return btn ? (btn.getAttribute(name) || '') : ''; }
    function say(text, isError) {
        if (!statusEl) { return; }
        statusEl.textContent = text || '';
        statusEl.classList.toggle('is-error', !!isError);
    }

    // Lock a <select> on a precise fix: set its value + a hidden submit input.
    // If it's still editable (no existing hidden — e.g. pro signup / profile /
    // annonce), disable it and create the hidden input so the precise country
    // can't be desynced. The "unlock" link reverses this. No-op if absent
    // (annonce forms have a city but no country select).
    function lockSelect(id, iso) {
        var sel = document.getElementById(id);
        if (!sel) { return; }
        if (sel.querySelector('option[value="' + iso + '"]')) { sel.value = iso; }
        var hidden = document.getElementById(id + '_value');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = id + '_value';
            hidden.name = sel.getAttribute('name') || id;
            sel.insertAdjacentElement('afterend', hidden);
            sel.removeAttribute('name');
            sel.disabled = true;
            sel.setAttribute('aria-disabled', 'true');
            sel.setAttribute('tabindex', '-1');
            sel.classList.add('is-locked', 'locked-field');
        }
        hidden.value = iso;
    }

    function applyCountry(iso) {
        iso = (iso || '').toUpperCase();
        // The user chose their location manually after unlocking — respect it.
        if (!iso || (country && country.dataset.unlocked === '1')) { return; }
        lockSelect('country_code', iso);
        prioritizeDial(iso); // phone country selector → detected country first (shared convention)
    }

    function conclude() {
        if (!best || Math.round(best.coords.accuracy) > MAX_M) {
            if (mode === 'manual') { say(attr('data-unavailable'), true); } // IP/WiFi-grade fix
            return;
        }
        var url = 'https://api.bigdatacloud.net/data/reverse-geocode-client'
            + '?latitude=' + encodeURIComponent(best.coords.latitude)
            + '&longitude=' + encodeURIComponent(best.coords.longitude)
            + '&localityLanguage=fr';
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var name = d.city || d.locality || '';
                // Don't re-lock once the user chose to edit manually (unlock link).
                var canLock = (!country || country.dataset.unlocked !== '1') && city.dataset.unlocked !== '1';
                if (name) {
                    city.value = name;
                    if (canLock) { city.readOnly = true; city.classList.add('is-locked'); }
                }
                applyCountry(d.countryCode);
                var note = document.getElementById('geo-lock-note');
                if (note && canLock) { note.hidden = false; }
                if (mode === 'manual') { say('✓ ' + (name || 'OK'), false); }
            })
            .catch(function () { if (mode === 'manual') { say(attr('data-unavailable'), true); } });
    }

    var best = null;
    var watchId = null;
    var pollId = null;
    var done = false;
    var mode = 'silent';

    function stopWatch() {
        if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
        if (pollId !== null) { clearInterval(pollId); pollId = null; }
    }

    function consider(p) {
        if (!best || p.coords.accuracy < best.coords.accuracy) { best = p; }
        if (p.coords.accuracy <= GOOD_M) { finish(); }
    }

    function finish() {
        if (done) { return; }
        done = true;
        stopWatch();
        if (btn) { btn.disabled = false; }
        conclude();
    }

    function refine(which) {
        mode = which;
        stopWatch();
        best = null; done = false;
        if (which === 'manual') { say(attr('data-asking'), false); if (btn) { btn.disabled = true; } }
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                best = pos;
                if (pos.coords.accuracy <= GOOD_M) { finish(); return; }
                // First fix too coarse — refine until ≤100 m or the budget ends.
                // watchPosition AND an active 2.5 s re-poll: some browsers/WebViews
                // never push watch updates, so polling is the reliable fallback.
                watchId = navigator.geolocation.watchPosition(
                    consider,
                    function (e) { if (e && e.code === 1) { finish(); } },
                    { enableHighAccuracy: true, maximumAge: 0 }
                );
                pollId = setInterval(function () {
                    navigator.geolocation.getCurrentPosition(
                        consider, function () {}, { enableHighAccuracy: true, maximumAge: 0, timeout: 2000 }
                    );
                }, 2500);
                setTimeout(finish, REFINE_MS);
            },
            function (e) {
                if (btn) { btn.disabled = false; }
                // Permission denial (code 1) vs transient unavailable — message only
                // for the explicit button press; the silent attempt stays quiet.
                if (which === 'manual') { say(attr(e && e.code === 1 ? 'data-denied' : 'data-unavailable'), true); }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    // Gesture-triggered button: reliable on every browser, incl. mobile Safari.
    if (btn) {
        btn.hidden = false;
        btn.addEventListener('click', function () { refine('manual'); });
    }
    // Silent attempt on load — only when the city is empty, so we never overwrite
    // a value already typed or saved (profile / annonce editing). The button
    // always (re)detects on demand.
    if (!city.value.trim()) {
        refine('silent');
    } else if (city.dataset.geoPrefill === '1' && city.dataset.unlocked !== '1'
        && navigator.permissions && navigator.permissions.query) {
        // City pre-filled by coarse detection (IP/edge) and locked: refine it to a
        // precise GPS fix — but ONLY if permission is already granted, so we never
        // raise a new prompt on load. Otherwise the 📍 button stays available.
        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
            if (p.state === 'granted') { refine('silent'); }
        }).catch(function () { /* indisponible : le bouton 📍 reste là */ });
    }
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

/* ---- Repli d'image CSP-safe : masque une <img data-hide-on-error> qui n'a pas
   pu charger. Remplace l'attribut onerror inline (interdit par la CSP). L'événement
   « error » d'une image ne remonte pas → on écoute en phase de CAPTURE. ---- */
document.addEventListener('error', function (ev) {
    var t = ev.target;
    if (t && t.tagName === 'IMG' && t.hasAttribute('data-hide-on-error')) {
        t.remove();
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

/* ---- Panier de commande (restaurant ET boutique) ----
   Chaque article sélectionnable porte un stepper. Le total est calculé en
   direct ; à l'envoi, le panier est sérialisé (le serveur re-vérifie prix et
   disponibilité). Activé dès qu'un élément [data-cart-root] est présent. */
(function () {
    var menu = document.querySelector('[data-cart-root]');
    if (!menu) { return; }
    var curInt = menu.getAttribute('data-cur-int') === '1';
    var sym = menu.getAttribute('data-cur-sym') || '';
    var form = document.querySelector('[data-cart-form]');
    var bar = document.querySelector('[data-cart-bar]');
    var cart = {}; // clé id|size -> {name, price, qty}
    var shopSlug = menu.getAttribute('data-shop-slug') || '';

    // Synchronise le panier persistant (serveur) + le compteur d'en-tête (boutique).
    function syncServer(pid, qty) {
        if (!shopSlug) { return; }
        fetch('/panier/ajouter', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'slug=' + encodeURIComponent(shopSlug) + '&pid=' + encodeURIComponent(pid) + '&qty=' + qty
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && typeof data.count === 'number') {
                document.querySelectorAll('[data-global-cart-count]').forEach(function (b) {
                    b.textContent = String(data.count);
                    if (data.count > 0) { b.removeAttribute('hidden'); } else { b.setAttribute('hidden', ''); }
                });
            }
        }).catch(function () {});
    }

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
        cart[key] = { name: stepper.getAttribute('data-name'), price: parseInt(stepper.getAttribute('data-price'), 10) || 0, qty: parseInt(stepper.getAttribute('data-qty'), 10) || 0 };

        stepper.addEventListener('click', function (ev) {
            var inc = ev.target.closest && ev.target.closest('[data-qty-inc]');
            var dec = ev.target.closest && ev.target.closest('[data-qty-dec]');
            if (!inc && !dec) { return; }
            ev.preventDefault();
            var c = cart[key];
            c.qty = Math.max(0, Math.min(99, c.qty + (inc ? 1 : -1)));
            paint(stepper, c.qty);
            render();
            syncServer(stepper.getAttribute('data-id'), c.qty);
            if (inc) { showToast(menu.getAttribute('data-added-label')); }
        });
    });
    render();

    // Boutique : « passer à la caisse » poste le panier vers une page dédiée.
    // Restaurant : pas de caisse → on déplie le formulaire en ligne ([data-cart-form]).
    var caisseForm = document.querySelector('[data-caisse-form]');

    function currentItems() {
        var out = [];
        Object.keys(cart).forEach(function (k) {
            if (cart[k].qty > 0) { var p = k.split('|'); out.push({ id: p[0], size: p[1], qty: cart[k].qty }); }
        });
        return out;
    }
    function scrollToProducts() {
        var grid = document.querySelector('.product-grid') || menu;
        if (grid) { grid.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
    function submitCaisse(items) {
        var hidden = caisseForm.querySelector('[data-cart-json]');
        if (hidden) { hidden.value = JSON.stringify(items); }
        caisseForm.submit();
    }
    // Ouvre le panier : caisse (boutique) ou formulaire en ligne (restaurant).
    function openCheckout() {
        var items = currentItems();
        if (caisseForm) {
            if (items.length === 0) { scrollToProducts(); return; }
            submitCaisse(items);
            return;
        }
        if (items.length > 0 && form) {
            form.hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            var n = form.querySelector('#cl-name');
            if (n) { setTimeout(function () { n.focus(); }, 350); }
        } else {
            scrollToProducts();
        }
    }
    if (bar) {
        var go = bar.querySelector('[data-cart-checkout]');
        if (go) { go.addEventListener('click', openCheckout); }
    }
    document.querySelectorAll('[data-cart-open]').forEach(function (b) {
        b.addEventListener('click', openCheckout);
    });

    // « Acheter » (achat express) : ouvre la caisse directement avec ce seul produit.
    document.querySelectorAll('[data-buy-now]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-buy-now');
            // Vente au mètre : on poste directement {id couleur, qty:1, longueur en cm}.
            var croot = document.querySelector('[data-cart-root]');
            if (croot && croot.getAttribute('data-sale-unit') === 'meter') {
                var li = document.querySelector('[data-meter-length]');
                var m = li ? parseFloat((li.value || '').replace(',', '.')) : 0;
                if (!m || m < 0.5) { if (li) { li.focus(); } return; }
                var cf = document.querySelector('[data-caisse-form]');
                var hid = cf ? cf.querySelector('[data-cart-json]') : null;
                if (cf && hid) { hid.value = JSON.stringify([{ id: id, size: '', qty: 1, len: Math.round(m * 100) }]); cf.submit(); }
                return;
            }
            if (caisseForm) { submitCaisse([{ id: id, size: '', qty: 1 }]); return; }
            var key = id + '|';
            if (cart[key]) {
                if (cart[key].qty < 1) { cart[key].qty = 1; }
                var stepper = document.querySelector('[data-order-item][data-id="' + id + '"][data-size=""]');
                if (stepper) { paint(stepper, cart[key].qty); }
                render();
            }
            openCheckout();
        });
    });

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

/* ---- Convention réutilisable : sélecteur d'indicatif téléphonique ----
   Tout <select class="dial-select"> affiche EN PREMIER le pays détecté (option
   pré-sélectionnée + remontée en tête de liste). Un indicatif verrouillé côté
   serveur (champ caché #<id>_value) est mis à jour et reste verrouillé ; un
   indicatif éditable reste éditable (le numéro peut appartenir à un autre pays).
   Appelé par les DEUX flux de géoloc (inscription + boutons [data-geolocate]),
   donc tout futur champ téléphone n'a qu'à porter class="dial-select". */
function prioritizeDial(iso) {
    iso = (iso || '').toUpperCase();
    if (!iso) { return; }
    document.querySelectorAll('select.dial-select').forEach(function (dial) {
        if (dial.dataset.unlocked === '1') { return; }
        var opt = dial.querySelector('option[value="' + iso + '"]');
        if (!opt) { return; }
        var hidden = dial.id ? document.getElementById(dial.id + '_value') : null;
        if (hidden) { hidden.value = iso; dial.value = iso; return; } // verrouillé : maj valeur + champ caché
        dial.value = iso;
        if (dial.firstElementChild !== opt) { dial.insertBefore(opt, dial.firstElementChild); } // remonter en tête
    });
}

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
            // Indicatif téléphonique : pays détecté en tête (convention .dial-select).
            prioritizeDial(geo.country_code);
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
            // un champ caché porte le pays à sa place. On NE reverrouille PAS un
            // champ que le vendeur a explicitement rouvert (lien « Modifier »).
            var manuallyUnlocked = (city && city.dataset.unlocked === '1') || (country && country.dataset.unlocked === '1');
            if (btn.getAttribute('data-geo-lock') === '1' && !manuallyUnlocked) {
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

/* ---- Fiche produit boutique : zoom plein écran (lightbox) ----
   La grande photo s'ouvre en plein écran ; flèches/Échap pour naviguer/fermer.
   Relit data-photos À L'OUVERTURE pour refléter un changement de galerie (photos
   par couleur), et suit l'index des vignettes par DÉLÉGATION (survit au remplacement
   des vignettes lorsqu'on change de couleur). */
(function () {
    var gallery = document.querySelector('[data-gallery]');
    if (!gallery) { return; }
    var zoomBtn = gallery.querySelector('[data-zoom-open]');
    if (!zoomBtn) { return; }
    function getPhotos() { try { return JSON.parse(gallery.getAttribute('data-photos') || '[]'); } catch (e) { return []; } }
    gallery.addEventListener('click', function (ev) {
        var th = ev.target && ev.target.closest ? ev.target.closest('.thumb[data-index]') : null;
        if (th) { zoomBtn.setAttribute('data-index', th.getAttribute('data-index') || '0'); }
    });
    var ov, imgEl, photos = [], current = 0;
    function show(i) { if (!photos.length) { return; } current = ((i % photos.length) + photos.length) % photos.length; if (imgEl) { imgEl.src = photos[current]; } }
    function close() { if (ov) { ov.classList.remove('is-open'); document.body.style.overflow = ''; } }
    function open() {
        photos = getPhotos();
        if (!photos.length) { return; }
        if (!ov) {
            ov = document.createElement('div');
            ov.className = 'lightbox';
            ov.innerHTML = '<button class="lightbox-close" type="button" aria-label="Fermer">×</button>'
                + '<button class="lightbox-nav lightbox-prev" type="button" aria-label="Précédent">‹</button>'
                + '<img class="lightbox-img" alt="">'
                + '<button class="lightbox-nav lightbox-next" type="button" aria-label="Suivant">›</button>';
            document.body.appendChild(ov);
            imgEl = ov.querySelector('.lightbox-img');
            ov.addEventListener('click', function (e) {
                var t = e.target;
                if (t === ov || t.classList.contains('lightbox-close')) { close(); }
                else if (t.classList.contains('lightbox-next')) { show(current + 1); }
                else if (t.classList.contains('lightbox-prev')) { show(current - 1); }
            });
        }
        show(parseInt(zoomBtn.getAttribute('data-index'), 10) || 0);
        ov.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    zoomBtn.addEventListener('click', open);
    document.addEventListener('keydown', function (e) {
        if (!ov || !ov.classList.contains('is-open')) { return; }
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowRight') { show(current + 1); }
        else if (e.key === 'ArrowLeft') { show(current - 1); }
    });
})();

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

/* ---- Caisse : champ obligatoire selon le mode choisi (ex. adresse pour la
   livraison). L'input porte data-require-radio (nom du groupe de boutons) et
   data-require-when (valeurs déclenchant l'obligation). ---- */
(function () {
    document.querySelectorAll('[data-require-when]').forEach(function (input) {
        var vals = (input.getAttribute('data-require-when') || '').split(',');
        var name = input.getAttribute('data-require-radio') || '';
        var radios = document.querySelectorAll('input[name="' + name + '"]');
        if (!radios.length) { return; }
        function update() {
            var sel = document.querySelector('input[name="' + name + '"]:checked');
            // Un transporteur choisi (data-carrier) = une expédition → adresse requise.
            input.required = !!(sel && (vals.indexOf(sel.value) !== -1 || sel.hasAttribute('data-carrier')));
        }
        radios.forEach(function (r) { r.addEventListener('change', update); });
        update();
    });
})();

/* ---- Caisse boutique : frais de livraison mis à jour selon le mode choisi ---- */
(function () {
    var box = document.querySelector('[data-ship-calc]');
    if (!box) { return; }
    var subtotal = parseInt(box.getAttribute('data-subtotal'), 10) || 0;
    var curInt = box.getAttribute('data-cur-int') === '1';
    var sym = box.getAttribute('data-cur-sym') || '';
    var shipEl = box.querySelector('[data-ship-amount]');
    var grandEl = box.querySelector('[data-grand-total]');
    var radios = document.querySelectorAll('input[name="fulfillment"]');
    if (!grandEl || !radios.length) { return; }
    // Zones de livraison : si définies, le tarif d'un mode expédié vient de la
    // zone du pays de destination (franco par zone). Le serveur fait foi ; ceci
    // n'est que l'affichage du récap qui s'ajuste quand l'acheteur change de pays.
    var destSel = document.querySelector('[data-dest-country]');
    var zones = [];
    try { zones = JSON.parse(box.getAttribute('data-zones') || '[]') || []; } catch (e) { zones = []; }
    // Équivalent dans la devise de l'acheteur (≈) : taux embarqué (centimes acheteur / centime boutique).
    var fxRate = parseFloat(box.getAttribute('data-fx-rate')) || 0;
    var fxInt = box.getAttribute('data-fx-int') === '1';
    var fxSym = box.getAttribute('data-fx-sym') || '';
    var approxEl = box.querySelector('[data-grand-approx]');
    function fmtFx(cents) {
        var tc = Math.round(cents * fxRate);
        var v = fxInt ? Math.round(tc / 100) : tc / 100;
        return '≈ ' + new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: fxInt ? 0 : 2 }).format(v) + ' ' + fxSym;
    }
    function zoneFee(cc) {
        if (!zones.length) { return null; }
        var match = null, catchAll = null;
        for (var i = 0; i < zones.length; i++) {
            var z = zones[i];
            if (!z.c || !z.c.length) { if (!catchAll) { catchAll = z; } continue; }
            if (cc && z.c.indexOf(cc) !== -1) { match = z; break; }
        }
        var z2 = match || catchAll;
        if (!z2) { return null; } // pays non couvert → non livrable (le serveur bloque)
        if (z2.tiers && z2.tiers.length) { // paliers par montant (triés croissants)
            var tf = z2.fee || 0;
            for (var j = 0; j < z2.tiers.length; j++) { if (subtotal >= z2.tiers[j].min) { tf = z2.tiers[j].fee; } }
            return tf;
        }
        return (z2.free > 0 && subtotal >= z2.free) ? 0 : (z2.fee || 0);
    }
    function fmt(c) {
        var v = curInt ? Math.round(c / 100) : c / 100;
        return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: curInt ? 0 : 2 }).format(v) + ' ' + sym;
    }
    function update() {
        var sel = document.querySelector('input[name="fulfillment"]:checked');
        var method = sel ? sel.value : '';
        var fee = sel ? (parseInt(sel.getAttribute('data-fee'), 10) || 0) : 0;
        if (zones.length && (method === 'local' || method === 'international') && destSel) {
            var zf = zoneFee((destSel.value || '').toUpperCase());
            if (zf !== null) { fee = zf; }
        }
        if (shipEl) { shipEl.textContent = fee > 0 ? fmt(fee) : (shipEl.getAttribute('data-free') || fmt(0)); }
        grandEl.textContent = fmt(subtotal + fee);
        if (approxEl && fxRate > 0) { approxEl.textContent = fmtFx(subtotal + fee); }
    }
    radios.forEach(function (r) { r.addEventListener('change', update); });
    if (destSel) { destSel.addEventListener('change', update); }
    update();
})();

/* ---- Assistant d'achat (chatbot règles + repli vendeur) ----
   Aucun JS inline (CSP stricte) ; le token CSRF est ajouté automatiquement aux
   requêtes fetch par le wrapper en tête de ce fichier. */
(function () {
    'use strict';
    var root = document.querySelector('[data-assistant]');
    if (!root) { return; }
    var panel    = root.querySelector('[data-assistant-panel]');
    var toggle   = root.querySelector('[data-assistant-toggle]');
    var closeBtn = root.querySelector('[data-assistant-close]');
    var log      = root.querySelector('[data-assistant-log]');
    var suggest  = root.querySelector('[data-assistant-suggest]');
    var form     = root.querySelector('[data-assistant-form]');
    var input    = root.querySelector('[data-assistant-input]');
    var endpoint = root.getAttribute('data-endpoint');
    var waLink   = root.getAttribute('data-wa') || '';
    var waLabel  = root.getAttribute('data-wa-label') || 'WhatsApp';
    var errText  = root.getAttribute('data-err') || 'Error';
    var thinking = root.getAttribute('data-thinking') || '…';
    var busy = false;

    function openPanel(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && input) { input.focus(); }
    }
    function addMsg(cls, text) {
        var d = document.createElement('div');
        d.className = 'assistant-msg ' + cls;
        d.textContent = text;            // textContent : neutralise tout HTML
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function addWa() {
        if (!waLink) { return; }
        var a = document.createElement('a');
        a.className = 'assistant-wa';
        a.href = waLink; a.target = '_blank'; a.rel = 'noopener';
        a.textContent = '🟢 ' + waLabel;
        log.appendChild(a);
        log.scrollTop = log.scrollHeight;
    }
    function renderSuggest(list) {
        if (!suggest) { return; }
        suggest.innerHTML = '';
        (list || []).forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'assistant-chip';
            b.textContent = s;
            b.addEventListener('click', function () { ask(s); });
            suggest.appendChild(b);
        });
    }
    function ask(text) {
        text = (text || '').trim();
        if (!text || busy) { return; }
        busy = true;
        addMsg('user', text);
        var wait = addMsg('bot thinking', thinking);
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'question=' + encodeURIComponent(text)
        }).then(function (r) { return r.json(); }).then(function (data) {
            wait.remove();
            addMsg('bot', (data && data.text) ? data.text : errText);
            if (data && data.handoff) { addWa(); }
            if (data && data.suggestions) { renderSuggest(data.suggestions); }
        }).catch(function () {
            wait.remove();
            addMsg('bot', errText);
        }).then(function () { busy = false; });
    }

    toggle.addEventListener('click', function () { openPanel(panel.hidden); });
    if (closeBtn) { closeBtn.addEventListener('click', function () { openPanel(false); }); }
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            ask(input.value);
            input.value = '';
        });
    }
    root.querySelectorAll('[data-assistant-q]').forEach(function (chip) {
        chip.addEventListener('click', function () { ask(chip.textContent); });
    });
})();

/* ---- Galerie produit : zoom à la loupe au survol (desktop, pointeur fin) ----
   Aucun JS inline (CSP) ; on ne fait que déplacer transform-origin selon le
   curseur. Le clic ouvre toujours le plein écran (lightbox). */
(function () {
    'use strict';
    if (!window.matchMedia || !window.matchMedia('(pointer:fine)').matches) { return; }
    document.querySelectorAll('[data-zoom-hover]').forEach(function (box) {
        var img = box.querySelector('img');
        if (!img) { return; }
        box.addEventListener('mouseenter', function () { box.classList.add('is-zooming'); });
        box.addEventListener('mouseleave', function () {
            box.classList.remove('is-zooming');
            img.style.transformOrigin = 'center center';
        });
        box.addEventListener('mousemove', function (e) {
            var r = box.getBoundingClientRect();
            if (!r.width || !r.height) { return; }
            var x = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
            var y = Math.max(0, Math.min(100, ((e.clientY - r.top) / r.height) * 100));
            img.style.transformOrigin = x + '% ' + y + '%';
        });
    });
})();

/* ---- Liste de souhaits : bascule instantanée du cœur (fetch, CSP-safe) ----
   Le wrapper fetch ajoute le token CSRF ; le serveur répond en JSON. Repli sur
   l'envoi natif du formulaire en cas d'échec. */
(function () {
    'use strict';
    function setCount(n) {
        document.querySelectorAll('[data-wish-count]').forEach(function (b) {
            b.textContent = String(n);
            if (n > 0) { b.removeAttribute('hidden'); } else { b.setAttribute('hidden', ''); }
        });
    }
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.matches || !form.matches('[data-wish-form]')) { return; }
        if (form.closest('.wish-page')) { return; } // sur /favoris : submit natif (recharge, retire la carte)
        ev.preventDefault();
        var btn = form.querySelector('[data-wish]');
        fetch(form.getAttribute('action'), { method: 'POST', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data) { return; }
                if (btn) {
                    btn.classList.toggle('is-wished', !!data.wished);
                    btn.setAttribute('aria-pressed', data.wished ? 'true' : 'false');
                }
                if (typeof data.count === 'number') { setCount(data.count); }
            })
            .catch(function () { form.submit(); });
    });
})();

/* ---- Comparateur : bascule instantanée du bouton ⇄ (fetch, CSP-safe) ---- */
(function () {
    'use strict';
    function setCount(n) {
        document.querySelectorAll('[data-compare-count]').forEach(function (b) {
            b.textContent = String(n);
            if (n > 0) { b.removeAttribute('hidden'); } else { b.setAttribute('hidden', ''); }
        });
    }
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.matches || !form.matches('[data-compare-form]')) { return; }
        if (form.closest('.compare-page')) { return; } // sur /comparer : submit natif (recharge, retire la colonne)
        ev.preventDefault();
        var btn = form.querySelector('[data-compare]');
        fetch(form.getAttribute('action'), { method: 'POST', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data) { return; }
                if (btn) {
                    btn.classList.toggle('is-comparing', !!data.comparing);
                    btn.setAttribute('aria-pressed', data.comparing ? 'true' : 'false');
                }
                if (typeof data.count === 'number') { setCount(data.count); }
            })
            .catch(function () { form.submit(); });
    });
})();

/* ---- Menus déroulants d'en-tête : aperçu chargé au clic (fetch, CSP-safe) ----
   Repli sans JS = le lien mène à la page complète. */
(function () {
    'use strict';
    var toggles = document.querySelectorAll('[data-dd-toggle]');
    if (!toggles.length) { return; }
    function closeAll(except) {
        document.querySelectorAll('[data-dd]').forEach(function (dd) {
            var panel = dd.querySelector('[data-dd-panel]');
            if (panel && panel !== except) {
                panel.hidden = true;
                var t = dd.querySelector('[data-dd-toggle]');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
    }
    toggles.forEach(function (toggle) {
        var dd = toggle.closest('[data-dd]');
        var panel = dd ? dd.querySelector('[data-dd-panel]') : null;
        var body = panel ? panel.querySelector('[data-dd-body]') : null;
        var url = toggle.getAttribute('data-dd-url');
        if (!panel) { return; }
        toggle.addEventListener('click', function (ev) {
            if (ev.ctrlKey || ev.metaKey || ev.shiftKey) { return; } // laisse ouvrir dans un onglet
            ev.preventDefault();
            var willOpen = panel.hidden;
            closeAll(willOpen ? panel : null);
            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen && url && body) {
                fetch(url, { headers: { 'Accept': 'text/html' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) { body.innerHTML = html; })
                    .catch(function () {});
            }
        });
    });
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest || !ev.target.closest('[data-dd]')) { closeAll(null); }
    });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closeAll(null); } });
})();

/* ---- Éditeur de variantes produit : ajouter / retirer une ligne ---- */
document.addEventListener('click', function (ev) {
    if (!ev.target || !ev.target.closest) { return; }
    var add = ev.target.closest('[data-variant-add]');
    if (add) {
        var tpl = document.getElementById('variant-template');
        var rows = document.querySelector('[data-variant-rows]');
        if (tpl && tpl.content && rows) {
            rows.appendChild(tpl.content.cloneNode(true));
            rows.dispatchEvent(new CustomEvent('al:variant-added'));
            var input = rows.lastElementChild ? rows.lastElementChild.querySelector('input') : null;
            if (input) { input.focus(); }
        }
        return;
    }
    var del = ev.target.closest('[data-variant-del]');
    if (del) {
        var row = del.closest('.variant-row');
        if (row && row.parentNode) { row.parentNode.removeChild(row); }
    }
});

/* ---- Le rayon pilote l'axe de déclinaison (taille → stockage, contenance, teinte, pointure…) ---- */
/* Le formulaire produit s'adapte au Rayon/Catégorie choisi ; pour le prêt-à-porter, la      */
/* catégorie de vêtement affine encore les tailles (soutien-gorge ≠ pantalon ≠ chaussure).   */
(function () {
    var rows = document.querySelector('[data-variant-rows]');
    if (!rows) { return; }
    var coll = document.querySelector('[data-collection-select]');
    var gar  = document.querySelector('[data-garment-select]');
    var dl   = document.getElementById('size-suggest');
    var head = rows.querySelector('[data-axis-label]');
    var sBox   = document.querySelector('[data-axis-suggest]');
    var sChips = document.querySelector('[data-axis-suggest-chips]');
    var sLabel = document.querySelector('[data-axis-suggest-label]');
    // 2ᵉ axe nommable (couleur par défaut) : rend possible « double couleur » / « double capacité ».
    var head2 = rows.querySelector('[data-axis2-label]');
    var axis2Inp = document.querySelector('[data-variant-axis2]');
    if (axis2Inp && head2) {
        var bindAxis2 = function () {
            var v = (axis2Inp.value || '').trim();
            head2.textContent = v || head2.getAttribute('data-default') || head2.textContent;
        };
        axis2Inp.addEventListener('input', bindAxis2);
        bindAxis2();
    }
    var axes = {}, sizeMap = {};
    try { axes = JSON.parse(rows.getAttribute('data-axes') || '{}'); } catch (e) { axes = {}; }
    try { sizeMap = JSON.parse(rows.getAttribute('data-size-map') || '{}'); } catch (e) { sizeMap = {}; }
    var baseLabel = rows.getAttribute('data-base-label') || 'Option';
    var basePh    = rows.getAttribute('data-base-ph') || '';
    var baseOpts  = [];
    try { baseOpts = JSON.parse(rows.getAttribute('data-base-opts') || '[]'); } catch (e) { baseOpts = []; }
    var teinteHex = {};
    try { teinteHex = JSON.parse(rows.getAttribute('data-teinte-hex') || '{}'); } catch (e) { teinteHex = {}; }

    function rayonAxis() {
        if (!coll) { return null; }
        var opt = coll.options[coll.selectedIndex];
        var key = opt ? (opt.getAttribute('data-axis') || '') : '';
        return (key && axes[key]) ? axes[key] : null;
    }
    function setSizeAttrs(label, ph) {
        if (head) { head.textContent = label; }
        var inputs = rows.querySelectorAll('input[name="var_size[]"]');
        Array.prototype.forEach.call(inputs, function (inp) {
            inp.setAttribute('placeholder', ph); inp.setAttribute('aria-label', label);
        });
        var tplInp = document.querySelector('#variant-template input[name="var_size[]"]');
        if (tplInp) { tplInp.setAttribute('placeholder', ph); tplInp.setAttribute('aria-label', label); }
    }
    function setSuggest(list) {
        if (!dl) { return; }
        dl.innerHTML = '';
        (list || []).forEach(function (s) { var o = document.createElement('option'); o.value = s; dl.appendChild(o); });
    }
    // Pastilles cliquables : rendent les suggestions VISIBLES (et pas seulement dans la
    // liste déroulante du champ). Un clic remplit une déclinaison.
    function setChips(list, label) {
        if (!sBox || !sChips) { return; }
        if (sLabel) { sLabel.textContent = label; }
        sChips.innerHTML = '';
        (list || []).forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-axis-chip', ''); b.setAttribute('data-val', s);
            // Pastille couleur pour les teintes (maquillage) : swatch déduit du nom.
            if (teinteHex[s]) {
                var dot = document.createElement('span');
                dot.className = 'chip-dot'; dot.style.background = teinteHex[s];
                b.appendChild(dot);
            }
            b.appendChild(document.createTextNode(s));
            sChips.appendChild(b);
        });
        sBox.hidden = !(list && list.length);
    }
    function refresh() {
        var axis = rayonAxis();
        var label = axis ? axis.label : baseLabel;
        var ph    = axis ? axis.label : basePh;
        var opts  = axis ? (axis.opts || []) : baseOpts;
        // Prêt-à-porter : si une catégorie de vêtement précise est choisie, ses tailles priment.
        if (gar) {
            var gopt = gar.options[gar.selectedIndex];
            var sys = gopt ? gopt.getAttribute('data-size-system') : '';
            if (sys && sizeMap[sys]) { opts = sizeMap[sys]; }
            else if (!axis && sizeMap.alpha) { opts = sizeMap.alpha; }
        }
        setSizeAttrs(label, ph);
        setSuggest(opts);
        setChips(opts, label);
    }
    // Clic sur une pastille : remplit la 1ʳᵉ déclinaison à taille vide, sinon en crée une.
    function fillSize(val) {
        var inputs = rows.querySelectorAll('input[name="var_size[]"]');
        var target = null;
        for (var i = 0; i < inputs.length; i++) { if (!inputs[i].value.trim()) { target = inputs[i]; break; } }
        if (!target) {
            var tpl = document.getElementById('variant-template');
            if (tpl && tpl.content) {
                rows.appendChild(tpl.content.cloneNode(true));
                rows.dispatchEvent(new CustomEvent('al:variant-added'));
                var last = rows.lastElementChild;
                target = last ? last.querySelector('input[name="var_size[]"]') : null;
            }
        }
        if (target) { target.value = val; target.dispatchEvent(new Event('input', { bubbles: true })); target.focus(); }
    }
    if (sChips) {
        sChips.addEventListener('click', function (ev) {
            var chip = ev.target && ev.target.closest ? ev.target.closest('[data-axis-chip]') : null;
            if (chip) { ev.preventDefault(); fillSize(chip.getAttribute('data-val') || chip.textContent); }
        });
    }
    if (coll) {
        coll.addEventListener('change', function () {
            refresh();
            // Révèle la section Déclinaisons pour que le vendeur voie tout de suite les suggestions.
            var d = rows.closest('details');
            if (d && sBox && !sBox.hidden) { d.open = true; }
        });
    }
    if (gar)  { gar.addEventListener('change', refresh); }
    rows.addEventListener('al:variant-added', refresh);
    refresh();
})();

/* ---- Beauté v2 : formulaire ADAPTATIF au type de produit + aperçu (CSP-safe) ---- */
(function () {
    var cfgEl = document.querySelector('[data-beauty]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(attr) { try { return JSON.parse(cfgEl.getAttribute(attr) || 'null') || {}; } catch (e) { return {}; } }
    var TYPES = parse('data-types'), FIELDS = parse('data-fields'), PALETTES = parse('data-palettes'), AXES = parse('data-axes');
    var NUANCES = []; try { NUANCES = JSON.parse(cfgEl.getAttribute('data-nuances') || '[]') || []; } catch (e) { NUANCES = []; }

    var typeSel = document.getElementById('p-ptype');
    var unitSel = document.getElementById('p-unit');
    var coll    = document.querySelector('[data-collection-select]');
    var attrsBox = document.querySelector('[data-beauty-attrs]');
    var chipsBox = document.querySelector('[data-beauty-chips-box]');
    var chips    = document.querySelector('[data-beauty-chips]');
    var rowsBox  = document.querySelector('[data-beauty-rows]');
    var tpl      = document.getElementById('bvariant-template');
    var ongRows  = document.querySelector('[data-ong-rows]');
    var ongTpl   = document.getElementById('ong-variant-template');
    var parRows  = document.querySelector('[data-par-rows]');
    var parTpl   = document.getElementById('par-variant-template');
    var parChips = document.querySelector('[data-par-chips]');
    var perrRows = document.querySelector('[data-perr-rows]');
    var perrTpl  = document.getElementById('perr-variant-template');
    var perrChips = document.querySelector('[data-perr-chips]');
    var perrBox  = document.querySelector('[data-perr]');
    var perHairSel = document.getElementById('per-hair');
    var perTypeSel = document.getElementById('per-type');
    var PERR_HUMAN = perrBox ? (perrBox.getAttribute('data-human-type') || '') : '';
    var PERR_LACE = []; try { PERR_LACE = perrBox ? JSON.parse(perrBox.getAttribute('data-lace-types') || '[]') : []; } catch (e) { PERR_LACE = []; }
    var ONGHEX = {}; try { ONGHEX = JSON.parse(cfgEl.getAttribute('data-ongles-hex') || '{}'); } catch (e) { ONGHEX = {}; }
    // Soins (corps / visage) : adaptatif au type (comme le maquillage) + actifs + conformité.
    var SOINS = parse('data-soins'); // { corps:{...}, visage:{...}, pao:[...] }
    var soinsTypeSel = document.getElementById('soins-type');
    var soinsUnitSel = document.getElementById('soins-unit');
    var soinsAttrsBox = document.querySelector('[data-soins-attrs]');
    var soinsRows = document.querySelector('[data-soins-rows]');
    var soinsTpl  = document.getElementById('soins-variant-template');
    var soinsChips = document.querySelector('[data-soins-chips]');
    var soinsActifsChips = document.querySelector('[data-soins-actifs-chips]');
    var soinsAtoutsChips = document.querySelector('[data-soins-atouts-chips]');
    function soinsKind() { return (coll && coll.value === 'Soins visage') ? 'visage' : 'corps'; }
    function soinsCfg() { return SOINS[soinsKind()] || {}; }
    // « Autre / nouveau rayon » : formulaire générique adaptatif (specs libres, axe libre).
    var AUTRE = parse('data-autre');
    var collOther = document.querySelector('[data-collection-other]');
    var autreSpecsBox = document.querySelector('[data-autre-specs]');
    var autreSpecTpl = document.getElementById('autre-spec-template');
    var autreRowsBox = document.querySelector('[data-autre-rows]');
    var autreVarTpl = document.getElementById('autre-variant-template');
    var SPECIALIZED = ['Maquillage', 'Parfums', 'Perruque', 'Ongles', 'Soins corps', 'Soins visage'];
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayon() {
        if (coll && coll.value === '__other__') { return collOther ? String(collOther.value || '').trim() : ''; }
        return coll ? coll.value : '';
    }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayon())] || null; }

    function isOngles() { return !!(coll && coll.value === 'Ongles'); }
    function isParfum() { return !!(coll && coll.value === 'Parfums'); }
    function isPerruque() { return !!(coll && coll.value === 'Perruque'); }
    function isSoins() { return !!(coll && (coll.value === 'Soins corps' || coll.value === 'Soins visage')); }
    function isAutre() {
        if (!coll) { return false; }
        if (coll.value === '__other__') { return true; }
        return coll.value !== '' && SPECIALIZED.indexOf(coll.value) === -1;
    }
    function activeSection() {
        if (isOngles()) { return 'ongles'; }
        if (isParfum()) { return 'parfum'; }
        if (isPerruque()) { return 'perruque'; }
        if (isSoins()) { return 'soins'; }
        if (coll && coll.value === 'Maquillage') { return 'maquillage'; }
        if (isAutre()) { return 'autre'; }
        return 'maquillage';
    }

    // Perruque : champs adaptatifs (qualité/origine si cheveux humains ; couleur de lace si lace wig).
    function refreshPerrAdaptive() {
        if (!perrBox) { return; }
        var human = perHairSel && perHairSel.value === PERR_HUMAN;
        var lace = perTypeSel && PERR_LACE.indexOf(perTypeSel.value) !== -1;
        perrBox.querySelectorAll('[data-perr-when]').forEach(function (b) {
            var on = (b.getAttribute('data-perr-when') === 'human') ? human : lace;
            b.hidden = !on;
            b.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
        });
    }

    // Affiche la section (maquillage / ongles / parfum) du rayon ; désactive les champs
    // des sections masquées pour qu'ils ne soient pas envoyés (pas de conflit).
    function toggleSections() {
        var active = activeSection();
        document.querySelectorAll('[data-beauty-section]').forEach(function (sec) {
            var on = sec.getAttribute('data-beauty-section') === active;
            sec.hidden = !on;
            sec.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
        });
        refreshPerrAdaptive();
    }

    function meta() { return (typeSel && TYPES[typeSel.value]) ? TYPES[typeSel.value] : null; }

    // Déclinaisons proposées : palette du type, sinon repli sur l'axe du rayon (Longueur, Contenance…).
    function declItems() {
        var m = meta();
        if (m && m.decl && PALETTES[m.decl]) {
            return PALETTES[m.decl].map(function (r) { return { name: r[0], hex: r[1] || '', nuance: r[2] || '' }; });
        }
        if (coll) {
            var opt = coll.options[coll.selectedIndex];
            var ax = opt ? (opt.getAttribute('data-axis') || '') : '';
            if (ax && AXES[ax] && (AXES[ax].opts || []).length) {
                return AXES[ax].opts.map(function (o) { return { name: o, hex: '', nuance: '' }; });
            }
        }
        return [];
    }
    function hasNuance() { var m = meta(); return !!(m && m.decl === 'teinte'); }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta();
        if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = FIELDS[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }

    function buildChips() {
        if (!chips) { return; }
        var items = declItems();
        chips.innerHTML = '';
        items.forEach(function (it) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'axis-chip'; b.setAttribute('data-bchip', '');
            b.setAttribute('data-name', it.name); b.setAttribute('data-hex', it.hex); b.setAttribute('data-nuance', it.nuance);
            if (it.hex) { var d = document.createElement('span'); d.className = 'chip-dot'; d.style.background = it.hex; b.appendChild(d); }
            b.appendChild(document.createTextNode(it.name));
            chips.appendChild(b);
        });
        if (chipsBox) { chipsBox.hidden = items.length === 0; }
    }

    function applyNuanceCol() {
        if (rowsBox) { rowsBox.classList.toggle('has-nuance', hasNuance()); }
        var hint = document.querySelector('[data-beauty-nuance-hint]');
        if (hint) { hint.hidden = !hasNuance(); }
    }

    function addRow(it) {
        if (!tpl || !tpl.content || !rowsBox) { return null; }
        rowsBox.appendChild(tpl.content.cloneNode(true));
        var row = rowsBox.lastElementChild;
        if (row && it) {
            var nm = row.querySelector('input[name="var_name[]"]'); if (nm) { nm.value = it.name || ''; }
            var hx = row.querySelector('input[name="var_hex[]"]'); if (hx && it.hex) { hx.value = it.hex; }
            var nz = row.querySelector('select[name="var_nuance[]"]'); if (nz && it.nuance) { nz.value = it.nuance; }
        }
        return row;
    }
    // Clic sur une suggestion : remplit une ligne vide, sinon en crée une.
    function fillDecl(it) {
        var empty = null;
        rowsBox.querySelectorAll('input[name="var_name[]"]').forEach(function (inp) { if (!empty && !inp.value.trim()) { empty = inp; } });
        if (empty) {
            var row = empty.closest('.bvariant-row');
            empty.value = it.name || '';
            var hx = row.querySelector('input[name="var_hex[]"]'); if (hx && it.hex) { hx.value = it.hex; }
            var nz = row.querySelector('select[name="var_nuance[]"]'); if (nz && it.nuance) { nz.value = it.nuance; }
        } else { addRow(it); }
        update();
    }

    function setText2(sel, txt) { document.querySelectorAll(sel).forEach(function (el) { el.textContent = txt; }); }

    function onType() {
        var m = meta();
        if (m && unitSel && m.unit) { unitSel.value = m.unit; }
        buildAttrs(); buildChips(); applyNuanceCol();
        var label = (m && m.decl_label) ? m.decl_label : 'Couleurs';
        setText2('[data-beauty-decl-title]', label);
        setText2('[data-beauty-decl-label]', label);
        var sh = document.querySelector('[data-beauty-specs-hint]');
        if (sh) { sh.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || sh.textContent) : (cfgEl.getAttribute('data-hint-pick') || sh.textContent); }
        var d = document.querySelector('[data-beauty-decl]'); if (d) { d.open = true; }
        update();
    }

    /* ---------- Soins (corps / visage) : adaptatif au type + actifs + conformité ---------- */
    function soinsMeta() { var t = soinsCfg().types || {}; return (soinsTypeSel && t[soinsTypeSel.value]) ? t[soinsTypeSel.value] : null; }
    function buildSoinsAttrs() {
        if (!soinsAttrsBox) { return; }
        var fields = soinsCfg().fields || {};
        var prev = {};
        soinsAttrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        soinsAttrsBox.innerHTML = '';
        var m = soinsMeta();
        if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); soinsAttrsBox.appendChild(wrap);
        });
    }
    // Conformité : true si le type le force (corps) OU si le champ « warn_field » vaut « warn_value » (visage).
    function soinsWarnOn() {
        var m = soinsMeta(); if (!m) { return false; }
        if (m.warn) { return true; }
        var cfg = soinsCfg();
        if (cfg.warn_field && soinsAttrsBox) {
            var sel = soinsAttrsBox.querySelector('select[name="attr[' + cfg.warn_field + ']"]');
            return !!(sel && sel.value === cfg.warn_value);
        }
        return false;
    }
    function refreshSoinsWarn() { var w = document.querySelector('[data-soins-warn]'); if (w) { w.hidden = !soinsWarnOn(); } }
    // Reconstruit les listes du rayon de soins courant (corps ↔ visage) : type, actifs, atouts, tailles.
    function rebuildSoins() {
        var cfg = soinsCfg();
        if (soinsTypeSel && cfg.types) {
            var cur = soinsTypeSel.value;
            soinsTypeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = soinsTypeSel.getAttribute('data-any') || '—'; soinsTypeSel.appendChild(o0);
            var groups = cfg.groups || {};
            Object.keys(groups).forEach(function (gk) {
                var og = document.createElement('optgroup'); og.label = groups[gk];
                Object.keys(cfg.types).forEach(function (tn) {
                    if ((cfg.types[tn].group || '') !== gk) { return; }
                    var op = document.createElement('option'); op.value = tn; op.textContent = tn;
                    if (tn === cur) { op.selected = true; }
                    og.appendChild(op);
                });
                soinsTypeSel.appendChild(og);
            });
            if (soinsTypeSel.value !== cur) { soinsTypeSel.value = ''; } // type invalide pour ce rayon
        }
        function fillChecks(box, list, name) {
            if (!box) { return; }
            box.innerHTML = '';
            (list || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = name; inp.value = v;
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); box.appendChild(lab);
            });
        }
        fillChecks(soinsActifsChips, cfg.actifs, 'soins_actif[]');
        fillChecks(soinsAtoutsChips, cfg.atouts, 'atouts[]');
        if (soinsChips) {
            soinsChips.innerHTML = '';
            (cfg.tailles || []).forEach(function (v) {
                var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
                b.setAttribute('data-soins-chip', ''); b.setAttribute('data-val', v); b.textContent = v;
                soinsChips.appendChild(b);
            });
        }
        onSoinsType();
    }
    function onSoinsType() {
        var m = soinsMeta();
        if (m && soinsUnitSel && m.unit) { soinsUnitSel.value = m.unit; }
        buildSoinsAttrs();
        refreshSoinsWarn();
        var act = document.querySelector('[data-soins-actifs-box]'); if (act) { act.hidden = !(m && m.actifs); }
        var sh = document.querySelector('[data-soins-hint]');
        if (sh) { sh.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || sh.textContent) : (cfgEl.getAttribute('data-hint-pick') || sh.textContent); }
        var tag = document.querySelector('[data-soins-unit-tag]'); if (tag && soinsUnitSel) { tag.textContent = soinsUnitSel.value; }
        update();
    }
    function addSoinsRow(val) {
        if (!soinsTpl || !soinsTpl.content || !soinsRows) { return null; }
        soinsRows.appendChild(soinsTpl.content.cloneNode(true));
        var row = soinsRows.lastElementChild;
        if (row && val) { var s = row.querySelector('input[name="var_size[]"]'); if (s) { s.value = val; } }
        return row;
    }
    function fillSoinsSize(val) {
        var empty = null;
        soinsRows.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { if (!empty && !i.value.trim()) { empty = i; } });
        if (empty) { empty.value = val; } else { addSoinsRow(val); }
        update();
    }

    /* ---------- Autre / nouveau rayon : générique adaptatif ---------- */
    function setChecksHtmlList(box, list, name, checkedFn) {
        if (!box) { return; }
        box.innerHTML = '';
        (list || []).forEach(function (v) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = name; inp.value = v;
            if (checkedFn && checkedFn(v)) { inp.checked = true; }
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(inp); lab.appendChild(sp); box.appendChild(lab);
        });
    }
    function autreApplyColorCol() {
        var tog = document.querySelector('[data-autre-color-toggle]');
        if (autreRowsBox) { autreRowsBox.classList.toggle('has-color', !!(tog && tog.checked)); }
    }
    function autreAxisLabel() {
        var ax = document.querySelector('[data-autre-axis]');
        var lab = document.querySelector('[data-autre-axis-label]');
        if (lab) { lab.textContent = (ax && ax.value.trim()) ? ax.value.trim() : (cfgEl.getAttribute('data-opt') || 'Option'); }
    }
    function autreBuildSpecChips() {
        var box = document.querySelector('[data-autre-spec-chips]'); if (!box) { return; }
        var cfg = autreCfg();
        var list = cfg ? cfg.specs : (AUTRE.generic_specs || []);
        box.innerHTML = '';
        (list || []).forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            box.appendChild(b);
        });
    }
    function autreRefreshWarn() {
        var cfg = autreCfg();
        var type = cfg ? (cfg.warn || 'cosmetic') : 'cosmetic';
        var box = document.querySelector('[data-autre-warn]');
        var txt = document.querySelector('[data-autre-warn-text]');
        if (txt) { txt.textContent = (AUTRE.warn_texts || {})[type] || ''; }
        if (box) { box.hidden = (type === 'none'); }
    }
    function adaptAutre() {
        var cfg = autreCfg();
        var hint = document.querySelector('[data-autre-rayon-hint]');
        if (hint) {
            var r = autreRayon();
            hint.textContent = cfg ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', r)) : (cfgEl.getAttribute('data-autre-generic') || '');
        }
        if (cfg) {
            var ax = document.querySelector('[data-autre-axis]'); if (ax && !ax.value.trim()) { ax.value = cfg.axis || ''; }
            var u = document.getElementById('autre-unit'); if (u && cfg.unit) { u.value = cfg.unit; }
            var tag = document.querySelector('[data-autre-unit-tag]'); if (tag && cfg.unit) { tag.textContent = cfg.unit; }
            var tog = document.querySelector('[data-autre-color-toggle]'); if (tog) { tog.checked = !!cfg.color; }
        }
        autreBuildSpecChips(); autreRefreshWarn(); autreApplyColorCol(); autreAxisLabel(); update();
    }
    function addAutreSpec(label) {
        if (!autreSpecTpl || !autreSpecTpl.content || !autreSpecsBox) { return null; }
        autreSpecsBox.appendChild(autreSpecTpl.content.cloneNode(true));
        var row = autreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
        return row;
    }
    function addAutreVar() {
        if (!autreVarTpl || !autreVarTpl.content || !autreRowsBox) { return null; }
        autreRowsBox.appendChild(autreVarTpl.content.cloneNode(true));
        return autreRowsBox.lastElementChild;
    }
    function pushAutreAtout() {
        var inp = document.querySelector('[data-autre-atout-input]');
        var box = document.querySelector('[data-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = ''; update();
    }

    /* ---------- Aperçu fiche ---------- */
    var root = document.querySelector('[data-pv-root]');
    var curInt = root ? root.getAttribute('data-cur-int') === '1' : false;
    var cur = root ? (root.getAttribute('data-cur') || '') : '';
    function out(n) { return root ? root.querySelector('[data-pv-out="' + n + '"]') : null; }
    // Lit le champ [data-pv] ACTIF (non désactivé) : plusieurs sections partagent une clé.
    function fval(n) {
        var list = document.querySelectorAll('[data-pv="' + n + '"]');
        for (var i = 0; i < list.length; i++) { if (!list[i].disabled) { return String(list[i].value || '').trim(); } }
        return list[0] ? String(list[0].value || '').trim() : '';
    }
    function num(s) { return parseFloat(String(s).replace(',', '.')) || 0; }
    function fmt(n) { n = curInt ? Math.round(n) : Math.round(n * 100) / 100; return n.toLocaleString('fr-FR', curInt ? {} : { maximumFractionDigits: 2 }) + ' ' + cur; }
    function setText(n, t) { var el = out(n); if (el) { el.textContent = t; } }
    var nameOut = out('name'); var nameDefault = nameOut ? nameOut.textContent : '';
    var imgBox = root ? root.querySelector('[data-pv-img]') : null; var imgEmpty = imgBox ? imgBox.innerHTML : '';

    function update() {
        var tag = document.querySelector('[data-pv-unit]'); var unit = fval('unit') || 'ml';
        if (tag) { tag.textContent = unit; }
        if (!root) { return; }
        setText('brand', fval('brand'));
        if (nameOut) { nameOut.textContent = fval('name') || nameDefault; }
        if (isOngles()) {
            setText('type', fval('forme'));
            var ln = fval('longueur'), tp = fval('tips');
            setText('vol', (ln ? '· ' + ln : '') + (tp ? (ln ? ' · ' : '· ') + tp + ' capsules' : ''));
        } else if (isParfum()) {
            setText('type', fval('type'));
            var g = fval('genre'), pv = fval('volume'), fm = fval('famille');
            setText('vol', (g ? '· ' + g : '') + (pv ? ' · ' + pv + ' ml' : '') + (fm ? ' · ' + fm : ''));
        } else if (isPerruque()) {
            setText('type', fval('type'));
            var tx = fval('texture'), pl = fval('longueur'), ht = fval('hair');
            setText('vol', (tx ? '· ' + tx : '') + (pl ? ' · ' + pl + '"' : '') + (ht ? ' · ' + ht : ''));
        } else {
            setText('type', fval('type'));
            var vol = num(fval('volume'));
            setText('vol', vol > 0 ? ('· ' + vol + ' ' + unit) : '');
        }
        var price = num(fval('price')), promo = num(fval('promo'));
        var now = (promo > 0 && promo < price) ? promo : price;
        setText('price', now > 0 ? fmt(now) : '');
        var old = out('old'), badge = out('disc');
        if (promo > 0 && promo < price) {
            if (old) { old.textContent = fmt(price); old.hidden = false; }
            if (badge) { badge.textContent = '-' + Math.round((1 - promo / price) * 100) + '%'; badge.hidden = false; }
        } else { if (old) { old.hidden = true; } if (badge) { badge.hidden = true; } }
        var tones = root.querySelector('[data-pv-tones]');
        if (tones) {
            tones.innerHTML = '';
            if (isOngles()) {
                document.querySelectorAll('input[name="ong_couleur[]"]:checked').forEach(function (c) {
                    var dot = document.createElement('span'); dot.className = 'pv-tone'; dot.title = c.value;
                    dot.style.background = ONGHEX[c.value] || '#d8c3a8'; tones.appendChild(dot);
                });
            } else if (rowsBox) {
                rowsBox.querySelectorAll('.bvariant-row').forEach(function (r) {
                    var nm = r.querySelector('input[name="var_name[]"]'); if (!nm || !nm.value.trim()) { return; }
                    var hx = r.querySelector('input[name="var_hex[]"]');
                    var dot = document.createElement('span'); dot.className = 'pv-tone'; dot.title = nm.value.trim();
                    dot.style.background = hx ? hx.value : '#d8c3a8'; tones.appendChild(dot);
                });
            }
        }
        var stockF = document.getElementById('p-stock'); var s = stockF ? String(stockF.value || '').trim() : '';
        setText('stock', s !== '' ? (s + ' en stock') : 'Stock illimité');
        if (isSoins()) { refreshSoinsWarn(); } // visage : conformité selon la préoccupation
    }
    function syncPhoto() {
        if (!imgBox) { return; }
        var src = document.querySelector('#product-previews .preview:not(.preview-video) img');
        if (src && src.getAttribute('src')) {
            imgBox.innerHTML = ''; var img = document.createElement('img'); img.src = src.getAttribute('src'); img.alt = ''; imgBox.appendChild(img);
        } else if (!imgBox.querySelector('.pv-img-empty')) { imgBox.innerHTML = imgEmpty; }
    }

    // Ajoute une ligne de déclinaison faux-ongles (forme × longueur).
    function addOngRow() {
        if (!ongTpl || !ongTpl.content || !ongRows) { return null; }
        ongRows.appendChild(ongTpl.content.cloneNode(true));
        return ongRows.lastElementChild;
    }
    // Parfum : déclinaison par contenance (ml). Un clic sur une taille remplit/ajoute une ligne.
    function addParRow(val) {
        if (!parTpl || !parTpl.content || !parRows) { return null; }
        parRows.appendChild(parTpl.content.cloneNode(true));
        var row = parRows.lastElementChild;
        if (row && val) { var s = row.querySelector('input[name="var_size[]"]'); if (s) { s.value = val; } }
        return row;
    }
    function fillParSize(val) {
        var empty = null;
        parRows.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { if (!empty && !i.value.trim()) { empty = i; } });
        if (empty) { empty.value = val; } else { addParRow(val); }
        update();
    }
    if (parChips) {
        parChips.addEventListener('click', function (ev) {
            var c = ev.target && ev.target.closest ? ev.target.closest('[data-par-chip]') : null;
            if (c) { ev.preventDefault(); fillParSize(c.getAttribute('data-val')); }
        });
    }
    // Perruque : déclinaison longueur × couleur. Un clic sur une longueur remplit/ajoute une ligne.
    function addPerrRow(val) {
        if (!perrTpl || !perrTpl.content || !perrRows) { return null; }
        perrRows.appendChild(perrTpl.content.cloneNode(true));
        var row = perrRows.lastElementChild;
        if (row && val) { var s = row.querySelector('input[name="var_size[]"]'); if (s) { s.value = val; } }
        return row;
    }
    function fillPerrSize(val) {
        var empty = null;
        perrRows.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { if (!empty && !i.value.trim()) { empty = i; } });
        if (empty) { empty.value = val; } else { addPerrRow(val); }
        update();
    }
    if (perrChips) {
        perrChips.addEventListener('click', function (ev) {
            var c = ev.target && ev.target.closest ? ev.target.closest('[data-perr-chip]') : null;
            if (c) { ev.preventDefault(); fillPerrSize(c.getAttribute('data-val')); }
        });
    }
    if (perHairSel) { perHairSel.addEventListener('change', function () { refreshPerrAdaptive(); update(); }); }
    if (perTypeSel) { perTypeSel.addEventListener('change', function () { refreshPerrAdaptive(); update(); }); }
    if (soinsTypeSel) { soinsTypeSel.addEventListener('change', onSoinsType); }
    if (soinsUnitSel) { soinsUnitSel.addEventListener('change', function () { var tag = document.querySelector('[data-soins-unit-tag]'); if (tag) { tag.textContent = soinsUnitSel.value; } update(); }); }
    if (soinsChips) {
        soinsChips.addEventListener('click', function (ev) {
            var c = ev.target && ev.target.closest ? ev.target.closest('[data-soins-chip]') : null;
            if (c) { ev.preventDefault(); fillSoinsSize(c.getAttribute('data-val')); }
        });
    }

    /* ---------- Événements ---------- */
    if (typeSel) { typeSel.addEventListener('change', onType); }
    if (coll) { coll.addEventListener('change', function () { toggleSections(); if (isSoins()) { rebuildSoins(); } if (isAutre()) { adaptAutre(); } if (!meta()) { buildChips(); } update(); }); }
    if (collOther) { collOther.addEventListener('input', function () { if (isAutre()) { adaptAutre(); } }); }
    var autreAxisInp = document.querySelector('[data-autre-axis]');
    if (autreAxisInp) { autreAxisInp.addEventListener('input', function () { autreAxisLabel(); update(); }); }
    var autreColorTog = document.querySelector('[data-autre-color-toggle]');
    if (autreColorTog) { autreColorTog.addEventListener('change', function () { autreApplyColorCol(); update(); }); }
    var autreUnitSel = document.getElementById('autre-unit');
    if (autreUnitSel) { autreUnitSel.addEventListener('change', function () { var t = document.querySelector('[data-autre-unit-tag]'); if (t) { t.textContent = autreUnitSel.value; } update(); }); }
    var autreAtoutInp = document.querySelector('[data-autre-atout-input]');
    if (autreAtoutInp) { autreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAutreAtout(); } }); }
    if (chips) {
        chips.addEventListener('click', function (ev) {
            var c = ev.target && ev.target.closest ? ev.target.closest('[data-bchip]') : null;
            if (c) { ev.preventDefault(); fillDecl({ name: c.getAttribute('data-name'), hex: c.getAttribute('data-hex'), nuance: c.getAttribute('data-nuance') }); }
        });
    }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        if (ev.target.closest('[data-beauty-add]')) { var r = addRow(null); if (r) { var f = r.querySelector('input'); if (f) { f.focus(); } } return; }
        if (ev.target.closest('[data-ong-add]')) { var ro = addOngRow(); if (ro) { var fo = ro.querySelector('select'); if (fo) { fo.focus(); } } return; }
        if (ev.target.closest('[data-par-add]')) { var rp = addParRow(''); if (rp) { var fp = rp.querySelector('input'); if (fp) { fp.focus(); } } return; }
        if (ev.target.closest('[data-perr-add]')) { var rpe = addPerrRow(''); if (rpe) { var fpe = rpe.querySelector('input'); if (fpe) { fpe.focus(); } } return; }
        if (ev.target.closest('[data-soins-add]')) { var rs = addSoinsRow(''); if (rs) { var fs = rs.querySelector('input'); if (fs) { fs.focus(); } } return; }
        if (ev.target.closest('[data-autre-add]')) { var ra = addAutreVar(); if (ra) { var fa = ra.querySelector('input'); if (fa) { fa.focus(); } } return; }
        if (ev.target.closest('[data-autre-spec-add]')) { addAutreSpec(''); return; }
        if (ev.target.closest('[data-autre-atout-add]')) { pushAutreAtout(); return; }
        var aspec = ev.target.closest('[data-autre-spec]');
        if (aspec) { ev.preventDefault(); addAutreSpec(aspec.getAttribute('data-val')); return; }
        var aray = ev.target.closest('[data-autre-rayon]');
        if (aray) {
            ev.preventDefault();
            var rn = aray.getAttribute('data-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); update(); } return; }
        var del = ev.target.closest('[data-beauty-del], [data-ong-del], [data-par-del], [data-perr-del], [data-soins-del], [data-autre-del]');
        if (del) { var row = del.closest('.bvariant-row'); if (row) { row.remove(); update(); } }
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    var previews = document.getElementById('product-previews');
    if (previews && 'MutationObserver' in window) { new MutationObserver(syncPhoto).observe(previews, { childList: true, subtree: true }); }

    // État initial.
    toggleSections();
    buildChips(); applyNuanceCol();
    update(); syncPhoto();
})();

/* ---- Électronique (Accessoires / Audio…) : adaptatif au type + axe libre ---- */
(function () {
    var cfgEl = document.querySelector('[data-elec]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons'); // { 'Accessoires':{fields,groups,types,atouts}, 'Audio & écouteurs':{…} }
    var coll = document.querySelector('[data-collection-select]');
    var typeSel = document.getElementById('acc-type');
    var attrsBox = document.querySelector('[data-elec-attrs]');
    var atoutsBox = document.querySelector('[data-elec-atouts-chips]');
    var sensorsBox = document.querySelector('[data-elec-sensors-box]');
    var sensorsChips = document.querySelector('[data-elec-sensors-chips]');
    var rowsBox = document.querySelector('[data-elec-rows]');
    var varTpl = document.getElementById('elec-variant-template');
    var axisInp = document.querySelector('[data-elec-axis]');
    var colorTog = document.querySelector('[data-elec-color-toggle]');
    // « Autre / nouveau rayon » électronique : specs libres adaptées au slug du rayon.
    var AUTRE = parse('data-autre');
    var collOther = document.querySelector('[data-collection-other]');
    var eautreSpecsBox = document.querySelector('[data-eautre-specs]');
    var eautreSpecTpl = document.getElementById('eautre-spec-template');

    function isElecForm() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayon() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? coll.value : ''); }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayon())] || null; }

    function toggleSections() {
        var active = isElecForm() ? 'elec' : 'autre';
        document.querySelectorAll('[data-elec-section]').forEach(function (sec) {
            var on = sec.getAttribute('data-elec-section') === active;
            sec.hidden = !on;
            sec.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
        });
    }
    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    // Reconstruit le sélecteur de type + les atouts quand le rayon électronique change.
    function rebuildElec() {
        var c = cfg();
        if (typeSel && c.types) {
            var cur = typeSel.value;
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = typeSel.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            var groups = c.groups || {};
            Object.keys(groups).forEach(function (gk) {
                var og = document.createElement('optgroup'); og.label = groups[gk];
                Object.keys(c.types).forEach(function (tn) {
                    if ((c.types[tn].group || '') !== gk) { return; }
                    var op = document.createElement('option'); op.value = tn; op.textContent = tn;
                    if (tn === cur) { op.selected = true; }
                    og.appendChild(op);
                });
                typeSel.appendChild(og);
            });
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        if (sensorsChips) {
            var prevCap = {};
            sensorsChips.querySelectorAll('input:checked').forEach(function (i) { prevCap[i.value] = true; });
            sensorsChips.innerHTML = '';
            (c.sensors || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check chip-check--health';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'capteur[]'; inp.value = v;
                if (prevCap[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); sensorsChips.appendChild(lab);
            });
        }
        onType();
    }
    function applyColorCol() { if (rowsBox) { rowsBox.classList.toggle('has-color', !!(colorTog && colorTog.checked)); } }
    function axisLabel() {
        var lab = document.querySelector('[data-elec-axis-label]');
        if (lab) { lab.textContent = (axisInp && axisInp.value.trim()) ? axisInp.value.trim() : (cfgEl.getAttribute('data-opt') || 'Option'); }
    }
    function onType() {
        var m = meta();
        var compat = document.querySelector('[data-elec-compat-box]'); if (compat) { compat.hidden = !(m && m.compat); }
        if (sensorsBox) { sensorsBox.hidden = !(m && m.sensors); }
        if (m) {
            if (axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
            if (colorTog) { colorTog.checked = !!m.color; }
        }
        buildAttrs(); applyColorCol(); axisLabel();
        var sh = document.querySelector('[data-elec-hint]');
        if (sh) { sh.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || sh.textContent) : (cfgEl.getAttribute('data-hint-pick') || sh.textContent); }
    }
    function addRow() {
        if (!varTpl || !varTpl.content || !rowsBox) { return null; }
        rowsBox.appendChild(varTpl.content.cloneNode(true));
        return rowsBox.lastElementChild;
    }

    // ----- « Autre / nouveau rayon » : adaptation au slug, specs libres, atouts libres -----
    function autreBuildSpecChips() {
        var box = document.querySelector('[data-eautre-spec-chips]'); if (!box) { return; }
        var c = autreCfg();
        var list = c ? c.specs : (AUTRE.generic_specs || []);
        box.innerHTML = '';
        (list || []).forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-eautre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            box.appendChild(b);
        });
    }
    function adaptAutre() {
        var c = autreCfg();
        var hint = document.querySelector('[data-eautre-rayon-hint]');
        if (hint) {
            var r = autreRayon();
            hint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', r)) : (cfgEl.getAttribute('data-autre-generic') || '');
        }
        if (c) {
            if (axisInp && !axisInp.value.trim()) { axisInp.value = c.axis || ''; }
            if (colorTog) { colorTog.checked = !!c.color; }
        }
        autreBuildSpecChips(); applyColorCol(); axisLabel();
    }
    function addEautreSpec(label) {
        if (!eautreSpecTpl || !eautreSpecTpl.content || !eautreSpecsBox) { return null; }
        eautreSpecsBox.appendChild(eautreSpecTpl.content.cloneNode(true));
        var row = eautreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
        return row;
    }
    function pushEautreAtout() {
        var inp = document.querySelector('[data-eautre-atout-input]');
        var box = document.querySelector('[data-eautre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }

    if (typeSel) { typeSel.addEventListener('change', onType); }
    if (coll) { coll.addEventListener('change', function () { toggleSections(); if (isElecForm()) { rebuildElec(); } else { adaptAutre(); } }); }
    if (collOther) { collOther.addEventListener('input', function () { if (!isElecForm()) { adaptAutre(); } }); }
    if (axisInp) { axisInp.addEventListener('input', axisLabel); }
    if (colorTog) { colorTog.addEventListener('change', applyColorCol); }
    var eautreAtoutInp = document.querySelector('[data-eautre-atout-input]');
    if (eautreAtoutInp) { eautreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushEautreAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        if (ev.target.closest('[data-elec-add]')) { var r = addRow(); if (r) { var f = r.querySelector('input'); if (f) { f.focus(); } } return; }
        if (ev.target.closest('[data-eautre-spec-add]')) { addEautreSpec(''); return; }
        if (ev.target.closest('[data-eautre-atout-add]')) { pushEautreAtout(); return; }
        var aspec = ev.target.closest('[data-eautre-spec]');
        if (aspec) { ev.preventDefault(); addEautreSpec(aspec.getAttribute('data-val')); return; }
        var aray = ev.target.closest('[data-eautre-rayon]');
        if (aray) {
            ev.preventDefault();
            var rn = aray.getAttribute('data-eautre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-eautre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } return; }
        var del = ev.target.closest('[data-elec-del]');
        if (del) { var row = del.closest('.bvariant-row'); if (row) { row.remove(); } }
    });

    toggleSections();
    if (!isElecForm()) { adaptAutre(); }
})();

/* ---- Mode : rayon adaptatif au type (Chaussures…) + pointures avec remplissage rapide ---- */
(function () {
    var cfgEl = document.querySelector('[data-appa]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons'); // { 'Chaussures':{groups,fields,types,atouts,quickfill,axis} }
    var GENRES = parse('data-genres'); // genres globaux (repli si le rayon n'en impose pas)
    if (!Array.isArray(GENRES)) { GENRES = []; }
    var COULEURS = parse('data-couleurs'); // couleurs globales (repli)
    if (!Array.isArray(COULEURS)) { COULEURS = []; }
    var CONDS = parse('data-conditions'); // états globaux (repli)
    if (!Array.isArray(CONDS)) { CONDS = []; }
    var coll = document.querySelector('[data-collection-select]');
    var typeSel = document.getElementById('appa-type');
    var genreSel = document.getElementById('appa-genre');
    var couleurSel = document.getElementById('appa-couleur');
    var condSel = document.getElementById('appa-condition');
    var attrsBox = document.querySelector('[data-appa-attrs]');
    var atoutsBox = document.querySelector('[data-appa-atouts-chips]');
    var rowsBox = document.querySelector('[data-appa-rows]');
    var varTpl = document.getElementById('appa-variant-template');
    var axisInp = document.querySelector('[data-appa-axis]');
    var colorTog = document.querySelector('[data-appa-color-toggle]');

    function isAppaRayon() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function genre() { return genreSel ? genreSel.value : ''; }

    function toggleSections() {
        var active = isAppaRayon() ? 'adaptive' : 'autre';
        document.querySelectorAll('[data-appa-section]').forEach(function (sec) {
            var on = sec.getAttribute('data-appa-section') === active;
            sec.hidden = !on;
            sec.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
        });
    }
    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        var g = genre();
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var drop = (def.exclude && def.exclude[g]) ? def.exclude[g] : [];
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                if (drop.indexOf(o) !== -1) { return; } // option exclue pour ce genre
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    // Reconstruit le sélecteur de type + atouts quand le rayon mode change (groupes + types à plat).
    function rebuildAppa() {
        var c = cfg();
        if (typeSel && c.types) {
            var cur = typeSel.value;
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = typeSel.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            var groups = c.groups || {};
            Object.keys(groups).forEach(function (gk) {
                var og = document.createElement('optgroup'); og.label = groups[gk];
                Object.keys(c.types).forEach(function (tn) {
                    if ((c.types[tn].group || '') !== gk) { return; }
                    var op = document.createElement('option'); op.value = tn; op.textContent = tn;
                    if (tn === cur) { op.selected = true; }
                    og.appendChild(op);
                });
                typeSel.appendChild(og);
            });
            Object.keys(c.types).forEach(function (tn) {
                if ((c.types[tn].group || '') !== '') { return; }
                var op = document.createElement('option'); op.value = tn; op.textContent = tn;
                if (tn === cur) { op.selected = true; }
                typeSel.appendChild(op);
            });
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        buildGenreOptions();
        if (couleurSel) {
            var curC = couleurSel.value;
            var clist = (cfg().couleurs && cfg().couleurs.length) ? cfg().couleurs : COULEURS;
            couleurSel.innerHTML = '';
            var c0 = document.createElement('option'); c0.value = ''; c0.textContent = '—'; couleurSel.appendChild(c0);
            clist.forEach(function (cn) { var op = document.createElement('option'); op.value = cn; op.textContent = cn; couleurSel.appendChild(op); });
            couleurSel.value = (clist.indexOf(curC) !== -1) ? curC : '';
        }
        if (condSel) {
            var curK = condSel.value;
            var klist = (cfg().conditions && cfg().conditions.length) ? cfg().conditions : CONDS;
            condSel.innerHTML = '';
            klist.forEach(function (kn) { var op = document.createElement('option'); op.value = kn; op.textContent = kn; condSel.appendChild(op); });
            condSel.value = (klist.indexOf(curK) !== -1) ? curK : (klist[0] || '');
            var note = document.querySelector('[data-appa-cond-note]');
            if (note) { var nt = cfg().condition_note || ''; note.textContent = nt ? ('· ' + nt) : ''; note.hidden = !nt; }
        }
        applyLock();
        if (axisInp && cfg().axis) { axisInp.value = cfg().axis; axisLabel(); } // axe par défaut du rayon (le type peut l'imposer ensuite)
        onType();
    }
    // Publics du sélecteur de genre : pub du TYPE (rayon type_public) sinon genres du rayon.
    function genreList() {
        var m = meta();
        if (cfg().type_public && m && m.pub && m.pub.length) { return m.pub; }
        return (cfg().genres && cfg().genres.length) ? cfg().genres : GENRES;
    }
    function noEmptyGenre() { return !!cfg().public || !!cfg().type_public; }
    function buildGenreOptions() {
        if (!genreSel) { return; }
        var curG = genreSel.value, list = genreList(), ne = noEmptyGenre();
        genreSel.innerHTML = '';
        if (!ne) { var g0 = document.createElement('option'); g0.value = ''; g0.textContent = cfgEl.getAttribute('data-any') || '—'; genreSel.appendChild(g0); }
        list.forEach(function (gn) { var op = document.createElement('option'); op.value = gn; op.textContent = gn; genreSel.appendChild(op); });
        genreSel.value = (list.indexOf(curG) !== -1) ? curG : (ne ? (list[0] || '') : '');
    }
    // Bandeau « rayon verrouillé » (ex. public féminin) + indice près du genre.
    function applyLock() {
        var locked = !!cfg().public, lab = cfg().lock_label || '';
        var ban = document.querySelector('[data-appa-lockban]'), bt = document.querySelector('[data-appa-lockban-text]'), lh = document.querySelector('[data-appa-lockhint]');
        if (bt) { bt.textContent = lab; }
        if (ban) { ban.hidden = !locked; }
        if (lh) { lh.hidden = !locked; }
    }
    // Boutons de remplissage : résolus selon le TYPE (taille/couleur), sinon par genre / statique.
    function quickfillSet() {
        var m = meta();
        if (m) {
            if (m.sizes) { return (cfg().sizesets || {})[m.sizes] || []; }
            if (m.color && cfg().palette) { return cfg().palette.map(function (p) { return { label: p[0], kind: 'color', hex: p[1] }; }); }
        }
        var qf = cfg().quickfill;
        if (!qf) { return []; }
        if (Array.isArray(qf)) { return qf; }
        return qf[genre()] || [];
    }
    function buildQuickfill() {
        var box = document.querySelector('[data-appa-quickfill]'); if (!box) { return; }
        box.querySelectorAll('[data-appa-fill]').forEach(function (b) { b.remove(); });
        var clear = box.querySelector('[data-appa-clear]');
        quickfillSet().forEach(function (btn) {
            var isCol = btn.kind === 'color';
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip' + (isCol ? ' axis-chip--color' : '');
            b.setAttribute('data-appa-fill', ''); b.setAttribute('data-fill', JSON.stringify(btn));
            if (isCol) {
                var dot = document.createElement('span'); dot.className = 'axis-dot'; dot.style.background = btn.hex || '#222';
                b.appendChild(dot); b.appendChild(document.createTextNode(btn.label || ''));
            } else { b.textContent = '+ ' + (btn.label || ''); }
            box.insertBefore(b, clear);
        });
    }
    function sizesHint() {
        var h = document.querySelector('[data-appa-sizes-hint]'); if (!h) { return; }
        var m = meta();
        if (cfg().type_decl) { // Sacs : la nature de la déclinaison dépend du type
            if (!m) { h.textContent = cfgEl.getAttribute('data-hint-pick') || h.textContent; }
            else { h.textContent = (m.sizes ? cfgEl.getAttribute('data-decl-size') : cfgEl.getAttribute('data-decl-color')) || h.textContent; }
            return;
        }
        if (cfg().type_sizes) { // Sous-vêtements : tailles selon le type
            h.textContent = (m ? cfgEl.getAttribute('data-sizes-hint') : cfgEl.getAttribute('data-hint-pick')) || h.textContent;
            return;
        }
        var qf = cfg().quickfill, byGenre = qf && !Array.isArray(qf), g = genre();
        if (byGenre && !g) { h.textContent = cfgEl.getAttribute('data-sizes-pick') || h.textContent; }
        else if (byGenre && g) { h.textContent = (cfgEl.getAttribute('data-sizes-genre') || '%G%').replace('%G%', g); }
        else { h.textContent = cfgEl.getAttribute('data-sizes-hint') || h.textContent; }
    }
    function applyColorCol() { if (rowsBox) { rowsBox.classList.toggle('has-color', !!(colorTog && colorTog.checked)); } }
    function axisLabel() {
        var lab = document.querySelector('[data-appa-axis-label]');
        if (lab) { lab.textContent = (axisInp && axisInp.value.trim()) ? axisInp.value.trim() : (cfgEl.getAttribute('data-opt') || 'Option'); }
    }
    function onType() {
        var m = meta();
        if (cfg().type_decl && m) { // Sacs : le type impose l'axe et la pastille couleur
            if (axisInp && m.axis) { axisInp.value = m.axis; }
            if (colorTog) { colorTog.checked = !!m.color; }
        }
        if (cfg().type_public) { buildGenreOptions(); } // public adapté au type
        buildAttrs(); applyColorCol(); axisLabel(); buildQuickfill(); sizesHint();
        var sh = document.querySelector('[data-appa-hint]');
        if (sh) { sh.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || sh.textContent) : (cfgEl.getAttribute('data-hint-pick') || sh.textContent); }
    }
    function addRow() {
        if (!varTpl || !varTpl.content || !rowsBox) { return null; }
        rowsBox.appendChild(varTpl.content.cloneNode(true));
        return rowsBox.lastElementChild;
    }
    // Ajoute une liste de tailles (sans doublon) à l'éditeur de déclinaisons.
    function pushSizes(list) {
        if (!rowsBox || !list || !list.length) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value).trim()] = true; });
        list.forEach(function (val) {
            if (have[String(val)]) { return; }
            have[String(val)] = true;
            var row = addRow(); if (!row) { return; }
            var sz = row.querySelector('input[name="var_size[]"]'); if (sz) { sz.value = String(val); }
        });
    }
    // Ajoute UN coloris (nom + pastille hex) à l'éditeur, en activant la colonne couleur.
    function addColorVariant(name, hex) {
        if (!rowsBox || !name) { return; }
        var exists = false;
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { if (String(i.value).trim() === name) { exists = true; } });
        if (exists) { return; }
        var row = addRow(); if (!row) { return; }
        var sz = row.querySelector('input[name="var_size[]"]'); if (sz) { sz.value = name; }
        var hx = row.querySelector('input[name="var_hex[]"]'); if (hx && hex) { hx.value = hex; }
        if (colorTog && !colorTog.checked) { colorTog.checked = true; applyColorCol(); }
    }
    // Génère les valeurs d'un bouton : 'color' (coloris), 'range' (de..à pas, +suffixe), 'jeans' (W..), 'list'.
    function fillFromBtn(btn) {
        if (!btn) { return; }
        if (btn.kind === 'color') { addColorVariant(btn.label, btn.hex); return; }
        var list = [], kind = btn.kind || 'range';
        if (kind === 'list') { list = btn.list || []; }
        else {
            var step = parseInt(btn.step, 10) || (kind === 'jeans' ? 2 : 1);
            var prefix = kind === 'jeans' ? 'W' : '', suffix = btn.suffix || '';
            for (var n = parseInt(btn.from, 10); n <= parseInt(btn.to, 10); n += step) { list.push(prefix + n + suffix); }
        }
        pushSizes(list);
    }

    // Changement de type : si l'axe change (Sacs couleur⇄taille), vide les options devenues caduques.
    if (typeSel) {
        typeSel.addEventListener('change', function () {
            var prevAxis = axisInp ? axisInp.value : '';
            onType();
            if (cfg().type_decl && axisInp && axisInp.value !== prevAxis && rowsBox) {
                rowsBox.querySelectorAll('.bvariant-row').forEach(function (r) { r.remove(); });
            }
        });
    }
    if (genreSel) { genreSel.addEventListener('change', function () { buildAttrs(); buildQuickfill(); sizesHint(); }); }
    if (coll) { coll.addEventListener('change', function () { toggleSections(); if (isAppaRayon()) { rebuildAppa(); } }); }
    if (axisInp) { axisInp.addEventListener('input', axisLabel); }
    if (colorTog) { colorTog.addEventListener('change', applyColorCol); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-appa-fill]');
        if (fill) { try { fillFromBtn(JSON.parse(fill.getAttribute('data-fill') || 'null')); } catch (e) {} return; }
        if (ev.target.closest('[data-appa-clear]')) { if (rowsBox) { rowsBox.querySelectorAll('.bvariant-row').forEach(function (r) { r.remove(); }); } return; }
        if (ev.target.closest('[data-appa-add]')) { var r = addRow(); if (r) { var f = r.querySelector('input'); if (f) { f.focus(); } } return; }
        var del = ev.target.closest('[data-appa-del]');
        if (del) { var row = del.closest('.bvariant-row'); if (row) { row.remove(); } }
    });

    toggleSections();
    if (isAppaRayon()) { sizesHint(); applyLock(); }
})();

/* ---- Mode : « nouveau rayon » générique (specs libres, tailles par genre/mètre, adaptatif au slug) ---- */
(function () {
    var cfgEl = document.querySelector('[data-appa]');
    var root  = document.querySelector('[data-appa-autre-root]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !root || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var AUTRE = parse('data-autre'); // { R, genre_sizes, metre_sizes, generic_specs, atout_suggest, couleurs }
    var RAYONS = parse('data-rayons');
    var coll = document.querySelector('[data-collection-select]');
    var collOther = document.querySelector('[data-collection-other]');
    var genreSel = document.getElementById('aautre-genre');
    var specsBox = document.querySelector('[data-aautre-specs]');
    var specTpl = document.getElementById('aautre-spec-template');
    var rowsBox = document.querySelector('[data-aautre-rows]');
    var varTpl = document.getElementById('aautre-variant-template');
    var axisInp = document.querySelector('[data-aautre-axis]');
    var colorTog = document.querySelector('[data-aautre-color-toggle]');

    function slug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function rayon() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? coll.value : ''); }
    function isKnown() { return !!(coll && RAYONS[coll.value]); }   // rayon mode répertorié => géré ailleurs
    function cfg() { return (AUTRE.R || {})[slug(rayon())] || null; }
    function genre() { return genreSel ? genreSel.value : ''; }

    function genreList() {
        var c = cfg(), pub = c ? (c.pub || 'all') : 'all';
        if (pub === 'femme') { return ['Femme']; }
        if (pub === 'none') { return ['Non applicable']; }
        return ['Mixte / unisexe', 'Femme', 'Homme', 'Fille', 'Garçon', 'Enfant', 'Bébé'];
    }
    function buildGenre() {
        if (!genreSel) { return; }
        var cur = genreSel.value, list = genreList();
        genreSel.innerHTML = '';
        list.forEach(function (g) { var op = document.createElement('option'); op.value = g; op.textContent = g; genreSel.appendChild(op); });
        genreSel.value = (list.indexOf(cur) !== -1) ? cur : (list[0] || '');
    }
    function buildSpecChips() {
        var box = document.querySelector('[data-aautre-spec-chips]'); if (!box) { return; }
        var c = cfg(), list = c ? c.specs : (AUTRE.generic_specs || []);
        box.innerHTML = '';
        (list || []).forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-aautre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            box.appendChild(b);
        });
    }
    function axisLabel() {
        var lab = document.querySelector('[data-aautre-axis-label]');
        if (lab) { lab.textContent = (axisInp && axisInp.value.trim()) ? axisInp.value.trim() : (cfgEl.getAttribute('data-opt') || 'Option'); }
    }
    function applyColorCol() { if (rowsBox) { rowsBox.classList.toggle('has-color', !!(colorTog && colorTog.checked)); } }
    function quickfillSet() {
        var c = cfg();
        if (c && c.sizes === 'metre') { return AUTRE.metre_sizes || []; }
        return (AUTRE.genre_sizes || {})[genre()] || [];
    }
    function buildQuickfill() {
        var box = document.querySelector('[data-aautre-quickfill]'); if (!box) { return; }
        box.querySelectorAll('[data-aautre-fill]').forEach(function (b) { b.remove(); });
        var clear = box.querySelector('[data-aautre-clear]');
        quickfillSet().forEach(function (btn) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-aautre-fill', ''); b.setAttribute('data-fill', JSON.stringify(btn));
            b.textContent = '+ ' + (btn.label || '');
            box.insertBefore(b, clear);
        });
    }
    function sizesHint() {
        var h = document.querySelector('[data-aautre-sizes-hint]'); if (!h) { return; }
        var c = cfg();
        h.textContent = (c && c.sizes === 'metre') ? (cfgEl.getAttribute('data-decl-size') || h.textContent)
            : (cfgEl.getAttribute('data-sizes-genre') || '%G%').replace('%G%', genre());
    }
    function adapt() {
        var c = cfg();
        var hint = document.querySelector('[data-aautre-hint]');
        if (hint) { hint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', rayon())) : (cfgEl.getAttribute('data-autre-generic') || ''); }
        if (c) {
            if (axisInp) { axisInp.value = c.axis || axisInp.value; }
            if (colorTog) { colorTog.checked = !!c.color; }
        }
        buildGenre(); buildSpecChips(); buildQuickfill(); applyColorCol(); axisLabel(); sizesHint();
    }
    function addRow() { if (!varTpl || !varTpl.content || !rowsBox) { return null; } rowsBox.appendChild(varTpl.content.cloneNode(true)); return rowsBox.lastElementChild; }
    function pushSizes(list) {
        if (!rowsBox || !list) { return; }
        var have = {}; rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value).trim()] = true; });
        list.forEach(function (val) { if (have[String(val)]) { return; } have[String(val)] = true; var r = addRow(); if (r) { var sz = r.querySelector('input[name="var_size[]"]'); if (sz) { sz.value = String(val); } } });
    }
    function fillFromBtn(btn) {
        if (!btn) { return; }
        var list = [], kind = btn.kind || 'range';
        if (kind === 'list') { list = btn.list || []; }
        else { var step = parseInt(btn.step, 10) || 1; var pfx = kind === 'jeans' ? 'W' : '', sfx = btn.suffix || ''; for (var n = parseInt(btn.from, 10); n <= parseInt(btn.to, 10); n += step) { list.push(pfx + n + sfx); } }
        pushSizes(list);
    }
    function addSpec(label) {
        if (!specTpl || !specTpl.content || !specsBox) { return; }
        specsBox.appendChild(specTpl.content.cloneNode(true));
        var row = specsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAtout() {
        var inp = document.querySelector('[data-aautre-atout-input]'), box = document.querySelector('[data-aautre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) { var lab = document.createElement('label'); lab.className = 'chip-check'; var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true; var sp = document.createElement('span'); sp.textContent = v; lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab); }
        inp.value = '';
    }

    if (genreSel) { genreSel.addEventListener('change', function () { buildQuickfill(); sizesHint(); }); }
    if (axisInp) { axisInp.addEventListener('input', axisLabel); }
    if (colorTog) { colorTog.addEventListener('change', applyColorCol); }
    if (coll) { coll.addEventListener('change', function () { if (!isKnown()) { adapt(); } }); }
    if (collOther) { collOther.addEventListener('input', function () { if (!isKnown()) { adapt(); } }); }
    var atoutInp = document.querySelector('[data-aautre-atout-input]');
    if (atoutInp) { atoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-aautre-fill]');
        if (fill) { try { fillFromBtn(JSON.parse(fill.getAttribute('data-fill') || 'null')); } catch (e) {} return; }
        if (ev.target.closest('[data-aautre-clear]')) { if (rowsBox) { rowsBox.querySelectorAll('.bvariant-row').forEach(function (r) { r.remove(); }); } return; }
        if (ev.target.closest('[data-aautre-add]')) { var r = addRow(); if (r) { var f = r.querySelector('input'); if (f) { f.focus(); } } return; }
        if (ev.target.closest('[data-aautre-spec-add]')) { addSpec(''); return; }
        if (ev.target.closest('[data-aautre-atout-add]')) { pushAtout(); return; }
        var asp = ev.target.closest('[data-aautre-spec]');
        if (asp) { ev.preventDefault(); addSpec(asp.getAttribute('data-val')); return; }
        var ar = ev.target.closest('[data-aautre-rayon]');
        if (ar) { ev.preventDefault(); var rn = ar.getAttribute('data-aautre-rayon'); if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); } if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); } return; }
        var sdel = ev.target.closest('[data-aautre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } return; }
        var del = ev.target.closest('[data-aautre-del]');
        if (del) { var row = del.closest('.bvariant-row'); if (row) { row.remove(); } }
    });

    if (!isKnown()) { sizesHint(); }
})();

/* ---- Rayon/Catégorie : révèle un champ libre quand « Autre » est choisi ---- */
(function () {
    var sel = document.querySelector('[data-collection-select]');
    var other = document.querySelector('[data-collection-other]');
    if (!sel || !other) { return; }
    sel.addEventListener('change', function () {
        var on = sel.value === '__other__';
        other.hidden = !on;
        if (on) { other.focus(); }
    });
})();

/* ---- Genre ⇄ Catégorie de vêtement : ne proposer que les catégories du genre choisi ---- */
(function () {
    var aud = document.getElementById('p-audience');
    var gar = document.querySelector('[data-garment-select]');
    if (!aud || !gar) { return; }
    function filter() {
        var a = aud.value, cur = gar.value, valid = true;
        Array.prototype.forEach.call(gar.querySelectorAll('option'), function (o) {
            if (!o.value) { return; }
            var auds = (o.getAttribute('data-audiences') || '').split(',');
            var ok = !a || auds.indexOf(a) !== -1;
            o.hidden = !ok; o.disabled = !ok;
            if (!ok && o.value === cur) { valid = false; }
        });
        Array.prototype.forEach.call(gar.querySelectorAll('optgroup'), function (g) {
            var any = Array.prototype.some.call(g.querySelectorAll('option'), function (o) { return !o.hidden; });
            g.hidden = !any;
        });
        if (!valid) { gar.value = ''; gar.dispatchEvent(new Event('change')); }
    }
    aud.addEventListener('change', filter);
    filter();
})();

/* ---- Création boutique : indice quand la catégorie « électronique » est choisie ---- */
(function () {
    var sel = document.querySelector('[data-shop-cat]');
    var hint = document.querySelector('[data-cat-phone-hint]');
    if (!sel || !hint) { return; }
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        hint.hidden = !(opt && opt.getAttribute('data-vertical') === 'phone');
    });
})();

/* ---- Vente au mètre : aperçu du total (longueur × prix au mètre) ---- */
(function () {
    var box = document.querySelector('[data-meter-buy]');
    if (!box) { return; }
    var input = box.querySelector('[data-meter-length]');
    var out = box.querySelector('[data-meter-total]');
    if (!input || !out) { return; }
    var priceM = parseInt(box.getAttribute('data-price-m'), 10) || 0;
    var root = document.querySelector('[data-cart-root]');
    var curInt = root && root.getAttribute('data-cur-int') === '1';
    var sym = (root && root.getAttribute('data-cur-sym')) || '';
    function upd() {
        var m = parseFloat((input.value || '').replace(',', '.'));
        if (!m || m <= 0) { out.textContent = '—'; return; }
        var c = Math.round(m * priceM);
        out.textContent = (curInt ? String(Math.round(c / 100)) : (c / 100).toFixed(2).replace('.', ',')) + ' ' + sym;
    }
    input.addEventListener('input', upd);
    upd();
})();

/* ---- Sélecteur de déclinaison taille/couleur (fiche produit) ---- */
(function () {
    var pick = document.querySelector('[data-variant-pick]');
    if (!pick) { return; }
    var variants;
    try { variants = JSON.parse(pick.getAttribute('data-variants') || '[]'); } catch (e) { variants = []; }
    if (!variants.length) { return; }
    var buy = document.querySelector('[data-buy-now]');
    var unavail = pick.querySelector('[data-variant-unavailable]');
    var priceEl = document.querySelector('.listing-price');
    var root = document.querySelector('[data-cart-root]');
    var curInt = root && root.getAttribute('data-cur-int') === '1';
    var sym = (root && root.getAttribute('data-cur-sym')) || '';
    var hasSize = !!pick.querySelector('input[name="pick_size"]');
    var hasColor = !!pick.querySelector('input[name="pick_color"]');
    var priceVaries = variants.some(function (v) { return v.price !== variants[0].price; });

    // Photos par couleur : clic sur une couleur → bascule la galerie produit.
    var colorImages = {};
    try { colorImages = JSON.parse(pick.getAttribute('data-color-images') || '{}'); } catch (e) { colorImages = {}; }
    var hasColorImages = Object.keys(colorImages).length > 0;
    var gal = document.querySelector('[data-gallery]');
    var galMain = document.getElementById('listing-main-photo');
    var galThumbs = gal ? gal.querySelector('.listing-thumbs') : null;
    var galZoom = gal ? gal.querySelector('[data-zoom-open]') : null;
    var galDefaults = gal ? { photos: gal.getAttribute('data-photos'), main: galMain ? galMain.src : '', thumbs: galThumbs ? galThumbs.innerHTML : '' } : null;
    var lastColor = null;
    function swapGallery(color) {
        if (!hasColorImages || !gal || !galMain || color === lastColor) { return; }
        lastColor = color;
        var imgs = colorImages[color];
        if (imgs && imgs.length) {
            galMain.src = imgs[0].full;
            gal.setAttribute('data-photos', JSON.stringify(imgs.map(function (x) { return x.zoom; })));
            if (galThumbs) {
                galThumbs.innerHTML = imgs.map(function (x, i) {
                    return '<button type="button" class="thumb" data-index="' + i + '" data-gallery-full="' + x.full + '"><img src="' + x.thumb + '" alt="" loading="lazy" width="120" height="90"></button>';
                }).join('');
            }
            if (galZoom) { galZoom.setAttribute('data-index', '0'); }
        } else if (galDefaults) {
            // Couleur sans photo dédiée → galerie d'origine du produit.
            galMain.src = galDefaults.main;
            gal.setAttribute('data-photos', galDefaults.photos || '[]');
            if (galThumbs) { galThumbs.innerHTML = galDefaults.thumbs; }
            if (galZoom) { galZoom.setAttribute('data-index', '0'); }
        }
    }

    function fmt(c) { return (curInt ? String(Math.round(c / 100)) : (c / 100).toFixed(2).replace('.', ',')) + ' ' + sym; }
    function sel(name) { var r = pick.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
    function inStock(v) { return !!v && (v.stock === null || v.stock > 0); }
    function match(size, color) {
        for (var i = 0; i < variants.length; i++) {
            var v = variants[i];
            if ((!hasSize || v.size === size) && (!hasColor || v.color === color)) { return v; }
        }
        return null;
    }
    function check(name, val) { pick.querySelectorAll('input[name="' + name + '"]').forEach(function (r) { if (r.value === val) { r.checked = true; } }); }
    function refreshAvailability() {
        var size = sel('pick_size'), color = sel('pick_color');
        pick.querySelectorAll('input[name="pick_size"]').forEach(function (r) {
            var ok = variants.some(function (v) { return v.size === r.value && (!hasColor || !color || v.color === color) && inStock(v); });
            r.disabled = !ok; if (r.closest('.variant-chip')) { r.closest('.variant-chip').classList.toggle('is-out', !ok); }
        });
        pick.querySelectorAll('input[name="pick_color"]').forEach(function (r) {
            var ok = variants.some(function (v) { return v.color === r.value && (!hasSize || !size || v.size === size) && inStock(v); });
            r.disabled = !ok; if (r.closest('.variant-chip')) { r.closest('.variant-chip').classList.toggle('is-out', !ok); }
        });
    }
    function update() {
        var size = sel('pick_size'), color = sel('pick_color');
        if (hasColorImages) { swapGallery(color); }
        pick.querySelectorAll('[data-axis-val]').forEach(function (s) {
            var v = s.getAttribute('data-axis-val') === 'size' ? size : color;
            s.textContent = v ? '· ' + v : '';
        });
        var ready = (!hasSize || size) && (!hasColor || color);
        var v = ready ? match(size, color) : null;
        var ok = inStock(v);
        if (buy) {
            if (v && ok) { buy.setAttribute('data-buy-now', v.id); buy.disabled = false; }
            else { buy.disabled = ready; }
        }
        if (unavail) { unavail.hidden = !(ready && !ok); }
        if (priceEl && priceVaries && v) {
            priceEl.innerHTML = (v.base > v.price ? '<del class="price-was">' + fmt(v.base) + '</del> ' : '') + '<span class="' + (v.base > v.price ? 'price-now' : '') + '">' + fmt(v.price) + '</span>';
        }
        refreshAvailability();
    }
    pick.addEventListener('change', function (ev) {
        if (ev.target && (ev.target.name === 'pick_size' || ev.target.name === 'pick_color')) { update(); }
    });
    var first = null;
    for (var i = 0; i < variants.length; i++) { if (inStock(variants[i])) { first = variants[i]; break; } }
    if (first) { if (hasSize && first.size) { check('pick_size', first.size); } if (hasColor && first.color) { check('pick_color', first.color); } }
    update();
})();

/* ---- Encart newsletter (comptes par téléphone) : abonnement AJAX, refus = jamais plus ---- */
(function () {
    var pop = document.querySelector('[data-newsletter-pop]');
    if (!pop) { return; }
    function setSeen() { document.cookie = 'nl_seen=1; path=/; max-age=31536000; samesite=Lax'; }
    function close() { pop.classList.remove('is-in'); setTimeout(function () { pop.hidden = true; }, 250); }
    setTimeout(function () { pop.hidden = false; requestAnimationFrame(function () { pop.classList.add('is-in'); }); }, 6500);
    Array.prototype.forEach.call(pop.querySelectorAll('[data-nl-decline]'), function (b) {
        b.addEventListener('click', function () { setSeen(); close(); });
    });
    var form = pop.querySelector('[data-nl-form]');
    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var email = pop.querySelector('[data-nl-email]');
            var btn = pop.querySelector('[data-nl-submit]');
            if (!email || !email.value) { return; }
            if (btn) { btn.disabled = true; }
            fetch(form.getAttribute('action'), { method: 'POST', headers: { 'Accept': 'application/json' }, body: new FormData(form) })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        setSeen();
                        var done = pop.querySelector('[data-nl-done]');
                        if (done) { done.hidden = false; }
                        if (form) { form.hidden = true; }
                        var no = pop.querySelector('.nl-pop-no'); if (no) { no.hidden = true; }
                        setTimeout(close, 2600);
                    } else if (btn) { btn.disabled = false; email.focus(); }
                })
                .catch(function () { if (btn) { btn.disabled = false; } });
        });
    }
})();

/* ---- Anti double-soumission sur les formulaires sensibles ([data-submit-once]) :
   vente en caisse, ouverture/clôture de session, commande. Évite la double-vente /
   double-commande sur double-clic ou réseau lent. La désactivation est différée
   (setTimeout) pour que la soumission native parte d'abord. ---- */
document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form || !form.matches || !form.matches('[data-submit-once]')) { return; }
    if (form.dataset.submitted === '1') { ev.preventDefault(); return; }
    form.dataset.submitted = '1';
    var btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) { setTimeout(function () { btn.disabled = true; btn.classList.add('is-loading'); }, 0); }
});

/* ---- Bouton « Imprimer / Enregistrer en PDF » ([data-print]), p.ex. la facture.
   Délégué sur document pour fonctionner sur la page de facture autonome. ---- */
document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest('[data-print]') : null;
    if (b) { ev.preventDefault(); window.print(); }
});

/* ---- Maison & meubles : rayons adaptatifs au type (Cuisine, Décoration, …).
   Le rayon sélectionné pilote la liste de types + atouts ; le type pilote les
   caractéristiques affichées ; les appareils/luminaires électriques (flag elec)
   révèlent la garantie + le rappel CE/tension. La section n'est active que pour
   un rayon Maison adaptatif (sinon désactivée pour ne pas être envoyée).
   Déclinaisons : éditeur générique partagé. ---- */
(function () {
    var cfgEl = document.querySelector('[data-cuisine]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    var RAYONS; try { RAYONS = JSON.parse(cfgEl.getAttribute('data-rayons') || 'null') || {}; } catch (e) { RAYONS = {}; }
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-cuisine-root]');
    var typeSel   = document.querySelector('[data-cuisine-type]');
    var attrsBox  = document.querySelector('[data-cuisine-attrs]');
    var atoutsBox = document.querySelector('[data-cuisine-atouts]');
    var elecBox   = document.querySelector('[data-cuisine-elec-box]');
    var elecWarn  = document.querySelector('[data-cuisine-elec-warn]');
    var hint      = document.querySelector('[data-cuisine-hint]');
    var axisInp   = document.querySelector('[data-cuisine-axis]');
    // « Nouveau rayon » Maison : specs libres adaptées au slug du rayon saisi.
    var AUTRE; try { AUTRE = JSON.parse(cfgEl.getAttribute('data-autre') || 'null') || {}; } catch (e) { AUTRE = {}; }
    var collOther     = document.querySelector('[data-collection-other]');
    var autreRoot     = document.querySelector('[data-cuisine-autre-root]');
    var autreHint     = document.querySelector('[data-cuisine-autre-hint]');
    var autreSpecsBox = document.querySelector('[data-cuisine-autre-specs]');
    var autreSpecChips= document.querySelector('[data-cuisine-autre-spec-chips]');
    var autreElecTog  = document.querySelector('[data-cuisine-autre-elec-toggle]');
    var autreElecBox  = document.querySelector('[data-cuisine-autre-elec-box]');
    var autreElecWarn = document.querySelector('[data-cuisine-autre-elec-warn]');
    var autreAxisInp  = document.querySelector('[data-cuisine-autre-axis]');
    if (!root && !autreRoot) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    // Nouveau rayon : collection non vide et hors des 6 rayons connus (« __other__ »
    // ou rayon personnalisé déjà enregistré sélectionné dans la liste).
    function autreActive() { return !!(coll && coll.value !== '' && !RAYONS[coll.value]); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayonName())] || null; }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }

    // Reconstruit les caractéristiques (selects) du type choisi, en gardant les valeurs.
    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) {
            var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; }
        });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    // Reconstruit le sélecteur de type (groupes) + les atouts quand le RAYON change.
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value;
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            var groups = c.groups || {}, types = c.types || {};
            Object.keys(groups).forEach(function (gk) {
                var og = document.createElement('optgroup'); og.label = groups[gk];
                Object.keys(types).forEach(function (tn) {
                    if ((types[tn].group || '') !== gk) { return; }
                    var op = document.createElement('option'); op.value = tn; op.textContent = tn;
                    if (tn === cur) { op.selected = true; }
                    og.appendChild(op);
                });
                typeSel.appendChild(og);
            });
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function onType() {
        var m = meta();
        var isElec = !!(m && m.elec);
        if (elecBox)  { elecBox.hidden = !isElec; }
        if (elecWarn) { elecWarn.hidden = !isElec; }
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    // ----- « Nouveau rayon » : adaptation au slug, specs libres, interrupteur électrique -----
    function autreElecToggle() {
        var on = !!(autreElecTog && autreElecTog.checked);
        if (autreElecBox)  { autreElecBox.hidden = !on; }
        if (autreElecWarn) { autreElecWarn.hidden = !on; }
        setEnabled();
    }
    function autreBuildSpecChips() {
        if (!autreSpecChips) { return; }
        var c = autreCfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        autreSpecChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-cuisine-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            autreSpecChips.appendChild(b);
        });
    }
    // Remplissage rapide de tailles : puces (selon le système de tailles du rayon)
    // qui pré-remplissent l'éditeur de déclinaisons générique partagé.
    function buildAutreSizeChips() {
        var box = document.querySelector('[data-cuisine-autre-size-chips]');
        var lab = document.querySelector('[data-cuisine-autre-size-label]');
        if (!box) { return; }
        var c = autreCfg();
        var btns = (c && c.sizes && AUTRE.size_systems) ? (AUTRE.size_systems[c.sizes] || []) : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-cuisine-autre-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function autreFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function adaptAutre() {
        var c = autreCfg();
        if (autreHint) {
            var rn = autreRayonName();
            autreHint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-autre-generic') || autreHint.textContent);
        }
        autreBuildSpecChips(); buildAutreSizeChips();
        if (c && autreAxisInp && !autreAxisInp.value.trim()) { autreAxisInp.value = c.axis || ''; }
        // Mode électrique par défaut depuis le rayon connu, sauf si l'utilisateur l'a réglé.
        if (c && autreElecTog && !autreElecTog.dataset.touched) { autreElecTog.checked = !!c.elec; autreElecToggle(); }
    }
    function addAutreSpec(label) {
        var tpl = document.getElementById('cuisine-autre-spec-template');
        if (!tpl || !tpl.content || !autreSpecsBox) { return; }
        autreSpecsBox.appendChild(tpl.content.cloneNode(true));
        var row = autreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAutreAtout() {
        var inp = document.querySelector('[data-cuisine-autre-atout-input]');
        var box = document.querySelector('[data-cuisine-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }

    // (dés)active la section selon le rayon courant : connu (root), nouveau rayon
    // (autreRoot) ou aucun. Les inputs cachés sont désactivés (non envoyés).
    function setEnabled() {
        var known = active(), isAutre = autreActive();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
            if (known && elecBox && elecBox.hidden) { elecBox.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = true; }); }
        }
        if (autreRoot) {
            autreRoot.hidden = !isAutre;
            autreRoot.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !isAutre; });
            if (isAutre && autreElecBox && autreElecBox.hidden) { autreElecBox.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = true; }); }
        }
    }
    // Changement de rayon → reconstruit la section active.
    function onColl() {
        if (active()) { rebuildRayon(); }
        else if (autreActive()) { adaptAutre(); }
        setEnabled();
    }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (collOther) { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (typeSel)   { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    if (autreElecTog) { autreElecTog.addEventListener('change', function () { this.dataset.touched = '1'; autreElecToggle(); }); }
    var autreAtoutInp = document.querySelector('[data-cuisine-autre-atout-input]');
    if (autreAtoutInp) { autreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAutreAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        if (ev.target.closest('[data-cuisine-autre-spec-add]')) { addAutreSpec(''); return; }
        if (ev.target.closest('[data-cuisine-autre-atout-add]')) { pushAutreAtout(); return; }
        var fill = ev.target.closest('[data-cuisine-autre-fill]');
        if (fill) { ev.preventDefault(); try { autreFill(JSON.parse(fill.getAttribute('data-cuisine-autre-fill') || '[]')); } catch (e) {} return; }
        var sp = ev.target.closest('[data-cuisine-autre-spec]');
        if (sp) { ev.preventDefault(); addAutreSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-cuisine-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-cuisine-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-cuisine-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });

    if (active()) { onType(); }
    else if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Aperçu fiche GÉNÉRIQUE (verticales hors beauté : Maison & meubles…).
   Reflète en direct comment le produit s'affichera sur la vitrine publique :
   photo principale, marque, nom, type, prix (+ promo & remise), stock /
   déclinaisons. Ne s'active QUE s'il n'y a pas de formulaire beauté (qui a son
   propre aperçu) ni d'autre [data-pv-root] déjà géré. CSP-safe (zéro inline). ---- */
(function () {
    var root = document.querySelector('[data-pv-root]');
    var form = document.getElementById('product-form');
    if (!root || !form || document.querySelector('[data-beauty]')) { return; }
    var curInt = root.getAttribute('data-cur-int') === '1';
    var cur = root.getAttribute('data-cur') || '';
    function out(n) { return root.querySelector('[data-pv-out="' + n + '"]'); }
    // Lit le champ [data-pv] ACTIF (non désactivé) : plusieurs sections partagent une clé.
    function fval(n) {
        var list = document.querySelectorAll('[data-pv="' + n + '"]');
        for (var i = 0; i < list.length; i++) { if (!list[i].disabled) { return String(list[i].value || '').trim(); } }
        return list[0] ? String(list[0].value || '').trim() : '';
    }
    function num(s) { return parseFloat(String(s).replace(',', '.')) || 0; }
    function fmt(n) { n = curInt ? Math.round(n) : Math.round(n * 100) / 100; return n.toLocaleString('fr-FR', curInt ? {} : { maximumFractionDigits: 2 }) + ' ' + cur; }
    function setText(n, t) { var el = out(n); if (el) { el.textContent = t; } }
    var nameOut = out('name'); var nameDefault = nameOut ? nameOut.textContent : '';
    var imgBox = root.querySelector('[data-pv-img]'); var imgEmpty = imgBox ? imgBox.innerHTML : '';

    function update() {
        setText('brand', fval('brand'));
        if (nameOut) { nameOut.textContent = fval('name') || nameDefault; }
        setText('type', fval('type'));
        setText('vol', '');
        var price = num(fval('price')), promo = num(fval('promo'));
        var now = (promo > 0 && promo < price) ? promo : price;
        setText('price', now > 0 ? fmt(now) : '');
        var old = out('old'), badge = out('disc');
        if (promo > 0 && promo < price) {
            if (old) { old.textContent = fmt(price); old.hidden = false; }
            if (badge) { badge.textContent = '-' + Math.round((1 - promo / price) * 100) + '%'; badge.hidden = false; }
        } else { if (old) { old.hidden = true; } if (badge) { badge.hidden = true; } }
        // Note : nombre de déclinaisons (lignes remplies) sinon stock.
        var opts = 0;
        form.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { if (!i.disabled && String(i.value || '').trim() !== '') { opts++; } });
        var stockF = document.getElementById('p-stock'); var s = stockF ? String(stockF.value || '').trim() : '';
        var note = opts > 0 ? (opts + ' déclinaison' + (opts > 1 ? 's' : ''))
            : (s !== '' ? (s + ' en stock') : '');
        setText('stock', note);
    }
    function syncPhoto() {
        if (!imgBox) { return; }
        var src = document.querySelector('#product-previews .preview:not(.preview-video) img');
        if (src && src.getAttribute('src')) {
            imgBox.innerHTML = ''; var img = document.createElement('img'); img.src = src.getAttribute('src'); img.alt = ''; imgBox.appendChild(img);
        } else if (!imgBox.querySelector('.pv-img-empty')) { imgBox.innerHTML = imgEmpty; }
    }
    form.addEventListener('input', update);
    form.addEventListener('change', function () { update(); syncPhoto(); });
    var previews = document.getElementById('product-previews');
    if (previews && window.MutationObserver) { new MutationObserver(syncPhoto).observe(previews, { childList: true, subtree: true }); }
    update(); syncPhoto();
})();

/* ---- Alimentation : rayons adaptatifs au type (Bio & naturel…).
   Le type pilote les caractéristiques, la conservation par défaut et l'axe de
   déclinaison (Poids / Contenance). Conservation au froid → rappel chaîne du
   froid. Allergènes (multi). Remplissage rapide des tailles. CSP-safe. ---- */
(function () {
    var cfgEl = document.querySelector('[data-alim]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var AMBIENT = cfgEl.getAttribute('data-ambient') || 'Ambiante / sèche';
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-alim-root]');
    var typeSel   = document.querySelector('[data-alim-type]');
    var attrsBox  = document.querySelector('[data-alim-attrs]');
    var atoutsBox = document.querySelector('[data-alim-atouts]');
    var conservSel= document.querySelector('[data-alim-conserv]');
    var coldNote  = document.querySelector('[data-alim-cold-note]');
    var alcNote   = document.querySelector('[data-alim-alc-note]');
    var dlcSel    = document.querySelector('[data-alim-dlc]');
    var hint      = document.querySelector('[data-alim-hint]');
    var axisInp   = document.querySelector('[data-alim-axis]');
    // « Nouveau rayon » Alimentation : specs libres adaptées au slug du rayon saisi.
    var AUTRE = parse('data-autre');
    var collOther     = document.querySelector('[data-collection-other]');
    var autreRoot     = document.querySelector('[data-alim-autre-root]');
    var autreHint     = document.querySelector('[data-alim-autre-hint]');
    var autreSpecsBox = document.querySelector('[data-alim-autre-specs]');
    var autreSpecChips= document.querySelector('[data-alim-autre-spec-chips]');
    var autreConserv  = document.querySelector('[data-alim-autre-conserv]');
    var autreDlc      = document.querySelector('[data-alim-autre-dlc]');
    var autreColdNote = document.querySelector('[data-alim-autre-cold-note]');
    var autreBabyNote = document.querySelector('[data-alim-autre-baby-note]');
    var autreAxisInp  = document.querySelector('[data-alim-autre-axis]');
    if (!root && !autreRoot) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function autreActive() { return !!(coll && coll.value !== '' && !RAYONS[coll.value]); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayonName())] || null; }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    // Reconstruit le sélecteur de type (groupes ou liste à plat) + les atouts au changement de rayon.
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-alim-size-chips]');
        var lab = document.querySelector('[data-alim-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-alim-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function coldToggle() {
        if (!coldNote) { return; }
        var v = conservSel ? String(conservSel.value || '') : '';
        coldNote.hidden = !(v !== '' && v !== AMBIENT);
    }
    function onType() {
        var m = meta();
        // Conservation par défaut selon le type, sauf si le vendeur l'a réglée.
        if (m && conservSel && !conservSel.dataset.touched && m.conserv) { conservSel.value = m.conserv; }
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        // Type de date par défaut : DLC pour un produit frais/surgelé, DDM sinon.
        if (m && dlcSel && !dlcSel.dataset.touched && m.conserv) {
            var dopts = Array.prototype.map.call(dlcSel.options, function (o) { return o.value; }).filter(function (v) { return v !== ''; });
            dlcSel.value = (m.conserv !== AMBIENT) ? (dopts[0] || '') : (dopts[1] || dopts[0] || '');
        }
        if (alcNote) { alcNote.hidden = !(m && m.alcool); }
        buildAttrs(); buildSizeChips(); coldToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function alimFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    // ----- « Nouveau rayon » Alimentation : adaptation au slug, specs libres -----
    function autreColdToggle() {
        if (!autreColdNote) { return; }
        var v = autreConserv ? String(autreConserv.value || '') : '';
        autreColdNote.hidden = !(v !== '' && v !== AMBIENT);
    }
    function autreBabyToggle() {
        if (!autreBabyNote) { return; }
        var c = autreCfg();
        var baby = !!(c && c.baby) || /b[ée]b[ée]|nourrisson/i.test(autreRayonName());
        autreBabyNote.hidden = !baby;
    }
    // Type de date par défaut : DLC pour frais/surgelé, DDM sinon (sauf si réglé).
    function autreSyncDlc(force) {
        if (!autreDlc || (!force && autreDlc.dataset.touched)) { return; }
        var v = autreConserv ? String(autreConserv.value || '') : '';
        var dopts = Array.prototype.map.call(autreDlc.options, function (o) { return o.value; }).filter(function (x) { return x !== ''; });
        autreDlc.value = (v !== '' && v !== AMBIENT) ? (dopts[0] || '') : (dopts[1] || dopts[0] || '');
    }
    function autreBuildSpecChips() {
        if (!autreSpecChips) { return; }
        var c = autreCfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        autreSpecChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-alim-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            autreSpecChips.appendChild(b);
        });
    }
    // Remplissage rapide de tailles : selon l'axe (Poids / Contenance) → réutilise SIZES.
    function buildAutreSizeChips() {
        var box = document.querySelector('[data-alim-autre-size-chips]');
        var lab = document.querySelector('[data-alim-autre-size-label]');
        if (!box) { return; }
        var c = autreCfg();
        var axis = (c && c.axis) ? c.axis : (autreAxisInp ? String(autreAxisInp.value || '').trim() : '');
        var btns = (axis && SIZES[axis]) ? SIZES[axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-alim-autre-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function addAutreSpec(label) {
        var tpl = document.getElementById('alim-autre-spec-template');
        if (!tpl || !tpl.content || !autreSpecsBox) { return; }
        autreSpecsBox.appendChild(tpl.content.cloneNode(true));
        var row = autreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAutreAtout() {
        var inp = document.querySelector('[data-alim-autre-atout-input]');
        var box = document.querySelector('[data-alim-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }
    function adaptAutre() {
        var c = autreCfg();
        if (autreHint) {
            var rn = autreRayonName();
            autreHint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-autre-generic') || autreHint.textContent);
        }
        autreBuildSpecChips(); buildAutreSizeChips();
        if (c && autreAxisInp && !autreAxisInp.value.trim()) { autreAxisInp.value = c.axis || ''; }
        // Conservation par défaut depuis le rayon connu, sauf si le vendeur l'a réglée.
        if (c && autreConserv && !autreConserv.dataset.touched && c.conserv) { autreConserv.value = c.conserv; }
        autreSyncDlc(false); autreColdToggle(); autreBabyToggle();
    }

    function setEnabled() {
        var known = active(), isAutre = autreActive();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
        if (autreRoot) {
            autreRoot.hidden = !isAutre;
            autreRoot.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !isAutre; });
        }
    }
    function onColl() {
        if (active()) { rebuildRayon(); }
        else if (autreActive()) { adaptAutre(); }
        setEnabled();
    }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (collOther) { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (typeSel)   { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    if (conservSel){ conservSel.addEventListener('change', function () { this.dataset.touched = '1'; coldToggle(); }); }
    if (dlcSel)    { dlcSel.addEventListener('change', function () { this.dataset.touched = '1'; }); }
    if (autreConserv) { autreConserv.addEventListener('change', function () { this.dataset.touched = '1'; autreSyncDlc(true); autreColdToggle(); }); }
    if (autreDlc)     { autreDlc.addEventListener('change', function () { this.dataset.touched = '1'; }); }
    if (autreAxisInp) { autreAxisInp.addEventListener('input', buildAutreSizeChips); }
    var autreAtoutInp = document.querySelector('[data-alim-autre-atout-input]');
    if (autreAtoutInp) { autreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAutreAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-alim-fill]');
        if (fill) { ev.preventDefault(); try { alimFill(JSON.parse(fill.getAttribute('data-alim-fill') || '[]')); } catch (e) {} return; }
        var afill = ev.target.closest('[data-alim-autre-fill]');
        if (afill) { ev.preventDefault(); try { alimFill(JSON.parse(afill.getAttribute('data-alim-autre-fill') || '[]')); } catch (e) {} return; }
        if (ev.target.closest('[data-alim-autre-spec-add]')) { ev.preventDefault(); addAutreSpec(''); return; }
        if (ev.target.closest('[data-alim-autre-atout-add]')) { ev.preventDefault(); pushAutreAtout(); return; }
        var sp = ev.target.closest('[data-alim-autre-spec]');
        if (sp) { ev.preventDefault(); addAutreSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-alim-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-alim-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-alim-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });
    if (active()) { onType(); }
    else if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : rayon « Alimentation » adaptatif au TYPE.
   Le TYPE pilote les caractéristiques (texture / conditionnement), l'âge minimum
   (souvent imposé), la conservation + DLC par défaut, les allergènes / régime, et
   les GARDE-FOUS réglementaires : préparation pour nourrissons 0–6 mois → promotion
   VERROUILLÉE + note de conformité ; laits infantiles & compléments → notes dédiées ;
   chaîne du froid. CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS  = parse('data-rayons');
    var SIZES   = parse('data-size-systems');
    var AMBIENT = cfgEl.getAttribute('data-ambient') || 'Ambiante';
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-bebe-root]');
    var typeSel   = document.querySelector('[data-bebe-type]');
    var attrsBox  = document.querySelector('[data-bebe-attrs]');
    var atoutsBox = document.querySelector('[data-bebe-atouts]');
    var conservSel= document.querySelector('[data-bebe-conserv]');
    var coldNote  = document.querySelector('[data-bebe-cold-note]');
    var dlcSel    = document.querySelector('[data-bebe-dlc]');
    var hint      = document.querySelector('[data-bebe-hint]');
    var axisInp   = document.querySelector('[data-bebe-axis]');
    var ageFix    = document.querySelector('[data-bebe-age-fix]');
    var ageSel    = document.querySelector('[data-bebe-age]');
    var allergWrap= document.querySelector('[data-bebe-allerg-wrap]');
    var regimeWrap= document.querySelector('[data-bebe-regime-wrap]');
    var noteF1    = document.querySelector('[data-bebe-note-formula1]');
    var noteForm  = document.querySelector('[data-bebe-note-formula]');
    var noteCompl = document.querySelector('[data-bebe-note-complement]');
    var noteBaby  = document.querySelector('[data-bebe-note-baby]');
    // Section promotion PARTAGÉE : verrou réglementaire (préparation 0–6 mois).
    var promoBox      = document.querySelector('[data-promo-box]');
    var promoFields   = document.querySelector('[data-promo-fields]');
    var promoLockNote = document.querySelector('[data-promo-lock-note]');
    var promoLockText = document.querySelector('[data-promo-lock-text]');
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function fieldsOf(m) { return (m && m.fields) ? m.fields : []; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; } // seuls texture / portion ont une définition
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-size-chips]');
        var lab = document.querySelector('[data-bebe-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function coldToggle() {
        if (!coldNote) { return; }
        var v = conservSel ? String(conservSel.value || '') : '';
        coldNote.hidden = !(v !== '' && v !== AMBIENT);
    }
    function ageToggle() {
        var m = meta();
        var fix = (m && m.age_fix) ? String(m.age_fix) : '';
        if (ageFix) { ageFix.hidden = (fix === ''); ageFix.textContent = fix; }
        if (ageSel) { ageSel.hidden = (fix !== ''); ageSel.disabled = (fix !== '') || !active(); }
    }
    function toggleBox(wrap, on) {
        if (!wrap) { return; }
        wrap.querySelectorAll('input').forEach(function (i) { i.disabled = !on || !active(); });
    }
    function promoLock(lock) {
        if (!promoBox) { return; }
        var doLock = !!lock && active();
        if (promoFields) {
            promoFields.querySelectorAll('input').forEach(function (i) { i.disabled = doLock; if (doLock) { i.value = ''; } });
        }
        if (promoLockText) { promoLockText.textContent = cfgEl.getAttribute('data-promo-lock') || ''; }
        if (promoLockNote) { promoLockNote.hidden = !doLock; }
        if (doLock && promoBox.open) { promoBox.open = false; }
    }
    function notesToggle() {
        var m = meta();
        var f1 = !!(m && m.formula1);
        show(noteF1, f1);
        show(noteForm, !!(m && m.formula) && !f1);
        show(noteCompl, !!(m && m.complement));
        show(noteBaby, !!m);
        var f = fieldsOf(m);
        show(allergWrap, f.indexOf('allerg') > -1);
        show(regimeWrap, f.indexOf('regime') > -1);
        toggleBox(allergWrap, f.indexOf('allerg') > -1);
        toggleBox(regimeWrap, f.indexOf('regime') > -1);
        promoLock(f1);
    }
    function onType() {
        var m = meta();
        if (m && conservSel && !conservSel.dataset.touched && m.conserv) { conservSel.value = m.conserv; }
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        if (m && dlcSel && !dlcSel.dataset.touched && m.conserv) {
            var dopts = Array.prototype.map.call(dlcSel.options, function (o) { return o.value; }).filter(function (v) { return v !== ''; });
            dlcSel.value = (m.conserv !== AMBIENT) ? (dopts[0] || '') : (dopts[1] || dopts[0] || '');
        }
        buildAttrs(); buildSizeChips(); coldToggle(); ageToggle(); notesToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function bebeFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
        // Réapplique les états dérivés après (ré)activation, sinon relâche le verrou promo.
        if (known) { ageToggle(); notesToggle(); } else { promoLock(false); }
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (typeSel)   { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    if (conservSel){ conservSel.addEventListener('change', function () { this.dataset.touched = '1'; coldToggle(); }); }
    if (dlcSel)    { dlcSel.addEventListener('change', function () { this.dataset.touched = '1'; }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-fill]');
        if (fill) { ev.preventDefault(); try { bebeFill(JSON.parse(fill.getAttribute('data-bebe-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : rayon « Jouets » adaptatif au TYPE.
   Le TYPE pilote les caractéristiques (matière, piles, compétence…), l'axe et le
   remplissage couleur. Garde-fous SÉCURITÉ ENFANT : marquage CE/EN71 (note si absent),
   cohérence ÂGE ↔ PETITES PIÈCES (jouet < 36 mois = aucune petite pièce, incohérence
   bloquante + mention « 3 ans+ » sinon), note piles bouton. CSP-safe. ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe-toy]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var AGES        = parse('data-ages');
    var AGES_U3     = parse('data-ages-under3');
    var AGE_DEFAULT = cfgEl.getAttribute('data-age-default') || '';
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-bebe-toy-root]');
    var typeSel   = document.querySelector('[data-bebe-toy-type]');
    var attrsBox  = document.querySelector('[data-bebe-toy-attrs]');
    var atoutsBox = document.querySelector('[data-bebe-toy-atouts]');
    var hint      = document.querySelector('[data-bebe-toy-hint]');
    var axisInp   = document.querySelector('[data-bebe-toy-axis]');
    var ageSel    = document.querySelector('[data-bebe-toy-age]');
    var ageHint   = document.querySelector('[data-bebe-toy-age-hint]');
    var ceChk     = document.querySelector('[data-bebe-toy-ce]');
    var smallChk  = document.querySelector('[data-bebe-toy-small]');
    var ceNote    = document.querySelector('[data-bebe-toy-ce-note]');
    var u3Note    = document.querySelector('[data-bebe-toy-u3-note]');
    var conflict  = document.querySelector('[data-bebe-toy-conflict]');
    var note3     = document.querySelector('[data-bebe-toy-3-note]');
    var pilesNote = document.querySelector('[data-bebe-toy-piles-note]');
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function isUnder3() { return ageSel && AGES_U3.indexOf(String(ageSel.value || '')) > -1; }
    function pilesVal() { var s = attrsBox && attrsBox.querySelector('select[name="attr[piles]"]'); return s ? String(s.value || '') : ''; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']'; sel.setAttribute('data-bebe-toy-field', key);
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    // L'âge se restreint à la tranche 0–3 ans pour les types « tout-petits » (age_fix).
    function ageRebuild() {
        if (!ageSel) { return; }
        var m = meta();
        var fix = !!(m && m.age_fix);
        var list = fix ? AGES_U3 : AGES;
        var cur = String(ageSel.value || '');
        ageSel.innerHTML = '';
        var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; ageSel.appendChild(o0);
        list.forEach(function (a) { var op = document.createElement('option'); op.value = a; op.textContent = a; ageSel.appendChild(op); });
        if (list.indexOf(cur) > -1) { ageSel.value = cur; }
        else if (fix && list.indexOf(AGE_DEFAULT) > -1) { ageSel.value = AGE_DEFAULT; }
        else { ageSel.value = ''; }
        if (ageHint) { ageHint.hidden = !fix; }
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-toy-size-chips]');
        var lab = document.querySelector('[data-bebe-toy-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-toy-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function safetyToggle() {
        if (ceNote) { ceNote.hidden = !!(ceChk && ceChk.checked); }
        var u3 = isUnder3();
        var small = !!(smallChk && smallChk.checked);
        show(u3Note, u3);
        show(conflict, u3 && small);
        show(note3, !u3 && small);
        var pv = pilesVal();
        show(pilesNote, pv !== '' && pv !== 'Sans pile');
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); ageRebuild(); buildSizeChips(); safetyToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function toyFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)     { coll.addEventListener('change', onColl); }
    if (typeSel)  { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    if (ageSel)   { ageSel.addEventListener('change', safetyToggle); }
    if (ceChk)    { ceChk.addEventListener('change', safetyToggle); }
    if (smallChk) { smallChk.addEventListener('change', safetyToggle); }
    if (attrsBox) { attrsBox.addEventListener('change', function (ev) { if (ev.target && ev.target.name === 'attr[piles]') { safetyToggle(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-toy-fill]');
        if (fill) { ev.preventDefault(); try { toyFill(JSON.parse(fill.getAttribute('data-bebe-toy-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : rayon « Puériculture » adaptatif au TYPE.
   Le TYPE pilote les caractéristiques (groupe, norme, ISOFIX, matière, piles…),
   l'axe et le remplissage couleur. Garde-fous SÉCURITÉ par produit : conformité
   CE/norme (note si absente) ; siège-auto (note R129/ISOFIX/dos-route + alerte
   OCCASION) ; lit (EN716) ; chaise haute (EN14988) ; électrique (CE/garantie) ;
   biberon (sans BPA). Valeurs par défaut siège-auto (norme/sens). CSP-safe. ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe-puer]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var CARSEAT_DEF = parse('data-carseat-defaults');
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-bebe-puer-root]');
    var typeSel   = document.querySelector('[data-bebe-puer-type]');
    var condSel   = document.querySelector('[data-bebe-puer-cond]');
    var attrsBox  = document.querySelector('[data-bebe-puer-attrs]');
    var atoutsBox = document.querySelector('[data-bebe-puer-atouts]');
    var hint      = document.querySelector('[data-bebe-puer-hint]');
    var axisInp   = document.querySelector('[data-bebe-puer-axis]');
    var ceChk     = document.querySelector('[data-bebe-puer-ce]');
    var ceNote    = document.querySelector('[data-bebe-puer-ce-note]');
    var csNote    = document.querySelector('[data-bebe-puer-carseat-note]');
    var csOccNote = document.querySelector('[data-bebe-puer-carseat-occ-note]');
    var bedNote   = document.querySelector('[data-bebe-puer-bed-note]');
    var chairNote = document.querySelector('[data-bebe-puer-chair-note]');
    var elecNote  = document.querySelector('[data-bebe-puer-elec-note]');
    var bottleNote= document.querySelector('[data-bebe-puer-bottle-note]');
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function isOccasion() { var v = condSel ? String(condSel.value || '') : ''; return v === 'Occasion' || v === 'Reconditionné'; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
        // Valeurs par défaut siège-auto (norme R129 / dos à la route) si non renseignées.
        if (m.carseat) {
            Object.keys(CARSEAT_DEF).forEach(function (k) {
                var s = attrsBox.querySelector('select[name="attr[' + k + ']"]');
                if (s && (s.value === '' || !prev[k])) {
                    if (Array.prototype.some.call(s.options, function (o) { return o.value === CARSEAT_DEF[k]; })) { s.value = CARSEAT_DEF[k]; }
                }
            });
        }
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-puer-size-chips]');
        var lab = document.querySelector('[data-bebe-puer-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-puer-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function safetyToggle() {
        var m = meta();
        if (ceNote) { ceNote.hidden = !!(ceChk && ceChk.checked); }
        var carseat = !!(m && m.carseat);
        show(csNote, carseat);
        show(csOccNote, carseat && isOccasion());
        show(bedNote, !!(m && m.bed));
        show(chairNote, !!(m && m.chair));
        show(elecNote, !!(m && m.elec));
        show(bottleNote, !!(m && m.bottle));
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips(); safetyToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function puerFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)     { coll.addEventListener('change', onColl); }
    if (typeSel)  { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    if (condSel)  { condSel.addEventListener('change', safetyToggle); }
    if (ceChk)    { ceChk.addEventListener('change', safetyToggle); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-puer-fill]');
        if (fill) { ev.preventDefault(); try { puerFill(JSON.parse(fill.getAttribute('data-bebe-puer-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : rayon « Soins » adaptatif au TYPE (hygiène / santé).
   Le TYPE pilote les caractéristiques, les labels (chips), la date de péremption
   et les rappels : cosmétique bébé (UE 1223/2009, INCI, PAO) ; solaire (SPF +
   < 6 mois) ; dispositif médical (CE) ; complément (avis santé) ; couche (taille).
   État FIGÉ « neuf scellé » (hygiène). CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe-soin]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var SUN_DEFAULT = cfgEl.getAttribute('data-sun-default') || '';
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-bebe-soin-root]');
    var typeSel   = document.querySelector('[data-bebe-soin-type]');
    var attrsBox  = document.querySelector('[data-bebe-soin-attrs]');
    var atoutsBox = document.querySelector('[data-bebe-soin-atouts]');
    var hint      = document.querySelector('[data-bebe-soin-hint]');
    var axisInp   = document.querySelector('[data-bebe-soin-axis]');
    var labelsWrap= document.querySelector('[data-bebe-soin-labels-wrap]');
    var peremWrap = document.querySelector('[data-bebe-soin-perem-wrap]');
    var cosmNote  = document.querySelector('[data-bebe-soin-cosmetic-note]');
    var sunNote   = document.querySelector('[data-bebe-soin-sun-note]');
    var medNote   = document.querySelector('[data-bebe-soin-medical-note]');
    var supNote   = document.querySelector('[data-bebe-soin-supplement-note]');
    var diaNote   = document.querySelector('[data-bebe-soin-diaper-note]');
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }
    function fieldsOf(m) { return (m && m.fields) ? m.fields : []; }
    function toggleBox(wrap, on) { if (wrap) { wrap.querySelectorAll('input').forEach(function (i) { i.disabled = !on || !active(); }); } }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; } // 'labels' = chips, pas un select
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
        // Solaire : SPF par défaut si non renseigné.
        if (m.sun && SUN_DEFAULT) {
            var s = attrsBox.querySelector('select[name="attr[spf]"]');
            if (s && s.value === '' && Array.prototype.some.call(s.options, function (o) { return o.value === SUN_DEFAULT; })) { s.value = SUN_DEFAULT; }
        }
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-soin-size-chips]');
        var lab = document.querySelector('[data-bebe-soin-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-soin-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function safetyToggle() {
        var m = meta();
        show(cosmNote, !!(m && m.cosmetic));
        show(sunNote, !!(m && m.sun));
        show(medNote, !!(m && m.medical));
        show(supNote, !!(m && m.supplement));
        show(diaNote, !!(m && m.diaper));
        var perem = !!(m && (m.cosmetic || m.medical || m.supplement));
        show(peremWrap, perem); toggleBox(peremWrap, perem);
        var labels = fieldsOf(m).indexOf('labels') > -1;
        show(labelsWrap, labels); toggleBox(labelsWrap, labels);
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips(); safetyToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function soinFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
        if (known) { safetyToggle(); } // réapplique l'état des wraps masqués (labels / péremption)
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)    { coll.addEventListener('change', onColl); }
    if (typeSel) { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-soin-fill]');
        if (fill) { ev.preventDefault(); try { soinFill(JSON.parse(fill.getAttribute('data-bebe-soin-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : rayon « Vêtements bébé » adaptatif au TYPE.
   Le TYPE pilote les caractéristiques (taille/âge, matière, saison, TOG…), l'axe et
   le remplissage (tailles par âge / couleurs). Garde-fous sécurité textile : rappel
   EN14682 (aucun cordon au cou) ; gigoteuse → note TOG. Défauts par type. CSP-safe. ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe-vet]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-bebe-vet-root]');
    var typeSel   = document.querySelector('[data-bebe-vet-type]');
    var attrsBox  = document.querySelector('[data-bebe-vet-attrs]');
    var atoutsBox = document.querySelector('[data-bebe-vet-atouts]');
    var hint      = document.querySelector('[data-bebe-vet-hint]');
    var axisInp   = document.querySelector('[data-bebe-vet-axis]');
    var enNote    = document.querySelector('[data-bebe-vet-en-note]');
    var sleepNote = document.querySelector('[data-bebe-vet-sleep-note]');
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
        // Valeurs par défaut du type (ex. gigoteuse → 2,5 TOG) si non renseignées.
        var defs = m.defaults || {};
        Object.keys(defs).forEach(function (k) {
            var s = attrsBox.querySelector('select[name="attr[' + k + ']"]');
            if (s && s.value === '' && !prev[k] && Array.prototype.some.call(s.options, function (o) { return o.value === defs[k]; })) { s.value = defs[k]; }
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-vet-size-chips]');
        var lab = document.querySelector('[data-bebe-vet-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-vet-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function safetyToggle() {
        var m = meta();
        show(enNote, !!m);            // rappel EN14682 dès qu'un type est choisi
        show(sleepNote, !!(m && m.sleep));
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips(); safetyToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function vetFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)    { coll.addEventListener('change', onColl); }
    if (typeSel) { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-vet-fill]');
        if (fill) { ev.preventDefault(); try { vetFill(JSON.parse(fill.getAttribute('data-bebe-vet-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Bébé & Enfant : « Nouveau rayon » générique adaptatif au SLUG du rayon saisi.
   Specs suggérées (R par slug) ou génériques, axe + remplissage, conformité CE
   (défaut selon le rayon). Carte sécurité enfant systématique (rappel EN + CE).
   Caractéristiques et atouts libres. CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-bebe-autre]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var AUTRE = parse('data-autre');
    var SIZES = parse('data-size-systems');
    var KNOWN = parse('data-known');
    var coll       = document.querySelector('[data-collection-select]');
    var collOther  = document.querySelector('[data-collection-other]');
    var root       = document.querySelector('[data-bebe-autre-root]');
    var hint       = document.querySelector('[data-bebe-autre-hint]');
    var specChips  = document.querySelector('[data-bebe-autre-spec-chips]');
    var specsBox   = document.querySelector('[data-bebe-autre-specs]');
    var axisInp    = document.querySelector('[data-bebe-autre-axis]');
    var ceChk      = document.querySelector('[data-bebe-autre-ce]');
    var ceNote     = document.querySelector('[data-bebe-autre-ce-note]');
    if (!root) { return; }

    function knownHas(v) { return (KNOWN || []).indexOf(v) > -1; }
    function autreActive() { return !!(coll && coll.value !== '' && !knownHas(coll.value)); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function rayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function cfg() { return (AUTRE.R || {})[autreSlug(rayonName())] || null; }

    function buildSpecChips() {
        if (!specChips) { return; }
        var c = cfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        specChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-bebe-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            specChips.appendChild(b);
        });
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-bebe-autre-size-chips]');
        var lab = document.querySelector('[data-bebe-autre-size-label]');
        if (!box) { return; }
        var c = cfg();
        var axis = (c && c.axis) ? c.axis : (axisInp ? String(axisInp.value || '').trim() : '');
        var btns = (axis && SIZES[axis]) ? SIZES[axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-bebe-autre-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function addSpec(label) {
        var tpl = document.getElementById('bebe-autre-spec-template');
        if (!tpl || !tpl.content || !specsBox) { return; }
        specsBox.appendChild(tpl.content.cloneNode(true));
        var row = specsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAtout() {
        var inp = document.querySelector('[data-bebe-autre-atout-input]');
        var box = document.querySelector('[data-bebe-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }
    function adaptAutre() {
        var c = cfg();
        if (hint) {
            var rn = rayonName();
            hint.textContent = c ? ((cfgEl.getAttribute('data-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-generic') || hint.textContent);
        }
        buildSpecChips(); buildSizeChips();
        if (c && axisInp && !axisInp.value.trim()) { axisInp.value = c.axis || ''; }
        if (ceChk && !ceChk.dataset.touched) { ceChk.checked = !!(c && c.ce); }
        if (ceNote) { ceNote.hidden = !(c && c.ce); }
    }
    function setEnabled() {
        var on = autreActive();
        root.hidden = !on;
        root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
    }
    function onColl() { if (autreActive()) { adaptAutre(); } setEnabled(); }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (collOther) { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (ceChk)     { ceChk.addEventListener('change', function () { this.dataset.touched = '1'; }); }
    if (axisInp)   { axisInp.addEventListener('input', buildSizeChips); }
    var atoutInp = document.querySelector('[data-bebe-autre-atout-input]');
    if (atoutInp) { atoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-bebe-autre-fill]');
        if (fill) {
            ev.preventDefault();
            var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
            var tpl = document.getElementById('variant-template');
            if (rowsBox && tpl && tpl.content) {
                var have = {};
                rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
                var list = []; try { list = JSON.parse(fill.getAttribute('data-bebe-autre-fill') || '[]'); } catch (e) {}
                list.forEach(function (sz) {
                    var key = String(sz).trim().toLowerCase();
                    if (key === '' || have[key]) { return; }
                    rowsBox.appendChild(tpl.content.cloneNode(true));
                    var r = rowsBox.lastElementChild; var inp = r && r.querySelector('input[name="var_size[]"]');
                    if (inp) { inp.value = sz; } have[key] = true;
                });
                var det = rowsBox.closest('details'); if (det) { det.open = true; }
            }
            return;
        }
        if (ev.target.closest('[data-bebe-autre-spec-add]')) { ev.preventDefault(); addSpec(''); return; }
        if (ev.target.closest('[data-bebe-autre-atout-add]')) { ev.preventDefault(); pushAtout(); return; }
        var sp = ev.target.closest('[data-bebe-autre-spec]');
        if (sp) { ev.preventDefault(); addSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-bebe-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-bebe-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-bebe-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });
    if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Sport & loisirs : rayons adaptatifs au type (Chaussures…).
   Le TYPE pilote les caractéristiques, l'axe (Pointure) et des repères contextuels :
   crampons (note terrain FG/SG/AG/IN-TF) ; chaussures aquatiques (séchage / antidérapant).
   Valeurs par défaut par type (ex. crampons → terrain ferme). CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-sport]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-sport-root]');
    var typeSel   = document.querySelector('[data-sport-type]');
    var attrsBox  = document.querySelector('[data-sport-attrs]');
    var atoutsBox = document.querySelector('[data-sport-atouts]');
    var hint      = document.querySelector('[data-sport-hint]');
    var axisInp   = document.querySelector('[data-sport-axis]');
    var cleatsNote= document.querySelector('[data-sport-cleats-note]');
    var waterNote = document.querySelector('[data-sport-water-note]');
    var elecNote  = document.querySelector('[data-sport-elec-note]');
    var heavyNote = document.querySelector('[data-sport-heavy-note]');
    var weightNote= document.querySelector('[data-sport-weight-note]');
    var pairWrap  = document.querySelector('[data-sport-pair-wrap]');
    var teamWrap  = document.querySelector('[data-sport-team-wrap]');
    var condSel   = document.querySelector('[data-sport-cond]');
    var condFixed = document.querySelector('[data-sport-cond-fixed]');
    var ceWrap    = document.querySelector('[data-sport-ce-wrap]');
    var FLAG_NOTES = ['shelter', 'sleep', 'pack', 'fire', 'light', 'watersport', 'fishing', 'team', 'swim', 'hygiene', 'ball', 'racket', 'protect', 'helmet', 'punchbag'].map(function (k) {
        return { k: k, el: document.querySelector('[data-sport-' + k + '-note]') };
    });
    if (!root) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }
    function show(el, on) { if (el) { el.hidden = !on; } }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
        // Valeurs par défaut du type (ex. crampons → terrain ferme) si non renseignées.
        var defs = m.defaults || {};
        Object.keys(defs).forEach(function (k) {
            var s = attrsBox.querySelector('select[name="attr[' + k + ']"]');
            if (s && s.value === '' && !prev[k] && Array.prototype.some.call(s.options, function (o) { return o.value === defs[k]; })) { s.value = defs[k]; }
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-sport-size-chips]');
        var lab = document.querySelector('[data-sport-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-sport-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function notesToggle() {
        var m = meta();
        show(cleatsNote, !!(m && m.cleats));
        show(waterNote, !!(m && m.water));
        show(elecNote, !!(m && m.elec));
        show(heavyNote, !!(m && m.heavy));
        show(weightNote, !!(m && m.weight));
        FLAG_NOTES.forEach(function (n) { show(n.el, !!(m && m[n.k])); });
        // Vente à la paire : seulement pour les poids (anti-soumission fantôme).
        var weight = !!(m && m.weight);
        show(pairWrap, weight);
        if (pairWrap) { pairWrap.querySelectorAll('input').forEach(function (i) { i.disabled = !weight || !active(); }); }
        // Maillot d'équipe : version + floquage (anti-soumission fantôme).
        var team = !!(m && m.team);
        show(teamWrap, team);
        if (teamWrap) { teamWrap.querySelectorAll('input, select').forEach(function (i) { i.disabled = !team || !active(); }); }
        // Hygiène (maillot de bain / sous-vêtement) : état figé « neuf scellé ».
        var hyg = !!(m && m.hygiene);
        show(condFixed, hyg);
        if (condSel) { condSel.hidden = hyg; condSel.disabled = hyg || !active(); }
        // Conformité CE : seulement pour les protections / casques.
        var ce = !!(m && (m.protect || m.helmet));
        show(ceWrap, ce);
        if (ceWrap) { ceWrap.querySelectorAll('input').forEach(function (i) { i.disabled = !ce || !active(); }); }
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips(); notesToggle();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function sportFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    function setEnabled() {
        var known = active();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
        }
        if (known) { notesToggle(); } // réapplique l'état du toggle « par paire » (masqué = désactivé)
    }
    function onColl() { if (active()) { rebuildRayon(); } setEnabled(); }

    if (coll)    { coll.addEventListener('change', onColl); }
    if (typeSel) { typeSel.addEventListener('change', function () { onType(); setEnabled(); }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-sport-fill]');
        if (fill) { ev.preventDefault(); try { sportFill(JSON.parse(fill.getAttribute('data-sport-fill') || '[]')); } catch (e) {} }
    });
    if (active()) { onType(); }
    setEnabled();
})();

/* ---- Sport & loisirs : « Nouveau rayon » générique adaptatif au SLUG du rayon saisi.
   Specs suggérées (R par slug) ou génériques, axe + remplissage, garde-fous (CE
   protections/casques ; DLC nutrition ; gilet nautique ; CE électronique sport).
   Caractéristiques et atouts libres. CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-sport-autre]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var AUTRE = parse('data-autre');
    var SIZES = parse('data-size-systems');
    var KNOWN = parse('data-known');
    var coll        = document.querySelector('[data-collection-select]');
    var collOther   = document.querySelector('[data-collection-other]');
    var root        = document.querySelector('[data-sport-autre-root]');
    var hint        = document.querySelector('[data-sport-autre-hint]');
    var specChips   = document.querySelector('[data-sport-autre-spec-chips]');
    var specsBox    = document.querySelector('[data-sport-autre-specs]');
    var axisInp     = document.querySelector('[data-sport-autre-axis]');
    var ceWrap      = document.querySelector('[data-sport-autre-ce-wrap]');
    var ceChk       = document.querySelector('[data-sport-autre-ce]');
    var ceNote      = document.querySelector('[data-sport-autre-ce-note]');
    var peremWrap   = document.querySelector('[data-sport-autre-perem-wrap]');
    var elecNote    = document.querySelector('[data-sport-autre-elec-note]');
    var nutriNote   = document.querySelector('[data-sport-autre-nutrition-note]');
    var waterNote   = document.querySelector('[data-sport-autre-watersport-note]');
    if (!root) { return; }

    function show(el, on) { if (el) { el.hidden = !on; } }
    function knownHas(v) { return (KNOWN || []).indexOf(v) > -1; }
    function autreActive() { return !!(coll && coll.value !== '' && !knownHas(coll.value)); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function rayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function cfg() { return (AUTRE.R || {})[autreSlug(rayonName())] || null; }

    function buildSpecChips() {
        if (!specChips) { return; }
        var c = cfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        specChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-sport-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            specChips.appendChild(b);
        });
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-sport-autre-size-chips]');
        var lab = document.querySelector('[data-sport-autre-size-label]');
        if (!box) { return; }
        var c = cfg();
        var axis = (c && c.axis) ? c.axis : (axisInp ? String(axisInp.value || '').trim() : '');
        var btns = (axis && SIZES[axis]) ? SIZES[axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-sport-autre-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function addSpec(label) {
        var tpl = document.getElementById('sport-autre-spec-template');
        if (!tpl || !tpl.content || !specsBox) { return; }
        specsBox.appendChild(tpl.content.cloneNode(true));
        var row = specsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAtout() {
        var inp = document.querySelector('[data-sport-autre-atout-input]');
        var box = document.querySelector('[data-sport-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }
    function tipsToggle() {
        var c = cfg();
        var ce = !!(c && c.ce), nutri = !!(c && c.nutrition);
        show(ceWrap, ce);
        if (ceWrap) { ceWrap.querySelectorAll('input').forEach(function (i) { i.disabled = !ce || !autreActive(); }); }
        show(peremWrap, nutri);
        if (peremWrap) { peremWrap.querySelectorAll('input').forEach(function (i) { i.disabled = !nutri || !autreActive(); }); }
        show(ceNote, ce && !(ceChk && ceChk.checked));
        show(elecNote, !!(c && c.elec));
        show(nutriNote, nutri);
        show(waterNote, !!(c && c.watersport));
    }
    function adaptAutre() {
        var c = cfg();
        if (hint) {
            var rn = rayonName();
            hint.textContent = c ? ((cfgEl.getAttribute('data-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-generic') || hint.textContent);
        }
        buildSpecChips(); buildSizeChips();
        if (c && axisInp && !axisInp.value.trim()) { axisInp.value = c.axis || ''; }
        if (ceChk && !ceChk.dataset.touched) { ceChk.checked = !!(c && c.ce); }
        tipsToggle();
    }
    function setEnabled() {
        var on = autreActive();
        root.hidden = !on;
        root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !on; });
        if (on) { tipsToggle(); } // réapplique l'état des wraps masqués (CE / péremption)
    }
    function onColl() { if (autreActive()) { adaptAutre(); } setEnabled(); }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (collOther) { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (ceChk)     { ceChk.addEventListener('change', function () { this.dataset.touched = '1'; tipsToggle(); }); }
    if (axisInp)   { axisInp.addEventListener('input', buildSizeChips); }
    var atoutInp = document.querySelector('[data-sport-autre-atout-input]');
    if (atoutInp) { atoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-sport-autre-fill]');
        if (fill) {
            ev.preventDefault();
            var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
            var tpl = document.getElementById('variant-template');
            if (rowsBox && tpl && tpl.content) {
                var have = {};
                rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
                var list = []; try { list = JSON.parse(fill.getAttribute('data-sport-autre-fill') || '[]'); } catch (e) {}
                list.forEach(function (sz) {
                    var key = String(sz).trim().toLowerCase();
                    if (key === '' || have[key]) { return; }
                    rowsBox.appendChild(tpl.content.cloneNode(true));
                    var r = rowsBox.lastElementChild; var inp = r && r.querySelector('input[name="var_size[]"]');
                    if (inp) { inp.value = sz; } have[key] = true;
                });
                var det = rowsBox.closest('details'); if (det) { det.open = true; }
            }
            return;
        }
        if (ev.target.closest('[data-sport-autre-spec-add]')) { ev.preventDefault(); addSpec(''); return; }
        if (ev.target.closest('[data-sport-autre-atout-add]')) { ev.preventDefault(); pushAtout(); return; }
        var sp = ev.target.closest('[data-sport-autre-spec]');
        if (sp) { ev.preventDefault(); addSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-sport-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-sport-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-sport-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });
    if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Auto & pièces : rayons adaptatifs au type (Accessoires…).
   Le TYPE pilote les caractéristiques, l'axe (Couleur / Taille / Modèle / Parfum),
   le mode électrique (note CE). Bloc COMPATIBILITÉ VÉHICULE : universel → masque la
   liste de véhicules. Remplissage rapide de tailles. CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-auto]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-auto-root]');
    var typeSel   = document.querySelector('[data-auto-type]');
    var attrsBox  = document.querySelector('[data-auto-attrs]');
    var atoutsBox = document.querySelector('[data-auto-atouts]');
    var elecNote  = document.querySelector('[data-auto-elec-note]');
    var oilNote   = document.querySelector('[data-auto-oil-note]');
    var hint      = document.querySelector('[data-auto-hint]');
    var axisInp   = document.querySelector('[data-auto-axis]');
    var universelChk = document.querySelector('[data-auto-universel]');
    var compatBox    = document.querySelector('[data-auto-compat-box]');
    var compatWrap   = document.querySelector('[data-auto-compat-wrap]');
    var pneusWrap    = document.querySelector('[data-auto-pneus-wrap]');
    var dimVal       = document.querySelector('[data-auto-dim-val]');
    var occasionNote = document.querySelector('[data-auto-occasion-note]');
    var condSel      = document.querySelector('[data-auto-condition]');
    // « Nouveau rayon » Auto : specs libres adaptées au slug du rayon saisi.
    var AUTRE = parse('data-autre');
    var collOther     = document.querySelector('[data-collection-other]');
    var autreRoot     = document.querySelector('[data-auto-autre-root]');
    var autreHint     = document.querySelector('[data-auto-autre-hint]');
    var autreSpecsBox = document.querySelector('[data-auto-autre-specs]');
    var autreSpecChips= document.querySelector('[data-auto-autre-spec-chips]');
    var autreUniChk   = document.querySelector('[data-auto-autre-universel]');
    var autreCompatBox= document.querySelector('[data-auto-autre-compat-box]');
    var autreElecTog  = document.querySelector('[data-auto-autre-elec-toggle]');
    var autreElecBox  = document.querySelector('[data-auto-autre-elec-box]');
    var autreElecWarn = document.querySelector('[data-auto-autre-elec-warn]');
    var autreAxisInp  = document.querySelector('[data-auto-autre-axis]');
    if (!root && !autreRoot) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function autreActive() { return !!(coll && coll.value !== '' && !RAYONS[coll.value]); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayonName())] || null; }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-auto-size-chips]');
        var lab = document.querySelector('[data-auto-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-auto-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function onType() {
        var m = meta();
        if (elecNote) { elecNote.hidden = !(m && m.elec); }
        if (oilNote)  { oilNote.hidden = !(m && m.oil); }
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips(); updateDim();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
    }
    function autoFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    // Compatibilité véhicule : universel → masque + désactive les champs véhicules / réf. OEM.
    function universelToggle() {
        var uni = !!(universelChk && universelChk.checked);
        if (compatBox) {
            compatBox.hidden = uni;
            compatBox.querySelectorAll('input, textarea').forEach(function (f) { f.disabled = uni || !active(); });
        }
    }
    // Mode pneu : la dimension composée (largeur/série/diamètre [+ charge/vitesse]) tient lieu de compatibilité.
    function tyreDim() {
        var a = {};
        if (attrsBox) { attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { a[k] = s.value; } }); }
        var l = a.largeur || '', se = a.serie || '', d = a.diametre || '', c = a.charge || '', v = a.vitesse || '';
        if (l && se && d) { var dim = l + '/' + se + ' R' + d; if (c) { dim += ' ' + c; } if (v) { dim += String(v).split(' ')[0]; } return dim; }
        if (d && !l) { return 'R' + d; }
        return '';
    }
    function updateDim() {
        if (!dimVal || !cfg().dimension) { return; }
        var dim = tyreDim();
        dimVal.textContent = dim || (dimVal.getAttribute('data-empty') || '');
    }
    function occasionToggle() {
        if (!occasionNote) { return; }
        var occ = condSel && condSel.value === 'Occasion';
        occasionNote.hidden = !(cfg().dimension && occ);
    }
    // ----- « Nouveau rayon » Auto : adaptation au slug, specs libres, compat + élec -----
    function autreUniverselToggle() {
        var uni = !!(autreUniChk && autreUniChk.checked);
        if (autreCompatBox) {
            autreCompatBox.hidden = uni;
            autreCompatBox.querySelectorAll('input, textarea').forEach(function (f) { f.disabled = uni || !autreActive(); });
        }
    }
    function autreElecToggle() {
        var on = !!(autreElecTog && autreElecTog.checked);
        if (autreElecBox)  { autreElecBox.hidden = !on; autreElecBox.querySelectorAll('select, input').forEach(function (f) { f.disabled = !on || !autreActive(); }); }
        if (autreElecWarn) { autreElecWarn.hidden = !on; }
    }
    function autreBuildSpecChips() {
        if (!autreSpecChips) { return; }
        var c = autreCfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        autreSpecChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-auto-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            autreSpecChips.appendChild(b);
        });
    }
    function addAutreSpec(label) {
        var tpl = document.getElementById('auto-autre-spec-template');
        if (!tpl || !tpl.content || !autreSpecsBox) { return; }
        autreSpecsBox.appendChild(tpl.content.cloneNode(true));
        var row = autreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAutreAtout() {
        var inp = document.querySelector('[data-auto-autre-atout-input]');
        var box = document.querySelector('[data-auto-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }
    function adaptAutre() {
        var c = autreCfg();
        if (autreHint) {
            var rn = autreRayonName();
            autreHint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-autre-generic') || autreHint.textContent);
        }
        autreBuildSpecChips();
        if (c && autreAxisInp && !autreAxisInp.value.trim()) { autreAxisInp.value = c.axis || ''; }
        // Défauts depuis le rayon connu, sauf si le vendeur a déjà réglé l'interrupteur.
        if (c && autreUniChk && !autreUniChk.dataset.touched) { autreUniChk.checked = !!c.uni; autreUniverselToggle(); }
        if (c && autreElecTog && !autreElecTog.dataset.touched) { autreElecTog.checked = !!c.elec; autreElecToggle(); }
    }
    function setEnabled() {
        var known = active(), isAutre = autreActive();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
            if (known) {
                var isPneus = !!cfg().dimension;
                // Mode pneu ↔ mode compatibilité : un seul bloc affiché/actif à la fois.
                if (compatWrap) { compatWrap.hidden = isPneus; if (isPneus) { compatWrap.querySelectorAll('input, textarea').forEach(function (f) { f.disabled = true; }); } }
                if (pneusWrap)  { pneusWrap.hidden = !isPneus; if (!isPneus) { pneusWrap.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = true; }); } }
                if (!isPneus) { universelToggle(); }
                updateDim(); occasionToggle();
            }
        }
        if (autreRoot) {
            autreRoot.hidden = !isAutre;
            autreRoot.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !isAutre; });
            if (isAutre) { autreUniverselToggle(); autreElecToggle(); }
        }
    }
    function onColl() {
        if (active()) { rebuildRayon(); }
        else if (autreActive()) { adaptAutre(); }
        setEnabled();
    }

    if (coll)         { coll.addEventListener('change', onColl); }
    if (typeSel)      { typeSel.addEventListener('change', function () {
        onType();
        // Compatibilité par défaut selon le type (pièce spécifique → non universel),
        // sauf si le vendeur a déjà réglé l'interrupteur lui-même.
        var m = meta();
        if (m && universelChk && !universelChk.dataset.touched) { universelChk.checked = !m.specific; universelToggle(); }
        setEnabled();
    }); }
    if (universelChk) { universelChk.addEventListener('change', function () { this.dataset.touched = '1'; universelToggle(); }); }
    if (attrsBox)     { attrsBox.addEventListener('change', updateDim); }
    if (condSel)      { condSel.addEventListener('change', occasionToggle); }
    if (collOther)    { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (autreUniChk)  { autreUniChk.addEventListener('change', function () { this.dataset.touched = '1'; autreUniverselToggle(); }); }
    if (autreElecTog) { autreElecTog.addEventListener('change', function () { this.dataset.touched = '1'; autreElecToggle(); }); }
    var autreAtoutInp = document.querySelector('[data-auto-autre-atout-input]');
    if (autreAtoutInp) { autreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAutreAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-auto-fill]');
        if (fill) { ev.preventDefault(); try { autoFill(JSON.parse(fill.getAttribute('data-auto-fill') || '[]')); } catch (e) {} return; }
        if (ev.target.closest('[data-auto-autre-spec-add]')) { ev.preventDefault(); addAutreSpec(''); return; }
        if (ev.target.closest('[data-auto-autre-atout-add]')) { ev.preventDefault(); pushAutreAtout(); return; }
        var sp = ev.target.closest('[data-auto-autre-spec]');
        if (sp) { ev.preventDefault(); addAutreSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-auto-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-auto-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-auto-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });
    if (active()) { onType(); }
    else if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Artisanat & Art : rayons adaptatifs au type (Bijoux…).
   Le TYPE pilote les caractéristiques + l'axe + le remplissage rapide. Signatures :
   FAIT MAIN, PIÈCE UNIQUE (→ stock 1, éditeur de déclinaisons verrouillé), HISTOIRE,
   rappel CITES. CSP-safe (zéro inline). ---- */
(function () {
    var cfgEl = document.querySelector('[data-arti]');
    var form  = document.getElementById('product-form');
    if (!cfgEl || !form) { return; }
    function parse(a) { try { return JSON.parse(cfgEl.getAttribute(a) || 'null') || {}; } catch (e) { return {}; } }
    var RAYONS = parse('data-rayons');
    var SIZES  = parse('data-size-systems');
    var coll      = document.querySelector('[data-collection-select]');
    var root      = document.querySelector('[data-arti-root]');
    var typeSel   = document.querySelector('[data-arti-type]');
    var attrsBox  = document.querySelector('[data-arti-attrs]');
    var atoutsBox = document.querySelector('[data-arti-atouts]');
    var hint      = document.querySelector('[data-arti-hint]');
    var axisInp   = document.querySelector('[data-arti-axis]');
    var uniqueChk = document.querySelector('[data-arti-unique]');
    var uniqueNote= document.querySelector('[data-arti-unique-note]');
    var elecTog   = document.querySelector('[data-arti-elec-toggle]');
    var elecBox   = document.querySelector('[data-arti-elec-box]');
    var elecWarn  = document.querySelector('[data-arti-elec-warn]');
    var elecWrap  = document.querySelector('[data-arti-elec-wrap]');
    var foodWrap  = document.querySelector('[data-arti-food-wrap]');
    var foodTog   = document.querySelector('[data-arti-food-toggle]');
    var FOOD_USAGES = parse('data-food-usages'); FOOD_USAGES = (FOOD_USAGES && FOOD_USAGES.length) ? FOOD_USAGES : [];
    var modePillWrap = document.querySelector('[data-arti-mode-pill-wrap]');
    var modePill     = document.querySelector('[data-arti-mode-pill]');
    var metreNote    = document.querySelector('[data-arti-metre-note]');
    // « Nouveau rayon » Artisanat : specs libres adaptées au slug du rayon saisi.
    var AUTRE = parse('data-autre');
    var collOther      = document.querySelector('[data-collection-other]');
    var autreRoot      = document.querySelector('[data-arti-autre-root]');
    var autreHint      = document.querySelector('[data-arti-autre-hint]');
    var autreSpecsBox  = document.querySelector('[data-arti-autre-specs]');
    var autreSpecChips = document.querySelector('[data-arti-autre-spec-chips]');
    var autreUniqueChk = document.querySelector('[data-arti-autre-unique]');
    var autreUniqueNote= document.querySelector('[data-arti-autre-unique-note]');
    var autreMetreChk  = document.querySelector('[data-arti-autre-metre]');
    var autreModeWrap  = document.querySelector('[data-arti-autre-mode-pill-wrap]');
    var autreAxisInp   = document.querySelector('[data-arti-autre-axis]');
    if (!root && !autreRoot) { return; }

    function active() { return !!(coll && RAYONS[coll.value]); }
    function autreActive() { return !!(coll && coll.value !== '' && !RAYONS[coll.value]); }
    function autreSlug(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'autre'; }
    function autreRayonName() { return (coll && coll.value === '__other__') ? (collOther ? String(collOther.value || '').trim() : '') : (coll ? String(coll.value || '').trim() : ''); }
    function autreCfg() { return (AUTRE.R || {})[autreSlug(autreRayonName())] || null; }
    function cfg() { return (coll && RAYONS[coll.value]) ? RAYONS[coll.value] : {}; }
    function meta() { var t = cfg().types || {}; return (typeSel && t[typeSel.value]) ? t[typeSel.value] : null; }

    function buildAttrs() {
        if (!attrsBox) { return; }
        var fields = cfg().fields || {};
        var prev = {};
        attrsBox.querySelectorAll('select').forEach(function (s) { var k = (s.name.match(/attr\[(.+)\]/) || [])[1]; if (k) { prev[k] = s.value; } });
        attrsBox.innerHTML = '';
        var m = meta(); if (!m) { return; }
        (m.fields || []).forEach(function (key) {
            var def = fields[key]; if (!def) { return; }
            var wrap = document.createElement('div');
            var lab = document.createElement('label'); lab.textContent = def.label; wrap.appendChild(lab);
            var sel = document.createElement('select'); sel.name = 'attr[' + key + ']';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '—'; sel.appendChild(o0);
            (def.opts || []).forEach(function (o) {
                var op = document.createElement('option'); op.value = o; op.textContent = o;
                if (prev[key] === o) { op.selected = true; }
                sel.appendChild(op);
            });
            wrap.appendChild(sel); attrsBox.appendChild(wrap);
        });
    }
    function rebuildRayon() {
        var c = cfg();
        if (typeSel) {
            var cur = typeSel.value, types = c.types || {}, groups = c.groups || {};
            typeSel.innerHTML = '';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = cfgEl.getAttribute('data-any') || '—'; typeSel.appendChild(o0);
            function addOpt(parent, tn) { var op = document.createElement('option'); op.value = tn; op.textContent = tn; if (tn === cur) { op.selected = true; } parent.appendChild(op); }
            if (Object.keys(groups).length) {
                Object.keys(groups).forEach(function (gk) {
                    var og = document.createElement('optgroup'); og.label = groups[gk];
                    Object.keys(types).forEach(function (tn) { if ((types[tn].group || '') === gk) { addOpt(og, tn); } });
                    typeSel.appendChild(og);
                });
            } else {
                Object.keys(types).forEach(function (tn) { addOpt(typeSel, tn); });
            }
            if (typeSel.value !== cur) { typeSel.value = ''; }
        }
        if (atoutsBox) {
            var prevAt = {};
            atoutsBox.querySelectorAll('input:checked').forEach(function (i) { prevAt[i.value] = true; });
            atoutsBox.innerHTML = '';
            (c.atouts || []).forEach(function (v) {
                var lab = document.createElement('label'); lab.className = 'chip-check';
                var inp = document.createElement('input'); inp.type = 'checkbox'; inp.name = 'atouts[]'; inp.value = v;
                if (prevAt[v]) { inp.checked = true; }
                var sp = document.createElement('span'); sp.textContent = v;
                lab.appendChild(inp); lab.appendChild(sp); atoutsBox.appendChild(lab);
            });
        }
        onType();
    }
    function buildSizeChips() {
        var box = document.querySelector('[data-arti-size-chips]');
        var lab = document.querySelector('[data-arti-size-label]');
        if (!box) { return; }
        var m = meta();
        var btns = (m && m.axis && SIZES[m.axis]) ? SIZES[m.axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-arti-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function onType() {
        var m = meta();
        if (m && axisInp && !axisInp.value.trim()) { axisInp.value = m.axis || ''; }
        buildAttrs(); buildSizeChips();
        if (hint) { hint.textContent = m ? (cfgEl.getAttribute('data-hint-specs') || hint.textContent) : (cfgEl.getAttribute('data-hint-pick') || hint.textContent); }
        // Mode de vente (textile) : pastille + note « au mètre ».
        if (modePillWrap) { modePillWrap.hidden = !m; }
        if (modePill) {
            var mode = m ? (m.mode || '') : '';
            modePill.textContent = mode === 'metre' ? (cfgEl.getAttribute('data-mode-metre') || '')
                : (mode === 'confection' ? (cfgEl.getAttribute('data-mode-confection') || '') : '');
            modePill.hidden = !mode;
        }
        if (metreNote) { metreNote.hidden = !(m && m.mode === 'metre'); }
    }
    function artiFill(list) {
        var rowsBox = document.getElementById('variant-rows') || document.querySelector('[data-variant-rows]');
        var tpl = document.getElementById('variant-template');
        if (!rowsBox || !tpl || !tpl.content) { return; }
        var have = {};
        rowsBox.querySelectorAll('input[name="var_size[]"]').forEach(function (i) { have[String(i.value || '').trim().toLowerCase()] = true; });
        (list || []).forEach(function (sz) {
            var key = String(sz).trim().toLowerCase();
            if (key === '' || have[key]) { return; }
            rowsBox.appendChild(tpl.content.cloneNode(true));
            var row = rowsBox.lastElementChild;
            var inp = row && row.querySelector('input[name="var_size[]"]');
            if (inp) { inp.value = sz; }
            have[key] = true;
        });
        var det = rowsBox.closest('details'); if (det) { det.open = true; }
    }
    // Pièce unique : stock = 1 (verrouillé, readonly pour rester soumis) + éditeur de
    // déclinaisons désactivé. On marque ce qu'on verrouille pour pouvoir tout libérer.
    function setUniqueLock(lock) {
        var stockF = document.getElementById('p-stock');
        var decl = document.getElementById('variant-rows');
        var declBox = decl ? decl.closest('details') : null;
        if (lock) {
            if (stockF) { stockF.value = '1'; stockF.readOnly = true; stockF.setAttribute('data-arti-locked', '1'); }
            if (decl) { decl.querySelectorAll('input').forEach(function (f) { f.disabled = true; f.setAttribute('data-arti-locked', '1'); }); }
            if (declBox) { declBox.style.opacity = '.5'; }
        } else {
            document.querySelectorAll('[data-arti-locked]').forEach(function (f) { f.readOnly = false; f.disabled = false; f.removeAttribute('data-arti-locked'); });
            if (declBox) { declBox.style.opacity = ''; }
        }
    }
    // Verrou « pièce unique » unifié : pilote l'interrupteur du root actif (connu ou autre).
    function syncUnique() {
        var knownUni = !!(active() && uniqueChk && uniqueChk.checked);
        var autreUni = !!(autreActive() && autreUniqueChk && autreUniqueChk.checked);
        if (uniqueNote) { uniqueNote.hidden = !knownUni; }
        if (autreUniqueNote) { autreUniqueNote.hidden = !autreUni; }
        setUniqueLock(knownUni || autreUni);
    }
    // ----- « Nouveau rayon » Artisanat : adaptation au slug, specs libres, signatures -----
    function autreMetreToggle() {
        if (autreModeWrap) { autreModeWrap.hidden = !(autreMetreChk && autreMetreChk.checked); }
    }
    function autreBuildSpecChips() {
        if (!autreSpecChips) { return; }
        var c = autreCfg();
        var list = c ? (c.specs || []) : (AUTRE.generic_specs || []);
        autreSpecChips.innerHTML = '';
        list.forEach(function (s) {
            var b = document.createElement('button'); b.type = 'button'; b.className = 'axis-chip';
            b.setAttribute('data-arti-autre-spec', ''); b.setAttribute('data-val', s); b.textContent = s;
            autreSpecChips.appendChild(b);
        });
    }
    function buildAutreSizeChips() {
        var box = document.querySelector('[data-arti-autre-size-chips]');
        var lab = document.querySelector('[data-arti-autre-size-label]');
        if (!box) { return; }
        var c = autreCfg();
        var axis = (c && c.axis) ? c.axis : (autreAxisInp ? String(autreAxisInp.value || '').trim() : '');
        var btns = (axis && SIZES[axis]) ? SIZES[axis] : [];
        box.innerHTML = '';
        box.hidden = btns.length === 0;
        if (lab) { lab.hidden = btns.length === 0; }
        btns.forEach(function (b) {
            var el = document.createElement('button'); el.type = 'button'; el.className = 'axis-chip';
            el.setAttribute('data-arti-autre-fill', JSON.stringify(b.list || []));
            el.textContent = '+ ' + (b.label || '');
            box.appendChild(el);
        });
    }
    function addAutreSpec(label) {
        var tpl = document.getElementById('arti-autre-spec-template');
        if (!tpl || !tpl.content || !autreSpecsBox) { return; }
        autreSpecsBox.appendChild(tpl.content.cloneNode(true));
        var row = autreSpecsBox.lastElementChild;
        if (row && label) { var l = row.querySelector('input[name="spec_label[]"]'); if (l) { l.value = label; } var v = row.querySelector('input[name="spec_value[]"]'); if (v) { v.focus(); } }
    }
    function pushAutreAtout() {
        var inp = document.querySelector('[data-arti-autre-atout-input]');
        var box = document.querySelector('[data-arti-autre-atouts]');
        if (!inp || !box) { return; }
        var v = String(inp.value || '').trim(); if (v === '') { return; }
        var exists = false;
        box.querySelectorAll('input[name="atouts[]"]').forEach(function (c) { if (c.value === v) { c.checked = true; exists = true; } });
        if (!exists) {
            var lab = document.createElement('label'); lab.className = 'chip-check';
            var c = document.createElement('input'); c.type = 'checkbox'; c.name = 'atouts[]'; c.value = v; c.checked = true;
            var sp = document.createElement('span'); sp.textContent = v;
            lab.appendChild(c); lab.appendChild(sp); box.appendChild(lab);
        }
        inp.value = '';
    }
    function adaptAutre() {
        var c = autreCfg();
        if (autreHint) {
            var rn = autreRayonName();
            autreHint.textContent = c ? ((cfgEl.getAttribute('data-autre-adapted') || '%R%').replace('%R%', rn))
                : (cfgEl.getAttribute('data-autre-generic') || autreHint.textContent);
        }
        autreBuildSpecChips(); buildAutreSizeChips();
        if (c && autreAxisInp && !autreAxisInp.value.trim()) { autreAxisInp.value = c.axis || ''; }
        // Défauts depuis le rayon connu, sauf si le vendeur a déjà réglé l'interrupteur.
        if (c && autreUniqueChk && !autreUniqueChk.dataset.touched) { autreUniqueChk.checked = !!c.unique; }
        if (c && autreMetreChk && !autreMetreChk.dataset.touched) { autreMetreChk.checked = (c.mode === 'metre'); }
        autreMetreToggle(); syncUnique();
    }
    // Le rayon courant comporte-t-il un type électrique (ex. luminaire) ?
    function rayonHasElec() { var t = cfg().types || {}; return Object.keys(t).some(function (k) { return !!t[k].elec; }); }
    // Contact alimentaire (poterie) : type alimentaire OU champ « usage » alimentaire.
    function foodLikely() {
        var m = meta(); if (!m) { return false; }
        if (m.food) { return true; }
        var us = attrsBox && attrsBox.querySelector('select[name="attr[usage]"]');
        return !!(us && FOOD_USAGES.indexOf(us.value) > -1);
    }
    function foodToggle() {
        if (!foodWrap) { return; }
        var likely = active() && foodLikely();
        foodWrap.hidden = !likely;
        if (foodTog) { foodTog.disabled = !likely; if (!likely) { foodTog.checked = false; } }
    }
    // Objet électrique (luminaire) : affiche garantie + rappel CE.
    function elecToggle() {
        var on = !!(elecTog && elecTog.checked);
        if (elecBox)  { elecBox.hidden = !on; elecBox.querySelectorAll('select, input').forEach(function (f) { f.disabled = !on || !active(); }); }
        if (elecWarn) { elecWarn.hidden = !on; }
    }
    function setEnabled() {
        var known = active(), isAutre = autreActive();
        if (root) {
            root.hidden = !known;
            root.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !known; });
            if (known) {
                var hasElec = rayonHasElec();
                // Le bloc « électrique » n'apparaît que si le rayon a des types électriques.
                if (elecWrap) { elecWrap.hidden = !hasElec; if (!hasElec) { elecWrap.querySelectorAll('input, select').forEach(function (f) { f.disabled = true; }); } }
                if (hasElec) { elecToggle(); }
                foodToggle();
            }
        }
        if (autreRoot) {
            autreRoot.hidden = !isAutre;
            autreRoot.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !isAutre; });
            if (isAutre) { autreMetreToggle(); }
        }
        syncUnique();
    }
    function onColl() {
        if (active()) { rebuildRayon(); }
        else if (autreActive()) { adaptAutre(); }
        setEnabled();
    }

    if (coll)      { coll.addEventListener('change', onColl); }
    if (typeSel)   { typeSel.addEventListener('change', function () {
        onType();
        // Mode électrique par défaut selon le type (luminaire) au changement manuel.
        var m = meta();
        if (m && elecTog) { elecTog.checked = !!m.elec; elecToggle(); }
        setEnabled();
    }); }
    if (uniqueChk) { uniqueChk.addEventListener('change', function () { this.dataset.touched = '1'; syncUnique(); }); }
    if (elecTog)   { elecTog.addEventListener('change', elecToggle); }
    if (attrsBox)  { attrsBox.addEventListener('change', foodToggle); }
    if (collOther) { collOther.addEventListener('input', function () { if (autreActive()) { adaptAutre(); } }); }
    if (autreUniqueChk) { autreUniqueChk.addEventListener('change', function () { this.dataset.touched = '1'; syncUnique(); }); }
    if (autreMetreChk)  { autreMetreChk.addEventListener('change', function () { this.dataset.touched = '1'; autreMetreToggle(); }); }
    var autreAtoutInp = document.querySelector('[data-arti-autre-atout-input]');
    if (autreAtoutInp) { autreAtoutInp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); pushAutreAtout(); } }); }
    document.addEventListener('click', function (ev) {
        if (!ev.target || !ev.target.closest) { return; }
        var fill = ev.target.closest('[data-arti-fill]');
        if (fill) { ev.preventDefault(); try { artiFill(JSON.parse(fill.getAttribute('data-arti-fill') || '[]')); } catch (e) {} return; }
        var afill = ev.target.closest('[data-arti-autre-fill]');
        if (afill) { ev.preventDefault(); try { artiFill(JSON.parse(afill.getAttribute('data-arti-autre-fill') || '[]')); } catch (e) {} return; }
        if (ev.target.closest('[data-arti-autre-spec-add]')) { ev.preventDefault(); addAutreSpec(''); return; }
        if (ev.target.closest('[data-arti-autre-atout-add]')) { ev.preventDefault(); pushAutreAtout(); return; }
        var sp = ev.target.closest('[data-arti-autre-spec]');
        if (sp) { ev.preventDefault(); addAutreSpec(sp.getAttribute('data-val')); return; }
        var ray = ev.target.closest('[data-arti-autre-rayon]');
        if (ray) {
            ev.preventDefault();
            var rn = ray.getAttribute('data-arti-autre-rayon');
            if (coll) { coll.value = '__other__'; coll.dispatchEvent(new Event('change')); }
            if (collOther) { collOther.hidden = false; collOther.value = rn; collOther.dispatchEvent(new Event('input')); }
            return;
        }
        var sdel = ev.target.closest('[data-arti-autre-spec-del]');
        if (sdel) { var srow = sdel.closest('.spec-row'); if (srow) { srow.remove(); } }
    });
    if (active()) { onType(); }
    else if (autreActive()) { adaptAutre(); }
    setEnabled();
})();

/* ---- Avis : téléversement de photos (façon Shein) — envoi direct → Cloudinary ---- */
(function () {
    var box = document.querySelector('[data-review-uploader]');
    if (!box) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var hidden = box.querySelector('[data-review-photos-json]');
    var previews = box.querySelector('[data-review-previews]');
    var fileInput = box.querySelector('[data-review-file]');
    var statusEl = box.querySelector('[data-review-status]');
    var errEl = box.querySelector('[data-review-error]');
    var addBtn = box.querySelector('[data-review-add]');
    var form = box.closest('form');
    var submit = form ? form.querySelector('[data-review-submit]') : null;
    var MAX = parseInt(box.getAttribute('data-max'), 10) || 6;
    var photos = [];   // {publicId, url}
    var pending = 0;

    function sync() {
        if (hidden) { hidden.value = JSON.stringify(photos.map(function (p) { return p.publicId; })); }
        if (submit) { submit.disabled = pending > 0; }
        if (statusEl) { statusEl.hidden = pending === 0; if (pending > 0) { statusEl.textContent = statusEl.getAttribute('data-msg') || 'Envoi en cours…'; } }
        if (addBtn) { addBtn.style.display = photos.length >= MAX ? 'none' : ''; }
    }
    function setErr(m) { if (errEl) { errEl.textContent = m || ''; errEl.hidden = !m; } }
    function sign() {
        var b = new FormData();
        b.append('resource_type', 'image');
        return fetch('/api/media/sign', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: b })
            .then(function (r) { if (!r.ok) { throw new Error('sign'); } return r.json(); });
    }
    function shrink(file) {
        if (file.size < 600 * 1024 || typeof window.createImageBitmap !== 'function') { return Promise.resolve(file); }
        return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
            var s = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
            if (s === 1) { return file; }
            var c = document.createElement('canvas');
            c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
            c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
            return new Promise(function (res) {
                c.toBlob(function (bl) { res(bl && bl.size < file.size ? new File([bl], 'p.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85);
            });
        }).catch(function () { return file; });
    }
    function upload(file, params) {
        var fd = new FormData();
        fd.append('file', file); fd.append('api_key', params.api_key); fd.append('timestamp', params.timestamp);
        fd.append('folder', params.folder); fd.append('signature', params.signature);
        return fetch('https://api.cloudinary.com/v1_1/' + encodeURIComponent(params.cloud_name) + '/' + params.resource_type + '/upload', { method: 'POST', body: fd })
            .then(function (r) { if (!r.ok) { throw new Error('up'); } return r.json(); });
    }
    function addPreview(publicId, url) {
        var w = document.createElement('div'); w.className = 'review-prev';
        var img = document.createElement('img'); img.src = url; img.alt = '';
        var del = document.createElement('button'); del.type = 'button'; del.className = 'review-prev-del'; del.setAttribute('aria-label', '✕'); del.textContent = '✕';
        del.addEventListener('click', function () { photos = photos.filter(function (p) { return p.publicId !== publicId; }); w.remove(); sync(); });
        w.appendChild(img); w.appendChild(del); previews.appendChild(w);
    }
    function handle(files) {
        setErr('');
        Array.prototype.filter.call(files || [], function (f) { return f && f.type.indexOf('image/') === 0; }).forEach(function (file) {
            if (photos.length + pending >= MAX) { return; }
            pending++; sync();
            shrink(file).then(function (f) { return sign().then(function (p) { return upload(f, p); }); })
                .then(function (res) {
                    if (res && res.public_id) { photos.push({ publicId: res.public_id, url: res.secure_url || '' }); addPreview(res.public_id, res.secure_url || ''); }
                    pending--; sync();
                })
                .catch(function () { pending--; setErr(box.getAttribute('data-err') || 'Échec de l’envoi d’une photo.'); sync(); });
        });
        if (fileInput) { fileInput.value = ''; }
    }
    if (fileInput) { fileInput.addEventListener('change', function () { handle(fileInput.files); }); }
    sync();
})();

/* ---- Avis : agrandissement des photos déposées par les clients ---- */
(function () {
    if (!document.querySelector('[data-review-photos]')) { return; }
    var ov, imgEl, list = [], cur = 0;
    function show(i) { cur = ((i % list.length) + list.length) % list.length; if (imgEl) { imgEl.src = list[cur]; } }
    function close() { if (ov) { ov.classList.remove('is-open'); document.body.style.overflow = ''; } }
    function ensure() {
        if (ov) { return; }
        ov = document.createElement('div'); ov.className = 'lightbox';
        ov.innerHTML = '<button class="lightbox-close" type="button" aria-label="Fermer">×</button>'
            + '<button class="lightbox-nav lightbox-prev" type="button" aria-label="Précédent">‹</button>'
            + '<img class="lightbox-img" alt="">'
            + '<button class="lightbox-nav lightbox-next" type="button" aria-label="Suivant">›</button>';
        document.body.appendChild(ov); imgEl = ov.querySelector('.lightbox-img');
        ov.addEventListener('click', function (e) {
            var t = e.target;
            if (t === ov || t.classList.contains('lightbox-close')) { close(); }
            else if (t.classList.contains('lightbox-next')) { show(cur + 1); }
            else if (t.classList.contains('lightbox-prev')) { show(cur - 1); }
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-review-photo]') : null;
        if (!btn) { return; }
        var grp = btn.closest('[data-review-photos]'); if (!grp) { return; }
        try { list = JSON.parse(grp.getAttribute('data-review-photos') || '[]'); } catch (x) { list = []; }
        if (!list.length) { return; }
        ensure(); show(parseInt(btn.getAttribute('data-index'), 10) || 0);
        ov.classList.add('is-open'); document.body.style.overflow = 'hidden';
    });
    document.addEventListener('keydown', function (e) {
        if (!ov || !ov.classList.contains('is-open')) { return; }
        if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowRight') { show(cur + 1); } else if (e.key === 'ArrowLeft') { show(cur - 1); }
    });
})();

/* ---- Vendeur : photos par couleur (déclinaisons, combos inclus) ---- */
(function () {
    var box = document.querySelector('[data-color-photos]');
    if (!box) { return; }
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var hidden = box.querySelector('[data-color-images-json]');
    var rowsBox = box.querySelector('[data-color-photos-rows]');
    var tpl = document.getElementById('color-photo-row-tpl');
    var statusEl = box.querySelector('[data-color-status]');
    if (!hidden || !rowsBox || !tpl) { return; }

    var COLOR_HEX = { noir:'#1a1a1a', black:'#1a1a1a', blanc:'#f5f5f0', white:'#f5f5f0', gris:'#9ca3af', gray:'#9ca3af', grey:'#9ca3af',
        rouge:'#dc2626', red:'#dc2626', bordeaux:'#7f1d1d', bleu:'#2563eb', blue:'#2563eb', marine:'#1e3a5f', turquoise:'#06b6d4',
        vert:'#16a34a', green:'#16a34a', kaki:'#78866b', jaune:'#facc15', yellow:'#facc15', or:'#d4af37', gold:'#d4af37',
        orange:'#ea580c', rose:'#ec4899', pink:'#ec4899', fuchsia:'#d946ef', violet:'#7c3aed', purple:'#7c3aed', mauve:'#b57edc',
        marron:'#92400e', brown:'#92400e', camel:'#c19a6b', taupe:'#8b8589', beige:'#e7d8b8', argent:'#c0c0c0', bronze:'#cd7f32' };
    function hexOf(name) { var f = (name || '').split(/[\/&·,]| et /i)[0].trim().toLowerCase(); return COLOR_HEX[f] || COLOR_HEX[(f.split(' ')[0] || '')] || ''; }

    var state = {}; // { colorLower: { name, imgs:[{id,url}] } }
    try { var pre = JSON.parse(box.getAttribute('data-prefill') || '{}'); Object.keys(pre).forEach(function (c) { state[c.toLowerCase()] = { name: c, imgs: pre[c] || [] }; }); } catch (e) {}
    var pending = 0;
    var submitBtn = document.getElementById('product-submit');

    function setStatus() { if (statusEl) { statusEl.hidden = pending === 0; if (pending > 0) { statusEl.textContent = statusEl.getAttribute('data-msg') || 'Envoi…'; } } if (submitBtn) { submitBtn.disabled = pending > 0; } }
    function syncHidden() { var map = {}; Object.keys(state).forEach(function (k) { if (state[k].imgs && state[k].imgs.length) { map[state[k].name] = state[k].imgs.map(function (x) { return x.id; }); } }); hidden.value = JSON.stringify(map); }
    function sign() { var b = new FormData(); b.append('resource_type', 'image'); return fetch('/api/media/sign', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: b }).then(function (r) { if (!r.ok) { throw new Error('sign'); } return r.json(); }); }
    function shrink(file) { if (file.size < 600 * 1024 || typeof window.createImageBitmap !== 'function') { return Promise.resolve(file); } return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) { var s = Math.min(1, 1600 / Math.max(bmp.width, bmp.height)); if (s === 1) { return file; } var c = document.createElement('canvas'); c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s); c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height); return new Promise(function (res) { c.toBlob(function (bl) { res(bl && bl.size < file.size ? new File([bl], 'p.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85); }); }).catch(function () { return file; }); }
    function upload(file, p) { var fd = new FormData(); fd.append('file', file); fd.append('api_key', p.api_key); fd.append('timestamp', p.timestamp); fd.append('folder', p.folder); fd.append('signature', p.signature); return fetch('https://api.cloudinary.com/v1_1/' + encodeURIComponent(p.cloud_name) + '/' + p.resource_type + '/upload', { method: 'POST', body: fd }).then(function (r) { if (!r.ok) { throw new Error('up'); } return r.json(); }); }

    function renderPreviews(row, key) {
        var prev = row.querySelector('[data-color-previews]'); prev.innerHTML = '';
        (state[key].imgs || []).forEach(function (img) {
            var w = document.createElement('div'); w.className = 'review-prev';
            var im = document.createElement('img'); im.src = img.url; im.alt = '';
            var del = document.createElement('button'); del.type = 'button'; del.className = 'review-prev-del'; del.textContent = '✕';
            del.addEventListener('click', function () { state[key].imgs = state[key].imgs.filter(function (x) { return x.id !== img.id; }); renderPreviews(row, key); syncHidden(); });
            w.appendChild(im); w.appendChild(del); prev.appendChild(w);
        });
    }
    function buildRow(color) {
        var key = color.toLowerCase();
        if (!state[key]) { state[key] = { name: color, imgs: [] }; } else { state[key].name = color; }
        var node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-key', key);
        node.querySelector('[data-color-name]').textContent = color;
        var dot = node.querySelector('[data-color-dot]'); var hx = hexOf(color);
        if (dot) { if (hx) { dot.style.background = hx; } else { dot.style.display = 'none'; } }
        var file = node.querySelector('[data-color-file]');
        file.addEventListener('change', function () {
            Array.prototype.filter.call(file.files || [], function (f) { return f && f.type.indexOf('image/') === 0; }).forEach(function (f) {
                pending++; setStatus();
                shrink(f).then(function (ff) { return sign().then(function (p) { return upload(ff, p); }); })
                    .then(function (res) { if (res && res.public_id) { state[key].imgs.push({ id: res.public_id, url: res.secure_url || '' }); renderPreviews(node, key); syncHidden(); } pending--; setStatus(); })
                    .catch(function () { pending--; setStatus(); });
            });
            file.value = '';
        });
        rowsBox.appendChild(node); renderPreviews(node, key);
    }
    function distinctColors() { var seen = {}, out = []; document.querySelectorAll('input[name="var_color[]"]').forEach(function (i) { var v = (i.value || '').trim(); if (!v) { return; } var k = v.toLowerCase(); if (seen[k]) { return; } seen[k] = true; out.push(v); }); return out; }
    function rebuild() {
        var colors = distinctColors();
        box.hidden = colors.length === 0;
        var present = {}; colors.forEach(function (c) { present[c.toLowerCase()] = true; });
        rowsBox.querySelectorAll('[data-color-row]').forEach(function (r) { if (!present[r.getAttribute('data-key')]) { r.remove(); } });
        var existing = {}; rowsBox.querySelectorAll('[data-color-row]').forEach(function (r) { existing[r.getAttribute('data-key')] = true; });
        colors.forEach(function (c) { if (!existing[c.toLowerCase()]) { buildRow(c); } });
        syncHidden();
    }
    document.addEventListener('input', function (ev) { if (ev.target && ev.target.name === 'var_color[]') { rebuild(); } });
    var addBtn = document.querySelector('[data-variant-add]'); if (addBtn) { addBtn.addEventListener('click', function () { setTimeout(rebuild, 40); }); }
    var rowsContainer = document.querySelector('[data-variant-rows]'); if (rowsContainer) { rowsContainer.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('[data-variant-del]')) { setTimeout(rebuild, 40); } }); }
    rebuild();
})();

/* Carrousel de pub en tête d'accueil : auto-défilement, flèches, points, swipe. */
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-carousel]').forEach(function (root) {
        var track = root.querySelector('.afk-carousel__track');
        var slides = root.querySelectorAll('.afk-carousel__slide');
        if (!track || slides.length < 2) { return; }
        var dots = root.querySelectorAll('[data-dot]');
        var delay = parseInt(root.getAttribute('data-autoplay') || '0', 10);
        var i = 0, timer = null;
        function go(n) {
            i = (n + slides.length) % slides.length;
            track.style.transform = 'translateX(' + (-i * 100) + '%)';
            dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
        }
        function next() { go(i + 1); }
        function prev() { go(i - 1); }
        function start() { stop(); if (delay && !reduce) { timer = setInterval(next, delay); } }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        var nx = root.querySelector('[data-next]'); if (nx) { nx.addEventListener('click', function () { next(); start(); }); }
        var pv = root.querySelector('[data-prev]'); if (pv) { pv.addEventListener('click', function () { prev(); start(); }); }
        dots.forEach(function (d) { d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-dot'), 10) || 0); start(); }); });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);
        var x0 = null;
        root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (x0 === null) { return; }
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) { if (dx < 0) { next(); } else { prev(); } }
            x0 = null; start();
        }, { passive: true });
        go(0); start();
    });
})();

/* Page « À propos » : apparition au scroll (sûr CSP — script externe). Si JS ou
   IntersectionObserver absent, le contenu reste visible (pas de .ab-reveal). */
(function () {
    var page = document.querySelector('.about-page');
    if (!page) { return; }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var els = page.querySelectorAll('.reveal');
    if (reduce || !('IntersectionObserver' in window) || !els.length) { return; }
    document.documentElement.classList.add('ab-reveal');
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
})();

/* ------------------------------------------------------------------ */
/* Caisse multi-étapes : un seul formulaire affiché étape par étape    */
/* (Suivant / Précédent). Amélioration progressive : sans JS, toutes   */
/* les étapes restent visibles et le formulaire reste soumettable.      */
/* ------------------------------------------------------------------ */
(function () {
    var form = document.querySelector('[data-checkout-wizard]');
    if (!form) { return; }
    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    if (steps.length < 2) { return; }
    var dots = Array.prototype.slice.call(form.querySelectorAll('[data-wiz-dot]'));
    var cur = 0;

    function show(i) {
        cur = Math.max(0, Math.min(i, steps.length - 1));
        steps.forEach(function (s, idx) { s.hidden = idx !== cur; });
        dots.forEach(function (d, idx) {
            d.classList.toggle('is-active', idx === cur);
            d.classList.toggle('is-done', idx < cur);
        });
        var top = form.getBoundingClientRect().top + window.pageYOffset - 80;
        window.scrollTo({ top: top < 0 ? 0 : top, behavior: 'smooth' });
    }

    function validStep(i) {
        var ok = true;
        steps[i].querySelectorAll('input, select, textarea').forEach(function (f) {
            if (f.disabled || f.type === 'hidden') { return; }
            if (!f.checkValidity()) { if (ok) { f.reportValidity(); } ok = false; }
        });
        if (!ok) { return false; }
        var contacts = steps[i].querySelectorAll('[data-wiz-contact]');
        if (contacts.length) {
            var any = false;
            contacts.forEach(function (c) { if ((c.value || '').trim() !== '') { any = true; } });
            if (!any) {
                var first = contacts[0];
                first.setCustomValidity(form.getAttribute('data-contact-msg') || 'Required');
                first.reportValidity();
                first.setCustomValidity('');
                return false;
            }
        }
        return true;
    }

    form.querySelectorAll('[data-wiz-next]').forEach(function (b) {
        b.addEventListener('click', function () { if (validStep(cur)) { show(cur + 1); } });
    });
    form.querySelectorAll('[data-wiz-prev]').forEach(function (b) {
        b.addEventListener('click', function () { show(cur - 1); });
    });

    var mm = form.querySelector('[data-mm-block]');
    function syncMM() {
        var sel = form.querySelector('input[name="payment_method"]:checked');
        if (mm) { mm.hidden = !(sel && sel.value === 'mobile_money'); }
    }
    form.querySelectorAll('[data-pay-method]').forEach(function (r) { r.addEventListener('change', syncMM); });
    syncMM();

    show(0);
})();

/* Caisse : déverrouiller le pays + la ville (préremplis depuis la géoloc). */
(function () {
    var form = document.querySelector('[data-checkout-wizard]');
    if (!form) { return; }
    var btn = form.querySelector('[data-geo-unlock]');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        var sel = form.querySelector('#cl-country');
        if (sel) {
            sel.disabled = false;
            sel.removeAttribute('tabindex');
            sel.removeAttribute('aria-disabled');
            sel.classList.remove('is-locked', 'locked-field');
            sel.name = 'dest_country';
        }
        var hid = form.querySelector('#cl-country_value');
        if (hid) { hid.disabled = true; }
        var city = form.querySelector('#cl-city');
        if (city) { city.removeAttribute('readonly'); city.classList.remove('is-locked'); }
        var note = form.querySelector('[data-geo-lock-note]');
        if (note) { note.hidden = true; }
    });
})();

/* ---- Agnès : assistant d'aide du site (chat + captures d'écran + liens) ---- */
(function () {
    'use strict';
    var root = document.querySelector('[data-agnes]');
    if (!root) { return; }
    var panel    = root.querySelector('[data-agnes-panel]');
    var toggle   = root.querySelector('[data-agnes-toggle]');
    var closeBtn = root.querySelector('[data-agnes-close]');
    var log      = root.querySelector('[data-agnes-log]');
    var suggest  = root.querySelector('[data-agnes-suggest]');
    var form     = root.querySelector('[data-agnes-form]');
    var input    = root.querySelector('[data-agnes-input]');
    var endpoint = root.getAttribute('data-endpoint');
    var errText  = root.getAttribute('data-err') || 'Error';
    var thinking = root.getAttribute('data-thinking') || '…';
    var busy = false;

    function openPanel(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && input) { input.focus(); }
    }
    function addMsg(cls, text) {
        var d = document.createElement('div');
        d.className = 'agnes-msg ' + cls;
        d.textContent = text;            // textContent : neutralise tout HTML
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function addReply(data) {
        addMsg('bot', (data && data.text) ? data.text : errText);
        if (data && data.screens) {
            data.screens.forEach(function (s) {
                if (!s || !s.src) { return; }
                var a = document.createElement('a');
                a.className = 'agnes-shot';
                a.href = s.src; a.target = '_blank'; a.rel = 'noopener';
                var img = document.createElement('img');
                img.src = s.src; img.alt = s.alt || ''; img.loading = 'lazy';
                a.appendChild(img);
                log.appendChild(a);
            });
        }
        if (data && data.links && data.links.length) {
            var row = document.createElement('div');
            row.className = 'agnes-actions';
            data.links.forEach(function (l) {
                // Défense en profondeur : on n'accepte qu'une URL http(s) absolue
                // (le serveur ne renvoie que des liens internes construits par url()).
                // Bloque tout javascript:/data: même si la réponse était détournée.
                if (!l || !l.url || !/^https?:\/\//i.test(l.url)) { return; }
                var a = document.createElement('a');
                a.className = 'agnes-action';
                a.href = l.url; a.textContent = l.label || l.url;
                row.appendChild(a);
            });
            log.appendChild(row);
        }
        log.scrollTop = log.scrollHeight;
    }
    function ask(text) {
        text = (text || '').trim();
        if (!text || busy) { return; }
        busy = true;
        addMsg('user', text);
        var wait = addMsg('bot thinking', thinking);
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(text) + '&path=' + encodeURIComponent(location.pathname)
        }).then(function (r) { return r.json(); }).then(function (data) {
            wait.remove();
            addReply(data);
        }).catch(function () {
            wait.remove();
            addMsg('bot', errText);
        }).then(function () { busy = false; });
    }

    if (toggle) { toggle.addEventListener('click', function () { openPanel(panel.hidden); }); }
    if (closeBtn) { closeBtn.addEventListener('click', function () { openPanel(false); }); }
    if (suggest) {
        Array.prototype.forEach.call(suggest.querySelectorAll('[data-agnes-q]'), function (b) {
            b.addEventListener('click', function () { ask(b.textContent || ''); });
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            ask(input ? input.value : '');
            if (input) { input.value = ''; }
        });
    }
})();

/* ---- Auth v2 : mot de passe (œil / jauge de force / concordance) + gate
   consentement. CSP-safe (aucun script inline) ; piloté par data-* qui portent
   aussi les libellés i18n → aucune chaîne en dur ici. Amélioration progressive :
   sans JS tout reste utilisable et le serveur valide. ---- */
(function () {
    'use strict';

    // Œil : afficher / masquer (délégué → robuste à tout ajout de champ).
    document.addEventListener('click', function (ev) {
        var t = ev.target && ev.target.closest ? ev.target.closest('[data-pwd-toggle]') : null;
        if (!t) { return; }
        var input = document.getElementById(t.getAttribute('data-pwd-toggle'));
        if (!input) { return; }
        var reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        var lbl = t.getAttribute(reveal ? 'data-hide' : 'data-show');
        if (lbl) { t.setAttribute('aria-label', lbl); }
        var eye = t.querySelector('.eye'), off = t.querySelector('.eye-off');
        if (eye && off) { eye.hidden = reveal; off.hidden = !reveal; }
    });

    function pwdLevel(pw) {
        if (!pw) { return 0; }
        var s = 0;
        if (pw.length >= 12) { s++; }
        if (/[A-Z]/.test(pw)) { s++; }
        if (/[0-9]/.test(pw)) { s++; }
        if (/[^A-Za-z0-9]/.test(pw)) { s++; }
        return s;
    }
    Array.prototype.forEach.call(document.querySelectorAll('input[data-pwd-strength]'), function (input) {
        var field = input.closest('.afield') || input.parentNode;
        var bars = field.querySelector('.pwd-strength');
        var label = field.querySelector('[data-pwd-strength-label]');
        input.addEventListener('input', function () {
            var lv = pwdLevel(input.value);
            if (bars) { bars.setAttribute('data-lvl', String(lv)); }
            if (label) {
                if (lv > 0 && bars) {
                    label.textContent = (bars.getAttribute('data-prefix') || '') + ' : ' + (bars.getAttribute('data-l' + lv) || '') + ' · ';
                } else { label.textContent = ''; }
            }
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('input[data-pwd-match]'), function (confirmInput) {
        var orig = document.getElementById(confirmInput.getAttribute('data-pwd-match'));
        var field = confirmInput.closest('.afield') || confirmInput.parentNode;
        var msg = field.querySelector('[data-pwd-match-msg]');
        function upd() {
            if (!msg) { return; }
            if (!confirmInput.value) { msg.className = 'pwd-match'; msg.textContent = ''; return; }
            var ok = orig && orig.value === confirmInput.value;
            msg.className = 'pwd-match ' + (ok ? 'ok' : 'no');
            msg.textContent = (ok ? '✓ ' : '✕ ') + (msg.getAttribute(ok ? 'data-ok' : 'data-no') || '');
        }
        confirmInput.addEventListener('input', upd);
        if (orig) { orig.addEventListener('input', upd); }
    });

    // Gate consentement : bouton d'envoi désactivé tant que la case légale
    // n'est pas cochée (le serveur revalide de toute façon).
    Array.prototype.forEach.call(document.querySelectorAll('form[data-consent-gate]'), function (form) {
        var box = form.querySelector('input[name="accept_legal"]');
        var btn = form.querySelector('[data-consent-submit]');
        if (!box || !btn) { return; }
        function sync() { btn.disabled = !box.checked; }
        box.addEventListener('change', sync);
        sync();
    });
})();
