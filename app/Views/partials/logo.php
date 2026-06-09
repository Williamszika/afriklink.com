<?php
/**
 * Afriklink logo — a cowrie shell (cauri), shaded to read as 3D.
 * Inline SVG (crisp at any size, themeable, CSP-safe — no script, no external asset).
 * Pass a unique $uid when including more than once on a page to avoid duplicate gradient ids.
 *
 * @var string $uid
 */
$uid = $uid ?? uniqid('cauri');
?>
<svg class="cauri" viewBox="0 0 48 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
        <radialGradient id="<?= $uid ?>-body" cx="36%" cy="28%" r="85%">
            <stop offset="0%" stop-color="#fff7e6"/>
            <stop offset="42%" stop-color="#f6dca5"/>
            <stop offset="78%" stop-color="#d8a955"/>
            <stop offset="100%" stop-color="#a9772f"/>
        </radialGradient>
        <linearGradient id="<?= $uid ?>-slit" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#7a571f"/>
            <stop offset="50%" stop-color="#34230d"/>
            <stop offset="100%" stop-color="#7a571f"/>
        </linearGradient>
    </defs>

    <!-- shell body -->
    <path d="M24 5 C33 5 39.5 16 39.5 32 C39.5 49 33 59 24 59 C15 59 8.5 49 8.5 32 C8.5 16 15 5 24 5 Z"
          fill="url(#<?= $uid ?>-body)" stroke="#9a6b2b" stroke-width="0.6"/>

    <!-- glossy highlight (top-left) -->
    <ellipse cx="18" cy="18" rx="5.5" ry="9" fill="#ffffff" opacity="0.35" transform="rotate(-18 18 18)"/>

    <!-- central aperture / slit -->
    <path d="M24 16 C26.4 26 26.4 38 24 52 C21.6 38 21.6 26 24 16 Z" fill="url(#<?= $uid ?>-slit)"/>

    <!-- denticles (teeth) -->
    <g stroke="#7a571f" stroke-width="1.3" stroke-linecap="round" opacity="0.9">
        <line x1="22" y1="22" x2="17.5" y2="21.4"/>
        <line x1="22" y1="27" x2="17"   y2="26.6"/>
        <line x1="22" y1="32" x2="16.8" y2="32"/>
        <line x1="22" y1="37" x2="17"   y2="37.4"/>
        <line x1="22" y1="42" x2="17.5" y2="42.6"/>
        <line x1="22" y1="47" x2="18.5" y2="47.6"/>
        <line x1="26" y1="22" x2="30.5" y2="21.4"/>
        <line x1="26" y1="27" x2="31"   y2="26.6"/>
        <line x1="26" y1="32" x2="31.2" y2="32"/>
        <line x1="26" y1="37" x2="31"   y2="37.4"/>
        <line x1="26" y1="42" x2="30.5" y2="42.6"/>
        <line x1="26" y1="47" x2="29.5" y2="47.6"/>
    </g>
</svg>
