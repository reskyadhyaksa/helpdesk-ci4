<?php

namespace App\Entities;

/**
 * Status tiket dan alur perpindahannya (satu-satunya tempat aturan transisi ditulis).
 * RESOLVED = ditandai PIC, menunggu konfirmasi; CLOSED dan CANCELLED bersifat final.
 */
enum TicketStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case NeedResponse = 'NEED_RESPONSE';
    case OnHold = 'ON_HOLD';
    case Escalated = 'ESCALATED';
    case Resolved = 'RESOLVED';
    case Reopened = 'REOPENED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::NeedResponse, self::OnHold, self::Escalated, self::Resolved],
            self::NeedResponse => [self::InProgress, self::Resolved, self::Cancelled],
            self::OnHold => [self::InProgress, self::Escalated],
            self::Escalated => [self::InProgress, self::OnHold, self::Resolved],
            self::Resolved => [self::Closed, self::Reopened],
            self::Reopened => [self::InProgress, self::Escalated, self::Resolved],
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
     * Pilihan untuk dropdown filter: nilai => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
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

    /**
     * Kolom papan Kanban di daftar tiket: beberapa status berdekatan digabung supaya papan tidak terlalu lebar.
     * tone = warna Bulma untuk titik penanda kolom.
     *
     * @return array<string, array{label: string, tone: string, statuses: list<string>}>
     */
    public static function boardColumns(): array
    {
        return [
            'open' => ['label' => 'Open', 'tone' => 'info', 'statuses' => [self::Open->value, self::Reopened->value]],
            'progress' => ['label' => 'In Progress', 'tone' => 'link', 'statuses' => [self::InProgress->value]],
            'waiting' => ['label' => 'Waiting', 'tone' => 'warning', 'statuses' => [self::NeedResponse->value, self::OnHold->value]],
            'escalated' => ['label' => 'Escalated', 'tone' => 'danger', 'statuses' => [self::Escalated->value]],
            'resolved' => ['label' => 'Resolved', 'tone' => 'success', 'statuses' => [self::Resolved->value]],
            'closed' => ['label' => 'Closed', 'tone' => 'grey', 'statuses' => [self::Closed->value, self::Cancelled->value]],
        ];
    }

    public function label(): string
    {
        return lang('Ticket.status.' . $this->value);
    }

    public function tagClass(): string
    {
        return match ($this) {
            self::Open => 'is-info',
            self::InProgress => 'is-link',
            self::NeedResponse, self::OnHold, self::Reopened => 'is-warning',
            self::Escalated => 'is-danger',
            self::Resolved => 'is-success',
            self::Closed, self::Cancelled => 'is-light',
        };
    }
}
