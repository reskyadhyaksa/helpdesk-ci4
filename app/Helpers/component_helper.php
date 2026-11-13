<?php

/*
 * Komponen view di app/Views/components/.
 *
 * Tanpa isi:   <?= component('empty_state', ['icon' => 'fa-ticket']) ?>
 *
 * Dengan isi (slot), mis. baris tabel atau isi modal:
 *   <?php component_start('modal', ['id' => 'roleModal', 'title' => 'Ubah Role']) ?>
 *       ...HTML isi → tersedia sebagai $slot di komponen...
 *   <?php component_slot('footer') ?>
 *       ...HTML tombol → tersedia sebagai $footer...
 *   <?= component_end() ?>
 */

if (! function_exists('component')) {
    /**
     * Render komponen dengan data terisolasi.
     *
     * view() biasa berbagi data dalam satu request: variabel controller (mis. $title) dan
     * variabel komponen sebelumnya ikut terbaca, sehingga nilai default opsional tertimpa.
     * Renderer terpisah memastikan komponen hanya melihat $data yang dikirim.
     */
    function component(string $name, array $data = []): string
    {
        return service('renderer', null, null, false)
            ->setData($data, 'raw')
            ->render('components/' . $name, null, false);
    }
}

if (! function_exists('component_slot_stack')) {
    /**
     * Tumpukan komponen yang sedang ditulis isinya (bisa bersarang, mis. tabel di dalam modal).
     * Dikembalikan by reference agar component_start/slot/end mengubah tumpukan yang sama.
     */
    function &component_slot_stack(): array
    {
        static $stack = [];

        return $stack;
    }
}

if (! function_exists('component_start')) {
    /**
     * Mulai menulis isi komponen. HTML sesudahnya ditangkap sebagai $slot.
     */
    function component_start(string $name, array $data = []): void
    {
        $stack   = &component_slot_stack();
        $stack[] = ['name' => $name, 'data' => $data, 'current' => 'slot', 'slots' => []];

        ob_start();
    }
}

if (! function_exists('component_slot')) {
    /**
     * Pindah ke slot bernama; HTML sesudahnya tersedia di komponen sebagai variabel $nama.
     */
    function component_slot(string $name): void
    {
        $stack = &component_slot_stack();
        $top   = array_key_last($stack);

        $stack[$top]['slots'][$stack[$top]['current']] = ob_get_clean();
        $stack[$top]['current']                         = $name;

        ob_start();
    }
}

if (! function_exists('component_end')) {
    /**
     * Selesai menulis isi; render komponen dengan data + semua slot.
     */
    function component_end(): string
    {
        $stack = &component_slot_stack();
        $entry = array_pop($stack);

        $entry['slots'][$entry['current']] = ob_get_clean();

        return component($entry['name'], array_merge($entry['data'], $entry['slots']));
    }
}
