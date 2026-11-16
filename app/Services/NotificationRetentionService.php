<?php

namespace App\Services;

use App\Models\SystemParameterModel;
use App\Models\UserNotificationModel;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Retensi notifikasi lonceng: notifikasi yang sudah dibaca lebih dari NOTIFICATION_RETENTION_DAYS hari dihapus
 * supaya tabel user_notifications tidak terus membesar. Yang belum dibaca tidak pernah dihapus.
 * Dijalankan harian lewat `php spark notifications:purge`.
 */
class NotificationRetentionService
{
    public function __construct(
        private readonly UserNotificationModel $notifications,
        private readonly SystemParameterModel $parameters,
    ) {
    }

    /**
     * @return int jumlah notifikasi yang dihapus dalam proses ini
     */
    public function purge(?DateTimeImmutable $now = null): int
    {
        $days = $this->parameters->int(SystemParameterModel::NOTIFICATION_RETENTION_DAYS);

        // 0 (atau kurang) = retensi dimatikan: tidak ada yang pernah dihapus
        if ($days <= 0) {
            return 0;
        }

        $now        = $now ?? new DateTimeImmutable('now', new DateTimeZone(app_timezone()));
        $readBefore = $now->sub(new DateInterval("P{$days}D"))->format('Y-m-d H:i:s');

        return $this->notifications->deleteReadBefore($readBefore);
    }
}
