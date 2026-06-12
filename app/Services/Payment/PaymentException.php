<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Erreur d'encaissement (fournisseur non configuré, refus, indisponible…). */
final class PaymentException extends \RuntimeException
{
}
