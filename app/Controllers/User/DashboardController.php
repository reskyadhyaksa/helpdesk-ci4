<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Entities\TicketStatus;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $tickets = service('tickets');
        $viewer  = auth_user();

        $filters = [
            'keyword'  => trim((string) $this->request->getGet('q')),
            'status'   => $this->listParam('status'),
            'category' => $this->listParam('category'),
        ];

        $statusOptions = [];

        foreach (TicketStatus::cases() as $status) {
            $statusOptions[$status->value] = $status->label();
        }

        return view('user/dashboard', [
            'title'           => lang('Ticket.dashboard.title'),
            'summary'         => $tickets->statusSummary($viewer),
            'tickets'         => $tickets->recentFor($viewer, $filters),
            'slaStandards'    => $tickets->slaStandards(),
            'filters'         => $filters,
            'statusOptions'   => $statusOptions,
            'categoryOptions' => array_column(service('requestCategories')->topLevel(), 'name', 'id'),
        ]);
    }

    /**
     * Filter multi-pilih dari query string (status[]=A&status[]=B); nilai kosong diabaikan.
     *
     * @return list<string>
     */
    private function listParam(string $name): array
    {
        $values = (array) $this->request->getGet($name);

        return array_values(array_filter(array_map('strval', $values), static fn (string $value): bool => $value !== ''));
    }
}
