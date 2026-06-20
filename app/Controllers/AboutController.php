<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;

/** Page « À propos » : présentation publique du site (déplacée depuis le hero d'accueil). */
final class AboutController
{
    public function index(Request $request): void
    {
        view('about', [
            'page_title' => t('about.title'),
            'meta'       => ['description' => t('home.hero_subtitle')],
        ]);
    }
}
