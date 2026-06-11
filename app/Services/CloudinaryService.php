<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Cloudinary — hébergement des photos et vidéos d'annonces (plan gratuit).
 *
 * Les fichiers partent DIRECTEMENT du navigateur vers Cloudinary (envoi signé) :
 * ils ne transitent jamais par Vercel, dont la limite de requête (~4,5 Mo)
 * interdirait les vidéos. Le serveur ne fait que :
 *   1. signer les paramètres d'envoi (signUploadParams) ;
 *   2. VÉRIFIER après coup, via l'API Admin, que chaque fichier annoncé par le
 *      client existe vraiment sur notre compte, est du bon type, rangé dans
 *      notre dossier — et que la vidéo ne dépasse pas la durée maximale.
 *
 * Aucune dépendance Composer : cURL natif. Clés dans les variables d'env :
 * CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET.
 */
final class CloudinaryService
{
    public const FOLDER = 'afriklink';

    /**
     * Nom du cloud Cloudinary du projet. PAS un secret : il figure dans toutes
     * les URLs publiques de diffusion (res.cloudinary.com/<cloud>/…). Servi en
     * secours si CLOUDINARY_CLOUD_NAME / CLOUDINARY_URL ne le fournissent pas.
     */
    private const DEFAULT_CLOUD = 'daljbrmog';

    /** Description secret-free de la dernière erreur (diagnostic /health). */
    public static ?string $lastError = null;

    /** @var array{cloud:string,key:string,secret:string}|null cache par requête */
    private static ?array $creds = null;

    public static function configured(): bool
    {
        $c = self::creds();
        return $c['cloud'] !== '' && $c['key'] !== '' && $c['secret'] !== ''
            && !self::hasPlaceholder($c);
    }

    /**
     * État lisible pour /health — ne révèle jamais de secret, mais explique
     * précisément ce qui cloche (gabarit non remplacé, variable manquante,
     * CLOUDINARY_URL illisible…).
     * @return array{status:string,cloud?:string,hint?:string}
     */
    public static function diagnostic(): array
    {
        $c = self::creds();
        if (self::configured()) {
            // Ping réel de l'API (authentifié) : prouve que la clé + le secret marchent.
            $ping = self::request('GET', sprintf(
                'https://api.cloudinary.com/v1_1/%s/ping',
                rawurlencode($c['cloud'])
            ));
            return [
                'status' => $ping !== null ? 'ok' : 'auth_failed',
                'cloud'  => $c['cloud'],
                'api'    => $ping !== null ? 'ok' : ('erreur — ' . (self::$lastError ?? 'inconnue')
                    . ' (clé ou secret invalide, ou cloud name erroné)'),
            ];
        }

        $rawUrl = self::clean(self::env('CLOUDINARY_URL'));
        $empty  = $c['cloud'] === '' && $c['key'] === '' && $c['secret'] === '' && $rawUrl === '';

        $problems = [];
        if (self::hasPlaceholder($c)) {
            $problems[] = 'valeurs encore à l’état de gabarit (<your_api_key>/<your_api_secret>) — révèle le secret sur le Dashboard Cloudinary puis recopie';
        }
        if ($rawUrl !== '' && self::parseUrl($rawUrl) === null) {
            $problems[] = 'CLOUDINARY_URL illisible — forme attendue : cloudinary://API_KEY:API_SECRET@cloud_name';
        }
        if ($c['cloud'] !== '' && (str_contains($c['cloud'], '=') || stripos($c['cloud'], 'cloudinary://') !== false)) {
            $problems[] = 'CLOUDINARY_CLOUD_NAME doit être le nom court seul (ex. « daljbrmog »)';
        }
        if (!$empty) {
            foreach (['CLOUDINARY_CLOUD_NAME' => 'cloud', 'CLOUDINARY_API_KEY' => 'key', 'CLOUDINARY_API_SECRET' => 'secret'] as $name => $k) {
                if ($c[$k] === '') {
                    $problems[] = $name . ' manquante';
                }
            }
        }

        return [
            'status'         => $empty ? 'unconfigured' : 'misconfigured',
            'has_cloud_name' => $c['cloud'] !== '',
            'has_api_key'    => $c['key'] !== '',
            'has_api_secret' => $c['secret'] !== '',
            'hint'           => $problems !== []
                ? implode(' ; ', $problems)
                : 'Définis CLOUDINARY_URL (cloudinary://API_KEY:API_SECRET@cloud_name) ou les trois variables séparées.',
        ];
    }

    public static function cloudName(): string
    {
        return self::creds()['cloud'];
    }

    private static function apiKey(): string
    {
        return self::creds()['key'];
    }

    private static function apiSecret(): string
    {
        return self::creds()['secret'];
    }

    /**
     * Résout les identifiants depuis la forme canonique Cloudinary
     * CLOUDINARY_URL = cloudinary://API_KEY:API_SECRET@cloud_name, OU depuis les
     * trois variables séparées. Tolérances : préfixe « NOM=… » collé par erreur,
     * guillemets parasites, et URL collée dans n’importe laquelle des variables.
     * @return array{cloud:string,key:string,secret:string}
     */
    private static function creds(): array
    {
        if (self::$creds !== null) {
            return self::$creds;
        }
        $cloud  = self::clean(self::env('CLOUDINARY_CLOUD_NAME'));
        $key    = self::clean(self::env('CLOUDINARY_API_KEY'));
        $secret = self::clean(self::env('CLOUDINARY_API_SECRET'));

        // Une URL cloudinary:// valide (où qu'elle soit collée) a priorité.
        foreach ([self::clean(self::env('CLOUDINARY_URL')), $cloud, $key, $secret] as $candidate) {
            if (stripos($candidate, 'cloudinary://') === false) {
                continue;
            }
            $parsed = self::parseUrl($candidate);
            if ($parsed !== null) {
                return self::$creds = $parsed;
            }
        }
        if ($cloud === '' && $key !== '' && $secret !== '') {
            $cloud = self::DEFAULT_CLOUD; // identifiant public du projet
        }
        return self::$creds = ['cloud' => $cloud, 'key' => $key, 'secret' => $secret];
    }

    private static function env(string $key): string
    {
        $v = $_ENV[$key] ?? getenv($key);
        return is_string($v) ? $v : '';
    }

    /** Retire espaces/guillemets et un éventuel préfixe « VARNAME= » collé par erreur. */
    private static function clean(string $v): string
    {
        $v = trim($v);
        if (preg_match('/^\s*CLOUDINARY_[A-Z_]+\s*=\s*(.*)$/s', $v, $m) === 1) {
            $v = trim($m[1]);
        }
        return trim($v, "\"' \t\n\r");
    }

    /**
     * cloudinary://KEY:SECRET@CLOUD → identifiants, ou null si gabarit/invalide.
     * @return array{cloud:string,key:string,secret:string}|null
     */
    private static function parseUrl(string $v): ?array
    {
        if (preg_match('#cloudinary://([^:@/\s]+):([^@/\s]+)@([^/\s]+)#i', $v, $m) !== 1) {
            return null;
        }
        if (preg_match('/[<>]/', $m[1] . $m[2] . $m[3]) === 1) {
            return null; // gabarit <your_api_key> non remplacé
        }
        return ['cloud' => $m[3], 'key' => $m[1], 'secret' => $m[2]];
    }

    /** Détecte les gabarits Cloudinary laissés tels quels. */
    private static function hasPlaceholder(array $c): bool
    {
        $joined = ($c['cloud'] ?? '') . ($c['key'] ?? '') . ($c['secret'] ?? '');
        return preg_match('/[<>]/', $joined) === 1 || stripos($joined, 'your_api') !== false;
    }

    /**
     * Paramètres signés pour un envoi direct navigateur → Cloudinary.
     * @param 'image'|'video' $resourceType
     * @return array{cloud_name:string,api_key:string,timestamp:int,folder:string,signature:string,resource_type:string}
     */
    public static function signUploadParams(string $resourceType): array
    {
        $timestamp = time();
        $folder    = self::FOLDER . '/' . ($resourceType === 'video' ? 'videos' : 'photos');
        // Signature Cloudinary : sha1 des paramètres triés (hors api_key/file) + secret.
        $signature = sha1('folder=' . $folder . '&timestamp=' . $timestamp . self::apiSecret());

        return [
            'cloud_name'    => self::cloudName(),
            'api_key'       => self::apiKey(),
            'timestamp'     => $timestamp,
            'folder'        => $folder,
            'signature'     => $signature,
            'resource_type' => $resourceType,
        ];
    }

    /**
     * Envoi PRIVÉ (type=authenticated) pour les pièces KYC : jamais d'URL
     * publique, consultable seulement via une URL signée (voir signedKycUrl).
     * Dossier par utilisateur : afriklink/kyc/<userId>.
     * @return array{cloud_name:string,api_key:string,timestamp:int,folder:string,type:string,signature:string,resource_type:string}
     */
    public static function signKycUpload(int $userId): array
    {
        $timestamp = time();
        $folder    = self::FOLDER . '/kyc/' . $userId;
        // Paramètres signés triés alphabétiquement : folder, timestamp, type.
        $signature = sha1('folder=' . $folder . '&timestamp=' . $timestamp . '&type=authenticated' . self::apiSecret());

        return [
            'cloud_name'    => self::cloudName(),
            'api_key'       => self::apiKey(),
            'timestamp'     => $timestamp,
            'folder'        => $folder,
            'type'          => 'authenticated',
            'signature'     => $signature,
            'resource_type' => 'image',
        ];
    }

    /**
     * Vérifie qu'une pièce KYC existe bien sur notre compte, dans le dossier de
     * CET utilisateur, et en mode authenticated (privé).
     * @return array{bytes:int,format:string,version:int}|null
     */
    public static function verifyKycAsset(int $userId, string $publicId): ?array
    {
        self::$lastError = null;
        $expectedPrefix = self::FOLDER . '/kyc/' . $userId . '/';
        if (!str_starts_with($publicId, $expectedPrefix)
            || preg_match('#^[A-Za-z0-9_/\-]{1,255}$#', $publicId) !== 1) {
            return null;
        }
        $url = sprintf(
            'https://api.cloudinary.com/v1_1/%s/resources/image/authenticated/%s',
            rawurlencode(self::cloudName()),
            implode('/', array_map('rawurlencode', explode('/', $publicId)))
        );
        $body = self::request('GET', $url);
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['public_id'] ?? '') !== $publicId) {
            return null;
        }
        return [
            'bytes'   => (int) ($data['bytes'] ?? 0),
            'format'  => (string) ($data['format'] ?? ''),
            'version' => (int) ($data['version'] ?? 0),
        ];
    }

    /**
     * URL de diffusion SIGNÉE d'une pièce authenticated (privée). Le serveur
     * (relecteur) l'utilise pour récupérer l'image ; sans signature valide,
     * Cloudinary refuse l'accès. Inclut la version pour cibler la bonne image.
     */
    public static function signedKycUrl(string $publicId, int $version, string $format): string
    {
        $toSign = $publicId . '.' . $format;
        // Signature courte Cloudinary : base64url(sha1_raw(toSign + secret)), 8 car.
        $sig = substr(strtr(base64_encode(sha1($toSign . self::apiSecret(), true)), '+/', '-_'), 0, 8);
        return sprintf(
            'https://res.cloudinary.com/%s/image/authenticated/s--%s--/v%d/%s.%s',
            rawurlencode(self::cloudName()),
            $sig,
            $version,
            $publicId,
            $format
        );
    }

    /**
     * Vérité serveur sur un fichier envoyé : interroge l'API Admin et retourne
     * ses métadonnées, ou null si le fichier n'existe pas / n'est pas à nous.
     * @param 'image'|'video' $resourceType
     * @return array{width:?int,height:?int,bytes:int,duration:?float,format:string}|null
     */
    public static function verifyAsset(string $resourceType, string $publicId): ?array
    {
        self::$lastError = null;
        $expectedPrefix = self::FOLDER . '/' . ($resourceType === 'video' ? 'videos' : 'photos') . '/';
        if (!str_starts_with($publicId, $expectedPrefix)
            || preg_match('#^[A-Za-z0-9_/\-]{1,255}$#', $publicId) !== 1) {
            return null;
        }

        $url = sprintf(
            'https://api.cloudinary.com/v1_1/%s/resources/%s/upload/%s',
            rawurlencode(self::cloudName()),
            $resourceType,
            implode('/', array_map('rawurlencode', explode('/', $publicId)))
        );
        $body = self::request('GET', $url);
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['public_id'] ?? '') !== $publicId) {
            return null;
        }
        return [
            'width'    => isset($data['width']) ? (int) $data['width'] : null,
            'height'   => isset($data['height']) ? (int) $data['height'] : null,
            'bytes'    => (int) ($data['bytes'] ?? 0),
            'duration' => isset($data['duration']) ? (float) $data['duration'] : null,
            'format'   => (string) ($data['format'] ?? ''),
        ];
    }

    /** Supprime un fichier (meilleur effort — jamais bloquant pour l'appelant). */
    public static function destroy(string $resourceType, string $publicId): void
    {
        $timestamp = time();
        $signature = sha1('public_id=' . $publicId . '&timestamp=' . $timestamp . self::apiSecret());
        $url = sprintf(
            'https://api.cloudinary.com/v1_1/%s/%s/destroy',
            rawurlencode(self::cloudName()),
            $resourceType
        );
        self::request('POST', $url, [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key'   => self::apiKey(),
            'signature' => $signature,
        ]);
    }

    /** Supprime une pièce KYC privée (type=authenticated). Meilleur effort. */
    public static function destroyKyc(string $publicId): void
    {
        $timestamp = time();
        $signature = sha1('public_id=' . $publicId . '&timestamp=' . $timestamp . '&type=authenticated' . self::apiSecret());
        $url = sprintf('https://api.cloudinary.com/v1_1/%s/image/destroy', rawurlencode(self::cloudName()));
        self::request('POST', $url, [
            'public_id' => $publicId,
            'type'      => 'authenticated',
            'timestamp' => $timestamp,
            'api_key'   => self::apiKey(),
            'signature' => $signature,
        ]);
    }

    /** Récupère les octets bruts d'une pièce KYC via son URL signée (relecteur). */
    public static function fetchKycBytes(string $publicId, int $version, string $format): ?array
    {
        $url = self::signedKycUrl($publicId, $version, $format);
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ctype  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($status === 200 && is_string($body) && $body !== '') {
                return ['bytes' => $body, 'content_type' => $ctype ?: 'application/octet-stream'];
            }
            self::$lastError = 'HTTP ' . $status;
            return null;
        } catch (\Throwable $e) {
            self::$lastError = 'exception — ' . $e->getMessage();
            return null;
        }
    }

    /* ---- URLs de diffusion (CDN) --------------------------------- */

    /** Image recadrée/optimisée (q_auto + f_auto = compression automatique). */
    public static function imageUrl(string $publicId, int $w, int $h): string
    {
        return sprintf(
            'https://res.cloudinary.com/%s/image/upload/c_fill,w_%d,h_%d,q_auto,f_auto/%s',
            rawurlencode(self::cloudName()),
            $w,
            $h,
            $publicId
        );
    }

    /** Vidéo MP4 H.264 en qualité automatique (compatible partout). */
    public static function videoUrl(string $publicId): string
    {
        return sprintf(
            'https://res.cloudinary.com/%s/video/upload/q_auto,f_mp4,vc_h264/%s.mp4',
            rawurlencode(self::cloudName()),
            $publicId
        );
    }

    /** Miniature de la vidéo (première seconde). */
    public static function videoPosterUrl(string $publicId, int $w = 640): string
    {
        return sprintf(
            'https://res.cloudinary.com/%s/video/upload/so_0,c_fill,w_%d,h_%d,q_auto,f_jpg/%s.jpg',
            rawurlencode(self::cloudName()),
            $w,
            (int) round($w * 3 / 4),
            $publicId
        );
    }

    /* ---- bas niveau ----------------------------------------------- */

    private static function request(string $method, string $url, array $post = []): ?string
    {
        try {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERPWD        => self::apiKey() . ':' . self::apiSecret(),
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
            }
            curl_setopt_array($ch, $opts);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err    = curl_error($ch);
            curl_close($ch);

            if ($status >= 200 && $status < 300 && is_string($body)) {
                return $body;
            }
            self::$lastError = 'HTTP ' . $status . ($err !== '' ? ' — ' . $err : '');
            log_message('warning', 'cloudinary ' . $method . ' failed', ['status' => $status, 'url' => $url]);
            return null;
        } catch (\Throwable $e) {
            self::$lastError = 'exception — ' . $e->getMessage();
            log_message('error', 'cloudinary exception: ' . $e->getMessage());
            return null;
        }
    }
}
