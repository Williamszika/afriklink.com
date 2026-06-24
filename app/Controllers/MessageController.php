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
        Conversation::post((int) $conv['id'], $uid, $body);
        $this->notify($sellerId, $uid, (string) $conv['public_id'], $body);
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
            if ($listing !== null) {
                $subject = (string) ($listing['title'] ?? '');
                $back    = '/annonce/' . $listingPid;
            }
        }

        if ($targetId === $uid) {
            flash('error', t('msg.err_self'));
            redirect($back);
        }
        $body = trim((string) input_string('body', ''));
        if (mb_strlen($body) < 2) {
            flash('error', t('msg.err_empty'));
            redirect($back);
        }
        $conv = Conversation::findOrCreateDirect($uid, $targetId, $subject);
        Conversation::post((int) $conv['id'], $uid, $body);
        $this->notify($targetId, $uid, (string) $conv['public_id'], $body);
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
        $body = trim((string) input_string('body', ''));
        if (mb_strlen($body) >= 2) {
            Conversation::post((int) $conv['id'], $uid, $body);
            $other = (int) $conv['buyer_id'] === $uid ? (int) $conv['seller_id'] : (int) $conv['buyer_id'];
            $this->notify($other, $uid, (string) $conv['public_id'], $body);
        } else {
            flash('error', t('msg.err_empty'));
        }
        redirect('/messages/' . $conv['public_id']);
    }

    /** Prévient le destinataire : notification (cloche) + e-mail. Ne bloque jamais l'envoi. */
    private function notify(int $recipientId, int $senderId, string $convPublicId, string $body): void
    {
        $from = User::findById($senderId) ?? [];
        $name = Conversation::displayName($from['full_name'] ?? null, $from['nickname'] ?? null);
        \App\Models\Notification::push($recipientId, 'message', t('notif.msg', ['name' => $name]), $body, '/messages/' . $convPublicId);
        try {
            $to    = User::findById($recipientId) ?? [];
            $email = trim((string) ($to['email'] ?? ''));
            if ($email === '') {
                return;
            }
            $link = url('/messages/' . $convPublicId);
            $html = '<p>' . e(t('msg.mail_intro', ['name' => $name])) . '</p>'
                . '<blockquote style="border-left:3px solid #ccc;padding-left:10px;color:#444">'
                . nl2br(e(mb_substr($body, 0, 300))) . '</blockquote>'
                . '<p><a href="' . e($link) . '">' . e(t('msg.mail_cta')) . '</a></p>';
            MailService::send($email, t('msg.mail_subject'), $html);
        } catch (\Throwable) {
        }
    }
}
