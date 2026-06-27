<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Request;
use App\Services\MailService;

/**
 * Messagerie acheteur ↔ vendeur (boîte unifiée /messages). Tout participant
 * connecté voit ses conversations et y répond ; le destinataire est prévenu
 * par e-mail (best-effort).
 */
final class MessageController
{
    public function inbox(Request $request): void
    {
        $uid = (int) current_user_id();
        view('messages/inbox', [
            'page_title'    => t('msg.title'),
            'uid'           => $uid,
            'conversations' => Conversation::forUser($uid),
        ]);
    }

    public function thread(Request $request): void
    {
        $uid  = (int) current_user_id();
        $conv = Conversation::findByPublicId((string) $request->param('id', ''));
        if ($conv === null || !Conversation::isParticipant($conv, $uid)) {
            abort(404);
        }
        Conversation::markRead($conv, $uid);
        $otherId   = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
        $otherUser = User::findById($otherId) ?? [];
        view('messages/thread', [
            'page_title' => t('msg.title'),
            'uid'        => $uid,
            'conv'       => $conv,
            'messages'   => Conversation::messages((int) $conv['id']),
            'other_name' => Conversation::displayName($otherUser['full_name'] ?? null, $otherUser['nickname'] ?? null),
            // État de blocage : 'i_blocked' = j'ai bloqué l'autre (je peux
            // débloquer) ; 'blocked' = blocage dans un sens ou l'autre (pas
            // d'envoi possible).
            'i_blocked'  => \App\Models\UserBlock::has($uid, $otherId),
            'blocked'    => \App\Models\UserBlock::between($uid, $otherId),
        ]);
    }

    /** L'acheteur démarre une conversation depuis une fiche produit / vitrine. */
    public function start(Request $request): void
    {
        $uid      = (int) current_user_id();
        $boutique = Boutique::findBySlug((string) input_string('slug', ''));
        if ($boutique === null || $boutique['status'] !== 'published') {
            abort(404);
        }
        $sellerId = (int) $boutique['user_id'];
        $back     = '/boutique/' . $boutique['slug'];
        if ($sellerId === $uid) {
            flash('error', t('msg.err_self'));
            redirect($back);
        }
        if (\App\Models\UserBlock::between($uid, $sellerId)) {
            flash('error', t('msg.err_blocked'));
            redirect($back);
        }

        $productId = null;
        $subject   = (string) $boutique['name'];
        $pid       = (string) input_string('product', '');
        if ($pid !== '') {
            $product = Product::findByPublicId($pid);
            if ($product !== null && (int) $product['boutique_id'] === (int) $boutique['id']) {
                $productId = (int) $product['id'];
                $subject   = (string) $product['name'];
                $back      = '/boutique/' . $boutique['slug'] . '/p/' . $product['public_id'];
            }
        }

        $body = trim((string) input_string('body', ''));
        if (mb_strlen($body) < 2) {
            flash('error', t('msg.err_empty'));
            redirect($back);
        }
        $conv = Conversation::findOrCreate($uid, $sellerId, (int) $boutique['id'], $productId, $subject);
        if (empty($conv['id'])) { // création impossible (panne base) : pas de message orphelin
            flash('error', t('msg.err_failed'));
            redirect($back);
        }
        Conversation::post((int) $conv['id'], $uid, $body);
        $this->notify($sellerId, $uid, (string) $conv['public_id']);
        flash('success', t('msg.sent'));
        redirect('/messages/' . $conv['public_id']);
    }

    /**
     * Messagerie D'UTILISATEUR À UTILISATEUR : un membre écrit directement à un
     * autre (p.ex. depuis une annonce). Conversation directe, hors boutique.
     * Texte uniquement ; le destinataire est prévenu (cloche + e-mail).
     */
    public function contactUser(Request $request): void
    {
        $uid    = (int) current_user_id();
        $target = User::findByPublicId((string) input_string('to', ''));
        if ($target === null) {
            abort(404);
        }
        $targetId = (int) $target['id'];

        // Contexte facultatif : prise de contact depuis une annonce (sujet + retour).
        $subject = null;
        $back    = '/messages';
        $listingPid = (string) input_string('listing', '');
        if ($listingPid !== '') {
            $listing = \App\Models\Listing::findByPublicId($listingPid);
            // Le contexte « annonce » n'est retenu que si l'annonce appartient
            // bien à la personne contactée — sinon n'importe quel pid pourrait
            // imposer un sujet/retour trompeur (annonce d'autrui).
            if ($listing !== null && (int) ($listing['user_id'] ?? 0) === $targetId) {
                $subject = (string) ($listing['title'] ?? '');
                $back    = '/annonce/' . $listingPid;
            }
        }

        if ($targetId === $uid) {
            flash('error', t('msg.err_self'));
            redirect($back);
        }
        if (\App\Models\UserBlock::between($uid, $targetId)) {
            flash('error', t('msg.err_blocked'));
            redirect($back);
        }
        $body = trim((string) input_string('body', ''));
        if (mb_strlen($body) < 2) {
            flash('error', t('msg.err_empty'));
            redirect($back);
        }
        $conv = Conversation::findOrCreateDirect($uid, $targetId, $subject);
        if (empty($conv['id'])) {
            flash('error', t('msg.err_failed'));
            redirect($back);
        }
        Conversation::post((int) $conv['id'], $uid, $body);
        $this->notify($targetId, $uid, (string) $conv['public_id']);
        flash('success', t('msg.sent'));
        redirect('/messages/' . $conv['public_id']);
    }

    public function reply(Request $request): void
    {
        $uid  = (int) current_user_id();
        $conv = Conversation::findByPublicId((string) $request->param('id', ''));
        if ($conv === null || !Conversation::isParticipant($conv, $uid)) {
            abort(404);
        }
        $other = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
        if (\App\Models\UserBlock::between($uid, $other)) {
            flash('error', t('msg.err_blocked'));
            redirect('/messages/' . $conv['public_id']);
        }
        $body = trim((string) input_string('body', ''));
        if (mb_strlen($body) >= 2) {
            Conversation::post((int) $conv['id'], $uid, $body);
            $this->notify($other, $uid, (string) $conv['public_id']);
        } else {
            flash('error', t('msg.err_empty'));
        }
        redirect('/messages/' . $conv['public_id']);
    }

    /** Bloque ou débloque l'autre membre d'une conversation (anti-harcèlement). */
    public function block(Request $request): void
    {
        $uid  = (int) current_user_id();
        $conv = Conversation::findByPublicId((string) $request->param('id', ''));
        if ($conv === null || !Conversation::isParticipant($conv, $uid)) {
            abort(404);
        }
        $other  = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
        $action = whitelist((string) input_string('action', 'block'), ['block', 'unblock'], 'block');
        if ($action === 'unblock') {
            \App\Models\UserBlock::unblock($uid, $other);
            flash('success', t('msg.unblock_done'));
        } else {
            \App\Models\UserBlock::block($uid, $other);
            flash('success', t('msg.block_done'));
        }
        redirect('/messages/' . $conv['public_id']);
    }

    /** Prévient le destinataire : notification (cloche) + e-mail. Ne bloque jamais l'envoi. */
    private function notify(int $recipientId, int $senderId, string $convPublicId): void
    {
        $from = User::findById($senderId) ?? [];
        $name = Conversation::displayName($from['full_name'] ?? null, $from['nickname'] ?? null);
        // Le contenu du message ne quitte JAMAIS la table chiffrée : la
        // notification (cloche) et l'e-mail invitent seulement à ouvrir la
        // discussion — aucun extrait en clair stocké/envoyé.
        \App\Models\Notification::push($recipientId, 'message', t('notif.msg', ['name' => $name]), '', '/messages/' . $convPublicId);
        try {
            $to    = User::findById($recipientId) ?? [];
            $email = trim((string) ($to['email'] ?? ''));
            if ($email === '') {
                return;
            }
            $link = url('/messages/' . $convPublicId);
            $html = '<p>' . e(t('msg.mail_intro', ['name' => $name])) . '</p>'
                . '<p><a href="' . e($link) . '">' . e(t('msg.mail_cta')) . '</a></p>';
            MailService::send($email, t('msg.mail_subject'), $html);
        } catch (\Throwable) {
        }
    }
}
