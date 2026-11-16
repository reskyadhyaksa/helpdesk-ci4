<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ComponentHelperTest extends CIUnitTestCase
{
    // Regresi: data view dari controller/komponen sebelumnya tidak boleh bocor ke komponen berikutnya
    public function testComponentDataIsIsolated(): void
    {
        view('components/page_header', ['title' => 'Judul Halaman', 'subtitle' => 'x']);

        $html = component('empty_state', ['icon' => 'fa-ticket']);

        $this->assertStringContainsString(lang('Admin.placeholderTitle'), $html);
        $this->assertStringNotContainsString('Judul Halaman', $html);
    }

    public function testDataTableRendersRowsFromSlot(): void
    {
        component_start('data_table', ['columns' => ['Nama', ['label' => 'Aksi']]]);
        echo '<tr><td>Budi</td><td>-</td></tr>';
        $html = component_end();

        $this->assertStringContainsString('<th scope="col" class="has-text-centered">Nama</th>', $html);
        $this->assertStringContainsString('class="has-text-centered">Aksi</th>', $html);
        $this->assertStringNotContainsString('is-fixed', $html);
        $this->assertStringContainsString('<tr><td>Budi</td>', $html);
        $this->assertStringContainsString('is-hoverable', $html);
        $this->assertStringNotContainsString('empty-state', $html);
    }

    public function testDataTableWithWidthsOnAllColumnsLocksLayoutAndSumsMinWidth(): void
    {
        $html = component('data_table', ['columns' => [['label' => 'A', 'width' => 10], ['label' => 'B', 'width' => 6.5]]]);

        $this->assertStringContainsString('is-fixed', $html);
        $this->assertStringContainsString('min-width: 16.5rem', $html);
        $this->assertStringContainsString('style="width: 10rem"', $html);
    }

    public function testDataTableWithoutRowsShowsEmptyState(): void
    {
        $html = component('data_table', ['columns' => ['A', 'B', 'C'], 'emptyIcon' => 'fa-users', 'emptyTitle' => 'Kosong']);

        $this->assertStringContainsString('colspan="3"', $html);
        $this->assertStringContainsString('fa-users', $html);
        $this->assertStringContainsString('Kosong', $html);
    }

    public function testModalWithFormAndFooterSlot(): void
    {
        component_start('modal', [
            'id'    => 'roleModal',
            'title' => 'Ubah Role',
            'form'  => ['action' => '/simpan', 'attributes' => ['data-role-form' => '']],
        ]);
        echo '<p>Isi modal</p>';
        component_slot('footer');
        echo '<button>Simpan</button>';
        $html = component_end();

        $this->assertStringContainsString('<form class="modal-card" role="dialog" aria-modal="true" aria-labelledby="roleModalTitle" method="post" action="&#x2F;simpan" data-role-form="">', $html);
        $this->assertStringContainsString('name="' . csrf_token() . '"', $html);
        $this->assertStringContainsString('id="roleModalTitle">Ubah Role</h2>', $html);
        $this->assertStringContainsString('<p>Isi modal</p>', $html);
        $this->assertStringContainsString('<footer class="modal-card-foot">', $html);
        $this->assertStringContainsString('<button>Simpan</button>', $html);
    }

    public function testNestedComponentsKeepTheirOwnSlots(): void
    {
        component_start('modal', ['id' => 'outer', 'title' => 'Luar']);
        component_start('data_table', ['columns' => ['Kolom']]);
        echo '<tr><td>Dalam</td></tr>';
        echo component_end();
        $html = component_end();

        $this->assertStringContainsString('<tr><td>Dalam</td></tr>', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('modal-card-foot', $html);
    }
}
