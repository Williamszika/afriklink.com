<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Registre des fournisseurs d'encaissement. Point d'entrée unique pour
 * obtenir un fournisseur, lister ceux qui sont configurés, et diagnostiquer.
 */
final class PaymentProviders
{
    /** @return array<string,PaymentProvider> */
    public static function all(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'simulation' => new SimulationProvider(),
                'cinetpay'   => new CinetPayProvider(),
                'stripe'     => new StripeProvider(),
                'paypal'     => new PayPalProvider(),
            ];
        }
        return $map;
    }

    public static function get(string $key): ?PaymentProvider
    {
        return self::all()[$key] ?? null;
    }

    /** Fournisseur utilisable pour une clé donnée, sinon repli sur la simulation. */
    public static function resolve(?string $key): PaymentProvider
    {
        $p = $key !== null ? self::get($key) : null;
        if ($p !== null && $p->isConfigured()) {
            return $p;
        }
        return self::all()['simulation'];
    }

    /** @return array<string,PaymentProvider> fournisseurs aux clés présentes */
    public static function configured(): array
    {
        return array_filter(self::all(), static fn (PaymentProvider $p): bool => $p->isConfigured());
    }

    /** Statut de chaque fournisseur pour /health. @return array<string,string> */
    public static function diagnostic(): array
    {
        $out = [];
        foreach (self::all() as $key => $p) {
            $out[$key] = $p->isConfigured() ? 'ready' : 'unconfigured';
        }
        return $out;
    }
}
