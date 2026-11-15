<?php

namespace App\Services;

use App\Models\SystemParameterModel;
use App\Models\TicketAttachmentModel;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Retensi lampiran: file lampiran tiket yang sudah CLOSED lebih dari ATTACHMENT_RETENTION_DAYS hari dihapus dari disk.
 * Baris lampiran dipertahankan dengan purged_at supaya riwayat tetap menunjukkan bahwa lampiran pernah ada.
 * Dijalankan harian lewat `php spark attachments:purge`.
 */
class AttachmentRetentionService
{
    public function __construct(
        private readonly TicketAttachmentModel $attachments,
        private readonly AttachmentService $files,
        private readonly SystemParameterModel $parameters,
    ) {
    }

    /**
     * @return int jumlah lampiran yang dihapus dalam proses ini
     */
    public function purge(?DateTimeImmutable $now = null, int $limit = 500): int
    {
        $days = $this->parameters->int(SystemParameterModel::ATTACHMENT_RETENTION_DAYS);

        // 0 (atau kurang) = retensi dimatikan: tidak ada yang pernah dihapus
        if ($days <= 0) {
            return 0;
        }

        $now          = $now ?? new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
        $closedBefore = $now->sub(new DateInterval("P{$days}D"))->format('Y-m-d H:i:s');
        $purged       = 0;

        foreach ($this->attachments->dueForPurge($closedBefore, $limit) as $attachment) {
            if ($this->purgeOne($attachment, $now)) {
                $purged++;
            }
        }

        return $purged;
    }

    /**
     * Satu lampiran yang gagal (file terkunci, izin ditolak, database) hanya dilewati dan dicatat; tanpa ini baris yang
     * sama selalu urutan pertama dan menghentikan seluruh proses harian, sehingga lampiran lain tidak pernah dihapus.
     */
    private function purgeOne(array $attachment, DateTimeImmutable $now): bool
    {
        try {
            // Hanya ditandai bila file benar-benar sudah tidak ada; yang gagal dicoba lagi di proses berikutnya
            if (! $this->files->remove($attachment)) {
                return false;
            }

            $this->attachments->markPurged($attachment['id'], $now->format('Y-m-d H:i:s'));

            return true;
        } catch (Throwable $e) {
            log_message('error', 'Lampiran {id} gagal dihapus oleh retensi: {msg}', ['id' => $attachment['id'], 'msg' => $e->getMessage()]);

            return false;
        }
    }
}
