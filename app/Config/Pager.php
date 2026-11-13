<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\HTTP\IncomingRequest;

class Pager extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Templates
     * --------------------------------------------------------------------------
     *
     * Pagination links are rendered out using views to configure their
     * appearance. This array contains aliases and the view names to
     * use when rendering the links.
     *
     * Within each view, the Pager object will be available as $pager,
     * and the desired group as $pagerGroup;
     *
     * @var array<string, string>
     */
    public array $templates = [
        'default_full'   => 'CodeIgniter\Pager\Views\default_full',
        'default_simple' => 'CodeIgniter\Pager\Views\default_simple',
        'default_head'   => 'CodeIgniter\Pager\Views\default_head',
        'bulma'          => 'App\Views\pagers\bulma',
    ];

    /**
     * --------------------------------------------------------------------------
     * Items Per Page
     * --------------------------------------------------------------------------
     *
     * Jumlah baris per halaman untuk semua tabel admin (paginate() tanpa argumen). Default 10; pengguna bisa
     * memilih salah satu PER_PAGE_OPTIONS lewat ?per_page=… (dropdown di footer components/data_table).
     */
    public const DEFAULT_PER_PAGE = 10;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public int $perPage = self::DEFAULT_PER_PAGE;

    public function __construct()
    {
        parent::__construct();

        // Satu tempat membaca pilihan pengguna, jadi semua model yang memanggil paginate() ikut tanpa perubahan
        $request = service('request');

        if (! $request instanceof IncomingRequest) {
            return;
        }

        $requested = (int) $request->getGet('per_page');

        if (in_array($requested, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = $requested;
        }
    }
}
