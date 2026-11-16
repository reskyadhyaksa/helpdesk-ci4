<?php

namespace App\Controllers;

class HomeController extends BaseController
{
    // Kartu "Kategori Layanan Populer" yang ditampilkan
    private const SERVICE_LIMIT = 8;

    public function index(): string
    {
        return view('home/index', [
            'title'    => 'Beranda',
            'services' => service('requestCategories')->popularServices(self::SERVICE_LIMIT),
        ]);
    }
}
