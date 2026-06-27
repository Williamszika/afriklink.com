<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Levée quand le stock d'une ligne devient insuffisant PENDANT la création de la
 * commande (course avec une vente concurrente). Permet à la caisse d'afficher un
 * message clair « article épuisé » au lieu de survendre — et garantit que toute
 * commande créée a bien décrémenté son stock (la restauration à l'annulation
 * reste donc exacte).
 */
final class OutOfStockException extends \RuntimeException
{
}
