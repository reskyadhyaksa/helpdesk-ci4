<?php

namespace App\Services;

use App\Models\SystemParameterModel;
use App\Models\TicketModel;
use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Peringatan SLA: memberi tahu PIC saat batas respons/penyelesaian hampir tercapai (SLA_WARNING_MINUTES sebelumnya)
 * dan saat terlewat. Dijalankan berkala lewat `php spark sla:check` (tiap menit).
 */
class SlaAlertService
{
    private const KINDS = [
        InAppNotificationService::SLA_RESPONSE_WARNING,
        InAppNotificationService::SLA_RESPONSE_BREACHED,
        InAppNotificationService::SLA_RESOLUTION_WARNING,
        InAppNotificationService::SLA_RESOLUTION_BREACHED,
    ];

    public function __construct(
        private readonly TicketModel $tickets,
        private readonly InAppNotificationService $inApp,
        private readonly NotificationPusher $pusher,
        private readonly SystemParameterModel $parameters,
    ) {
    }

    /**
     * @return array{warnings: int, breaches: int} jumlah tiket yang baru diperingatkan
     */
    public function run(?DateTimeImmutable $now = null): array
    {
        $now       = $now ?? new DateTimeImmutable('now', new \DateTimeZone(app_timezone()));
        $nowText   = $now->format('Y-m-d H:i:s');
        $windowEnd = $now->add(new DateInterval('PT' . max(1, $this->parameters->int(SystemParameterModel::SLA_WARNING_MINUTES)) . 'M'))->format('Y-m-d H:i:s');
        $oldestDue = $now->sub(new DateInterval('PT' . max(1, $this->parameters->int(SystemParameterModel::SLA_ALERT_MAX_AGE_HOURS)) . 'H'))->format('Y-m-d H:i:s');
        $result    = ['warnings' => 0, 'breaches' => 0];

        foreach (self::KINDS as $kind) {
            foreach ($this->tickets->dueForSla($kind, $nowText, $windowEnd, $oldestDue) as $ticket) {
                if ($this->alert($ticket, $kind, $nowText)) {
                    $result[str_ends_with($kind, 'breached') ? 'breaches' : 'warnings']++;
                }
            }
        }

        return $result;
    }

    private function alert(array $ticket, string $kind, string $now): bool
    {
        // Klaim dulu: proses lain yang berjalan bersamaan tidak akan membuat notifikasi yang sama
        if (! $this->tickets->claimSla($ticket['id'], $kind, $now)) {
            return false;
        }

        try {
            $rows = $this->inApp->forSla($ticket, $kind);
            $this->pusher->push($rows);
        } catch (Throwable $e) {
            log_message('error', 'Peringatan SLA tiket {id} gagal: {msg}', ['id' => $ticket['id'], 'msg' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
