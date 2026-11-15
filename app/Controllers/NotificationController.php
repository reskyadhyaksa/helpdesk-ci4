<?php

namespace App\Controllers;

use App\Services\WsToken;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * API JSON untuk lonceng notifikasi. Semua endpoint hanya melayani pengguna yang login dan hanya data miliknya.
 * Setiap jawaban memuat token CSRF terbaru karena token diganti setelah tiap POST.
 */
class NotificationController extends BaseController
{
    public function index(): ResponseInterface
    {
        return $this->json(service('inAppNotifications')->listFor(auth_user()['id']));
    }

    public function read(string $id): ResponseInterface
    {
        $service = service('inAppNotifications');
        $userId  = auth_user()['id'];

        $service->markRead($id, $userId);

        return $this->json(['unread' => $service->unreadCount($userId)]);
    }

    public function readAll(): ResponseInterface
    {
        service('inAppNotifications')->markAllRead(auth_user()['id']);

        return $this->json(['unread' => 0]);
    }

    /**
     * Token singkat untuk menyambung ke server WebSocket.
     */
    public function token(): ResponseInterface
    {
        try {
            $token = (new WsToken())->issue(auth_user()['id']);
        } catch (RuntimeException) {
            return $this->json(['error' => 'WebSocket belum dikonfigurasi'], 503);
        }

        return $this->json(['token' => $token, 'url' => (string) env('WS_PUBLIC_URL', '')]);
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data + ['csrf' => csrf_hash()]);
    }
}
