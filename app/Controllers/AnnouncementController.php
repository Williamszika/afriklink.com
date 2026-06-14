<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Request;
use App\Services\AuditLog;
use App\Services\NewsTicker;

/**
 * Annonces « À la une » du staff. Admins & modérateurs peuvent en rédiger ;
 * celles des modérateurs passent en attente et ne sont diffusées qu'après
 * APPROBATION d'un admin. Page publique d'article en /info/{public_id}.
 *
 * Back-office sous StaffMiddleware (admin OU modérateur) ; l'approbation est
 * en plus réservée aux admins (vérifiée ici).
 */
final class AnnouncementController
{
    /** Back-office : liste + formulaire de rédaction. */
    public function index(Request $request): void
    {
        $admin = is_admin();
        // Admin : toutes les annonces ; modérateur : seulement les siennes.
        $list = Announcement::listFor($admin ? null : current_user_id());
        view('admin/announcements', [
            'list'      => $list,
            'is_admin'  => $admin,
            'pending'   => $admin ? Announcement::pendingCount() : 0,
            'page_title' => t('ann.admin_title'),
        ]);
    }

    /** Crée une annonce : approuvée si admin, en attente si modérateur. */
    public function store(Request $request): void
    {
        $title = trim((string) input_string('title', ''));
        $body  = trim((string) input_string('body', ''));
        $link  = trim((string) input_string('link', ''));

        $errors = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            $errors['title'] = t('ann.err_title');
        }
        if ($body !== '' && mb_strlen($body) > 5000) {
            $errors['body'] = t('ann.err_body');
        }
        // Lien externe optionnel : http/https uniquement (jamais javascript:, etc.).
        if ($link !== '') {
            $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
            if (filter_var($link, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                $errors['link'] = t('ann.err_link');
            }
        }
        if ($errors !== []) {
            keep_old($_POST);
            set_errors($errors);
            redirect('/admin/annonces');
        }

        $admin = is_admin();
        $pid = Announcement::create((int) current_user_id(), $title, $body !== '' ? $body : null, $link !== '' ? $link : null, $admin);
        NewsTicker::bustCache(); // l'annonce approuvée doit apparaître tout de suite
        AuditLog::record((int) current_user_id(), 'announcement.created', 'announcement', null, ['status' => $admin ? 'approved' : 'pending'], $request->ipBinary());

        clear_old();
        flash('success', $admin ? t('ann.created_live') : t('ann.created_pending'));
        redirect('/admin/annonces');
    }

    /** Approuve ou rejette une annonce — ADMINS uniquement. */
    public function review(Request $request): void
    {
        if (!is_admin()) {
            flash('error', t('ann.only_admin'));
            redirect('/admin/annonces');
        }
        $id     = (int) $request->param('id', '0');
        $action = whitelist((string) input_string('action', ''), ['approve', 'reject'], null);
        $ann    = Announcement::findById($id);
        if ($ann === null || $action === null) {
            abort(404);
        }
        Announcement::review($id, (int) current_user_id(), $action === 'approve');
        NewsTicker::bustCache();
        AuditLog::record((int) current_user_id(), 'announcement.' . $action . 'd', 'announcement', $id, [], $request->ipBinary());
        flash('success', t($action === 'approve' ? 'ann.approved' : 'ann.rejected'));
        redirect('/admin/annonces');
    }

    /** Supprime une annonce : admin (toutes) ou l'auteur (les siennes). */
    public function destroy(Request $request): void
    {
        $id  = (int) $request->param('id', '0');
        $ann = Announcement::findById($id);
        if ($ann === null) {
            abort(404);
        }
        if (!is_admin() && (int) $ann['author_user_id'] !== (int) current_user_id()) {
            flash('error', t('ann.only_admin'));
            redirect('/admin/annonces');
        }
        Announcement::delete($id);
        NewsTicker::bustCache();
        flash('success', t('ann.deleted'));
        redirect('/admin/annonces');
    }

    /** Page publique de l'article (annonce approuvée uniquement). */
    public function show(Request $request): void
    {
        $ann = Announcement::findPublic((string) $request->param('slug', ''));
        if ($ann === null) {
            abort(404);
        }
        $author = User::findById((int) $ann['author_user_id']);
        view('announcement/show', [
            'ann'         => $ann,
            'author_name' => (string) ($author['full_name'] ?? ''),
            'page_title'  => (string) $ann['title'],
        ]);
    }
}
