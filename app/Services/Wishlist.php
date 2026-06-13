<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Liste de souhaits (favoris) — côté visiteur, par cookie first-party (uniquement
 * des identifiants publics de produits). Fonctionne connecté ou non, sans base.
 */
final class Wishlist
{
    private const COOKIE = 'afk_wish';
    private const MAX    = 100;
    private const PID_RX = '/^[A-Za-z0-9\-]{8,40}$/';

    /** @return list<string> */
    public static function ids(): array
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($raw === '') {
            return [];
        }
        $ids = array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $s): bool => $s !== '' && preg_match(self::PID_RX, $s) === 1
        );
        return array_slice(array_values(array_unique($ids)), 0, self::MAX);
    }

    public static function has(string $pid): bool
    {
        return in_array(trim($pid), self::ids(), true);
    }

    public static function count(): int
    {
        return count(self::ids());
    }

    /** Bascule un produit ; renvoie true s'il est désormais en favori. */
    public static function toggle(string $pid): bool
    {
        $pid = trim($pid);
        if ($pid === '' || preg_match(self::PID_RX, $pid) !== 1) {
            return false;
        }
        $ids = self::ids();
        $idx = array_search($pid, $ids, true);
        if ($idx !== false) {
            unset($ids[$idx]);
            $now = false;
        } else {
            array_unshift($ids, $pid);
            $now = true;
        }
        $ids   = array_slice(array_values(array_unique($ids)), 0, self::MAX);
        $value = implode(',', $ids);
        @setcookie(self::COOKIE, $value, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
        return $now;
    }
}
