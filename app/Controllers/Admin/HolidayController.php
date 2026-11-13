<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessException;
use App\Models\HolidayModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class HolidayController extends BaseController
{
    public function index(): string
    {
        $holidays = service('holidays');
        $years    = $holidays->filterYears();

        // Filter dari query string, multi-pilih (year[]=2026&month[]=1). Nilai di luar pilihan dibuang;
        // tanpa tahun valid = tahun berjalan, tanpa bulan = semua bulan.
        $selectedYears = array_values(array_intersect($years, array_map('intval', (array) $this->request->getGet('year'))));
        $selectedYears = $selectedYears === [] ? [(int) date('Y')] : $selectedYears;

        $selectedMonths = array_values(array_filter(
            array_map('intval', (array) $this->request->getGet('month')),
            static fn (int $month): bool => isset(HolidayModel::MONTHS[$month]),
        ));

        $keyword = trim((string) $this->request->getGet('q'));
        $result  = $holidays->paginateFiltered($selectedYears, $selectedMonths, $keyword);

        return view('admin/holidays/index', [
            'title'          => lang('Admin.pages.holidays.title'),
            'years'          => $years,
            'selectedYears'  => $selectedYears,
            'selectedMonths' => $selectedMonths,
            'keyword'        => $keyword,
            'holidays'       => $result['holidays'],
            'pager'          => $result['pager'],
            'offDayNums'     => $holidays->offDayNumbers(),
        ]);
    }

    public function new(): string
    {
        return view('admin/holidays/new', [
            'title'   => lang('Admin.holiday.createTitle'),
            'holiday' => ['holiday_date' => '', 'name' => '', 'description' => null, 'is_mass_leave' => 0],
        ]);
    }

    public function create(): RedirectResponse
    {
        if (! $this->validate('holiday')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('holidays')->create($this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.holidays.index'))->with('success', lang('Admin.holiday.created'));
    }

    public function edit(string $id): string
    {
        return view('admin/holidays/edit', [
            'title'   => lang('Admin.holiday.editTitle'),
            'holiday' => $this->findOrFail($id),
        ]);
    }

    public function update(string $id): RedirectResponse
    {
        $holiday = $this->findOrFail($id);

        if (! $this->validate('holiday')) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            service('holidays')->update($holiday, $this->validator->getValidated());
        } catch (BusinessException $e) {
            return redirect()->back()->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.holidays.index'))->with('success', lang('Admin.holiday.updated'));
    }

    public function delete(string $id): RedirectResponse
    {
        service('holidays')->delete($this->findOrFail($id));

        return redirect()->to(url_to('admin.holidays.index'))->with('success', lang('Admin.holiday.deleted'));
    }

    public function sync(): RedirectResponse
    {
        try {
            $total = service('holidays')->sync((int) date('Y'));
        } catch (BusinessException $e) {
            return redirect()->to(url_to('admin.holidays.index'))->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.holidays.index'))->with('success', lang('Admin.holiday.synced', [$total]));
    }

    public function updateOffDays(): RedirectResponse
    {
        if (! $this->validate('offDays')) {
            return redirect()->to(url_to('admin.holidays.index'))->with('danger', implode(' ', $this->validator->getErrors()));
        }

        try {
            service('holidays')->updateOffDays($this->request->getPost('off_days') ?? []);
        } catch (BusinessException $e) {
            return redirect()->to(url_to('admin.holidays.index'))->with('danger', $e->getMessage());
        }

        return redirect()->to(url_to('admin.holidays.index'))->with('success', lang('Admin.holiday.offDaysSaved'));
    }

    private function findOrFail(string $id): array
    {
        return service('holidays')->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }
}
