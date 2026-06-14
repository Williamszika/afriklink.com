<?php
declare(strict_types=1);

/**
 * Jeu d'icônes « outline » maison (une seule famille cohérente), rendu en SVG
 * inline par le helper icon(). Inline = compatible CSP stricte (aucune police
 * d'icônes ni ressource externe). viewBox 0 0 24 24, trait = currentColor.
 * N'indiquer ici que le contenu interne du <svg> (chemins/formes).
 */

return [
    'grid'       => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
    'store'      => '<path d="M4 8 5.5 4h13L20 8z"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M9.5 20v-5h5v5"/>',
    'receipt'    => '<path d="M5 3h14v18l-2.3-1.4L14.3 21 12 19.6 9.7 21l-2.4-1.4L5 21z"/><path d="M9 8h6"/><path d="M9 12h6"/><path d="M9 16h4"/>',
    'package'    => '<path d="M21 8.5 12 3.5 3 8.5v7L12 20.5l9-5z"/><path d="m3 8.5 9 5 9-5"/><path d="M12 13.5v7"/>',
    'chat'       => '<path d="M20 15.5a2 2 0 0 1-2 2H8l-4 3.5V5.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>',
    'megaphone'  => '<path d="M4 9v4a1 1 0 0 0 1 1h2V8H5a1 1 0 0 0-1 1z"/><path d="M7 8 18 4v14L7 14z"/><path d="M8 14v3a2 2 0 0 0 4 0v-2"/>',
    'users'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5a3 3 0 0 1 0 6"/><path d="M17.5 14.5A5 5 0 0 1 20.5 20"/>',
    'shield'     => '<path d="M12 3 5 6v5c0 4.6 3 7.6 7 9 4-1.4 7-4.4 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'building'   => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M10 21v-4h4v4"/><path d="M9 7h1"/><path d="M14 7h1"/><path d="M9 11h1"/><path d="M14 11h1"/>',
    'settings'   => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2v3"/><path d="M12 19v3"/><path d="M4.2 4.2 6.3 6.3"/><path d="M17.7 17.7l2.1 2.1"/><path d="M2 12h3"/><path d="M19 12h3"/><path d="M4.2 19.8 6.3 17.7"/><path d="M17.7 6.3l2.1-2.1"/>',
    'wallet'     => '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/><circle cx="16.5" cy="14.5" r="1.3"/>',
    'bell'       => '<path d="M6 9a6 6 0 0 1 12 0c0 4.5 1.8 5.7 2 6H4c.2-.3 2-1.5 2-6z"/><path d="M10.2 20a2 2 0 0 0 3.6 0"/>',
    'link'       => '<path d="M9.5 14.5 14.5 9.5"/><path d="M11 7.5 12.3 6.2a3.6 3.6 0 0 1 5.1 5.1L16 12.7"/><path d="M13 16.5l-1.3 1.3a3.6 3.6 0 0 1-5.1-5.1L8 11.3"/>',
    'eye'        => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3.2"/>',
    'tag'        => '<path d="M3 12.5V4.5A1.5 1.5 0 0 1 4.5 3h8L21 11.5 13.5 19z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
    'lightbulb'  => '<path d="M9 18h6"/><path d="M10 21h4"/><path d="M12 3a6 6 0 0 0-3.8 10.6c.7.6 1.1 1.2 1.2 2.4h5.2c.1-1.2.5-1.8 1.2-2.4A6 6 0 0 0 12 3z"/>',
    'camera'     => '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7H7l1.5-2.5h7L16 7h3.5A1.5 1.5 0 0 1 21 8.5V18a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18z"/><circle cx="12" cy="13" r="3.3"/>',
    'zap'        => '<path d="M13 2 4.5 13H11l-1 9 8.5-11H12z"/>',
];
