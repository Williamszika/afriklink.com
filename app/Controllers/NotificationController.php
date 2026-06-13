<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use App\Request;

/** Centre de notifications (cloche) : liste, ouverture (marque lu + redirige), tout marquer lu. */
final class NotificationController
{
    public function index(Request $request): void
    {
        view('notifications/index', [
            'page_title'    => t('notif.title'),
            'notifications' => Notification::forUser((int) current_user_id()),
        ]);
    }

    public function open(Request $request): void
    {
        $row = Notification::markRead((int) $request->param('id', 0), (int) current_user_id());
        $to  = $row !== null ? trim((string) ($row['link'] ?? '')) : '';
        if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\x00-\x1f]/', $to)) {
            $to = '/notifications';
        }
        redirect(mb_substr($to, 0, 255));
    }

    public function markAll(Request $request): void
    {
        Notification::markAllRead((int) current_user_id());
        redirect('/notifications');
    }
}
