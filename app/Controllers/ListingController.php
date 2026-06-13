<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Listing;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\CloudinaryService;

/**
 * Annonces entre particuliers : dépôt (photos 1-5 + vidéo ≤ 60 s), gestion
 * (« Mes annonces »), page publique, changement de statut.
 *
 * Les médias sont envoyés par le navigateur directement à Cloudinary ; ici on
 * ne reçoit que leurs identifiants, et on RE-VÉRIFIE chacun côté serveur
 * (existence sur notre compte, type, dossier, durée vidéo) avant d'accepter.
 */
final class ListingController
{
    /* ---- Dépôt ---------------------------------------------------- */

    public function create(Request $request): void
    {
        view('listings/create', [
            'user'        => current_user() ?? [],
            'media_ready' => CloudinaryService::configured(),
        ]);
    }

    public function store(Request $request): void
    {
        if (!CloudinaryService::configured()) {
            flash('error', t('listing.media_unconfigured'));
            redirect('/vendre');
        }
        $user   = current_user() ?? [];
        $userId = (int) current_user_id();

        [$data, $errors] = $this->validateFields();

        // Photos : identifiants Cloudinary transmis par le JS du formulaire.
        $maxPhotos = (int) config('listings.max_photos', 5);
        $photoIds  = json_decode((string) ($_POST['photos_json'] ?? '[]'), true);
        $photoIds  = is_array($photoIds) ? array_values(array_unique(array_filter($photoIds, 'is_string'))) : [];
        if (count($photoIds) < 1) {
            $errors['photos'] = t('validation.photos_required');
        } elseif (count($photoIds) > $maxPhotos) {
            $errors['photos'] = t('validation.photos_too_many', ['max' => $maxPhotos]);
        }

        $videoId = input_string('video_public_id');

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/vendre');
        }

        // Vérité serveur : chaque média doit exister sur NOTRE compte Cloudinary.
        $photos = [];
        foreach ($photoIds as $pid) {
            $meta = CloudinaryService::verifyAsset('image', $pid);
            if ($meta === null) {
                keep_old($_POST);
                set_errors(['photos' => t('validation.photos_invalid')]);
                redirect('/vendre');
            }
            $photos[] = ['public_id' => $pid, 'width' => $meta['width'], 'height' => $meta['height']];
        }

        $videoDuration = null;
        if ($videoId !== null) {
            $meta = CloudinaryService::verifyAsset('video', $videoId);
            $maxSeconds = (int) config('listings.max_video_seconds', 60);
            if ($meta === null) {
                keep_old($_POST);
                set_errors(['video' => t('validation.video_invalid')]);
                redirect('/vendre');
            }
            // Tolérance d'une seconde : l'encodage téléphone arrondit parfois à 60,4 s.
            if (($meta['duration'] ?? 0.0) > $maxSeconds + 1) {
                CloudinaryService::destroy('video', $videoId);
                keep_old($_POST);
                set_errors(['video' => t('validation.video_too_long', ['max' => $maxSeconds])]);
                redirect('/vendre');
            }
            $videoDuration = $meta['duration'];
        }

        $publicId = Listing::create([
            'user_id'         => $userId,
            'title'           => $data['title'],
            'description'     => $data['description'],
            'category'        => $data['category'],
            'price_cents'     => $data['price_cents'],
            'currency'        => $data['currency'],
            'item_condition'  => $data['condition'],
            'country_code'    => strtoupper((string) ($user['country_code'] ?? '')) ?: null,
            'city'            => $data['city'],
            'whatsapp_optin'  => $data['whatsapp_optin'] && !empty($user['phone']),
            'video_public_id' => $videoId,
            'video_duration'  => $videoDuration,
        ], $photos);

        AuditLog::record($userId, 'listing.created', 'listing', null, ['public_id' => $publicId], $request->ipBinary());
        clear_old();
        flash('success', t('flash.listing_created'));
        redirect('/annonce/' . $publicId);
    }

    /* ---- Mes annonces --------------------------------------------- */

    public function mine(Request $request): void
    {
        $userId   = (int) current_user_id();
        $listings = Listing::forUser($userId);
        $mains    = Listing::mainPhotos(array_map(static fn (array $l): int => (int) $l['id'], $listings));

        view('listings/mine', ['listings' => $listings, 'mains' => $mains]);
    }

    /* ---- Page publique --------------------------------------------- */

    public function show(Request $request): void
    {
        $listing = Listing::findByPublicId((string) $request->param('pid', ''));
        if ($listing === null || $listing['status'] === 'deleted') {
            abort(404);
        }
        $isOwner = (int) $listing['user_id'] === (int) (current_user_id() ?? 0);
        // Une annonce en pause/vendue n'est visible que par son propriétaire.
        if (!$isOwner && $listing['status'] !== 'active' && $listing['status'] !== 'sold') {
            abort(404);
        }

        $seller = User::findById((int) $listing['user_id']) ?? [];

        view('listings/show', [
            'listing'        => $listing,
            'photos'         => Listing::photos((int) $listing['id']),
            'seller'         => $seller,
            'is_owner'       => $isOwner,
            'avatar_version' => \App\Models\Avatar::versionFor((int) $listing['user_id']),
        ]);
    }

    /* ---- Modifier / statut ----------------------------------------- */

    public function edit(Request $request): void
    {
        $listing = $this->ownListingOr404((string) $request->param('pid', ''));
        view('listings/edit', [
            'listing' => $listing,
            'photos'  => Listing::photos((int) $listing['id']),
        ]);
    }

    public function update(Request $request): void
    {
        $listing = $this->ownListingOr404((string) $request->param('pid', ''));
        [$data, $errors] = $this->validateFields();

        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/annonce/' . $listing['public_id'] . '/modifier');
        }

        $user = current_user() ?? [];
        Listing::updateFields((int) $listing['id'], [
            'title'          => $data['title'],
            'description'    => $data['description'],
            'category'       => $data['category'],
            'price_cents'    => $data['price_cents'],
            'currency'       => $data['currency'],
            'item_condition' => $data['condition'],
            'city'           => $data['city'],
            'whatsapp_optin' => $data['whatsapp_optin'] && !empty($user['phone']),
        ]);

        AuditLog::record((int) current_user_id(), 'listing.updated', 'listing', (int) $listing['id'], [], $request->ipBinary());
        clear_old();
        flash('success', t('flash.listing_updated'));
        redirect('/annonce/' . $listing['public_id']);
    }

    /** Met en avant (ou retire) une annonce — « sponsorisé », simulation gratuite. */
    public function promote(Request $request): void
    {
        $listing = $this->ownListingOr404((string) $request->param('pid', ''));
        $action  = whitelist((string) input_string('action', ''), ['promote', 'stop'], null);
        if ($action === null) {
            abort(404);
        }
        Listing::setPromoted((int) $listing['id'], $action === 'promote' ? 7 : null);
        flash('success', t($action === 'promote' ? 'ads.promoted_flash' : 'ads.stopped_flash'));
        redirect('/annonces');
    }

    public function setStatus(Request $request): void
    {
        $listing = $this->ownListingOr404((string) $request->param('pid', ''));
        $action  = whitelist((string) input_string('action', ''), ['pause', 'activate', 'sold', 'delete'], null);
        if ($action === null) {
            abort(404);
        }

        $status = match ($action) {
            'pause'    => 'paused',
            'activate' => 'active',
            'sold'     => 'sold',
            'delete'   => 'deleted',
        };
        Listing::setStatus((int) $listing['id'], $status);

        // Suppression : on efface aussi les médias chez Cloudinary (meilleur effort).
        if ($status === 'deleted') {
            foreach (Listing::photos((int) $listing['id']) as $photo) {
                CloudinaryService::destroy('image', (string) $photo['cloud_public_id']);
            }
            if (!empty($listing['video_public_id'])) {
                CloudinaryService::destroy('video', (string) $listing['video_public_id']);
            }
        }

        AuditLog::record((int) current_user_id(), 'listing.' . $status, 'listing', (int) $listing['id'], [], $request->ipBinary());
        flash('success', t('flash.listing_' . $status));
        redirect($status === 'deleted' ? '/annonces' : '/annonce/' . $listing['public_id']);
    }

    /* ---- Helpers ---------------------------------------------------- */

    private function ownListingOr404(string $publicId): array
    {
        $listing = Listing::findByPublicId($publicId);
        if ($listing === null
            || $listing['status'] === 'deleted'
            || (int) $listing['user_id'] !== (int) (current_user_id() ?? 0)) {
            abort(404); // 404 (pas 403) pour ne pas révéler l'existence — security.md §5
        }
        return $listing;
    }

    /**
     * Valide les champs texte/prix communs au dépôt et à la modification.
     * @return array{0:array,1:array<string,string>} [données propres, erreurs]
     */
    private function validateFields(): array
    {
        $errors = [];

        $titleMax = (int) config('listings.title_max', 120);
        $descMax  = (int) config('listings.description_max', 5000);

        $title = input_string('title');
        if ($title === null || mb_strlen($title) < 4 || mb_strlen($title) > $titleMax) {
            $errors['title'] = t('validation.title_length', ['max' => $titleMax]);
        }

        $description = input_string('description');
        if ($description === null || mb_strlen($description) < 10 || mb_strlen($description) > $descMax) {
            $errors['description'] = t('validation.description_length', ['max' => $descMax]);
        }

        $category = whitelist((string) input_string('category', ''), config('listings.categories', []), null);
        if ($category === null) {
            $errors['category'] = t('validation.required');
        }

        $condition = whitelist((string) input_string('condition', ''), config('listings.conditions', []), null);
        if ($condition === null) {
            $errors['condition'] = t('validation.required');
        }

        $currency = input_currency('currency', config('app.currencies', ['EUR', 'USD', 'XOF', 'NGN', 'GBP']));
        if ($currency === null) {
            $errors['currency'] = t('validation.required');
        }

        $priceCents = parse_price_to_cents((string) input_string('price', ''), $currency ?? 'EUR');
        if ($priceCents === null || $priceCents < 1 || $priceCents > 99999999900) {
            $errors['price'] = t('validation.price_invalid');
        }

        $city = input_string('city');
        if ($city !== null && mb_strlen($city) > 120) {
            $city = mb_substr($city, 0, 120);
        }

        return [[
            'title'          => $title,
            'description'    => $description,
            'category'       => $category,
            'condition'      => $condition,
            'currency'       => $currency,
            'price_cents'    => $priceCents,
            'city'           => $city,
            'whatsapp_optin' => (string) ($_POST['whatsapp_optin'] ?? '') === '1',
        ], $errors];
    }
}
