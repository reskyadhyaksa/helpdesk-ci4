<?php

namespace App\Entities;

/**
 * Status tiket dan alur perpindahannya (satu-satunya tempat aturan transisi ditulis).
 * RESOLVED = ditandai PIC, menunggu konfirmasi; CLOSED dan CANCELLED bersifat final.
 */
enum TicketStatus: string
{
    case Open          = 'OPEN';
    case InProgress    = 'IN_PROGRESS';
    case NeedResponse  = 'NEED_RESPONSE';
    case OnHold        = 'ON_HOLD';
    case Escalated     = 'ESCALATED';
    case Resolved      = 'RESOLVED';
    case Reopened      = 'REOPENED';
    case Closed        = 'CLOSED';
    case Cancelled     = 'CANCELLED';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open         => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress   => [self::NeedResponse, self::OnHold, self::Escalated, self::Resolved],
            self::NeedResponse => [self::InProgress, self::Resolved, self::Cancelled],
            self::OnHold       => [self::InProgress, self::Escalated],
            self::Escalated    => [self::InProgress, self::OnHold, self::Resolved],
            self::Resolved     => [self::Closed, self::Reopened],
            self::Reopened     => [self::InProgress, self::Escalated, self::Resolved],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Pengelompokan status untuk kartu ringkasan dashboard user. CANCELLED tidak dihitung.
     *
     * @return array<string, list<self>>
     */
    public static function dashboardGroups(): array
    {
        return [
            'open'          => [self::Open],
            'progress'      => [self::InProgress, self::OnHold, self::Escalated, self::Reopened],
            'need_response' => [self::NeedResponse],
            'done'          => [self::Resolved, self::Closed],
        ];
    }

    public function label(): string
    {
        return lang('Ticket.status.' . $this->value);
    }

    public function tagClass(): string
    {
        return match ($this) {
            self::Open         => 'is-info',
            self::InProgress   => 'is-link',
            self::NeedResponse, self::OnHold, self::Reopened => 'is-warning',
            self::Escalated    => 'is-danger',
            self::Resolved     => 'is-success',
            self::Closed, self::Cancelled => 'is-light',
        };
    }
}
