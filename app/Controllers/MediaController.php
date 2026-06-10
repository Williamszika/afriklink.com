<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\CloudinaryService;

/**
 * Signe les envois directs navigateur → Cloudinary (photos/vidéos d'annonces).
 * Le secret ne quitte jamais le serveur ; le navigateur ne reçoit qu'une
 * signature valable pour UN dossier imposé et un horodatage.
 */
final class MediaController
{
    public function sign(Request $request): void
    {
        if (!CloudinaryService::configured()) {
            json_response(['error' => 'media_unconfigured'], 503);
        }
        $type = whitelist((string) input_string('resource_type', 'image'), ['image', 'video'], 'image');
        json_response(CloudinaryService::signUploadParams($type));
    }
}
