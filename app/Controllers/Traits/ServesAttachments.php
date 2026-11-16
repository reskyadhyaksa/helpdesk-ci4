<?php

namespace App\Controllers\Traits;

use App\Services\AttachmentService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;

/**
 * Respons berkas lampiran tiket (unduh atau tampil di browser), dipakai controller tiket admin dan user.
 * Hak akses sudah diperiksa pemanggil lewat TicketCommentService::attachmentForDownload().
 */
trait ServesAttachments
{
    /**
     * @param array{path: string, name: string, type: string} $file
     */
    protected function attachmentResponse(array $file, bool $inline): DownloadResponse
    {
        $response = $this->response->download($file['path'], null)->setFileName($file['name'])->setHeader('X-Content-Type-Options', 'nosniff');

        if (! $inline) {
            return $response;
        }

        if (! AttachmentService::canPreview($file['type'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $response->inline()->setContentType($file['type']);

        // Gambar dan teks tidak butuh apa pun selain tampil: sandbox mencegah skrip dari berkas ini berjalan.
        // PDF dibiarkan agar penampil bawaan browser bisa dipakai.
        if ($file['type'] !== AttachmentService::TYPE_PDF) {
            $response->setHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        }

        return $response;
    }
}
