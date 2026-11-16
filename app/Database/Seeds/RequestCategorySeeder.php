<?php

namespace App\Database\Seeds;

use App\Models\RequestCategoryModel;
use CodeIgniter\Database\Seeder;

/**
 * Kategori layanan awal (kategori utama + sub kategori). Tanpa SLA dan tanpa aturan akses: admin yang mengaturnya.
 * Aman dijalankan ulang: yang sudah ada (mungkin sudah diubah admin) tidak ditimpa.
 */
class RequestCategorySeeder extends Seeder
{
    private const CATEGORIES = [
        'Payroll' => [
            'description' => 'Layanan penggajian karyawan.',
            'subs'        => [
                'Slip Gaji'           => 'Slip gaji tidak muncul atau tidak sesuai.',
                'Selisih Gaji'        => 'Perbedaan nominal gaji, tunjangan, atau potongan.',
                'Pajak PPh 21'        => 'Bukti potong dan perhitungan PPh 21.',
                'Reimbursement'       => 'Klaim penggantian biaya yang belum dibayarkan.',
            ],
        ],
        'HCEazy' => [
            'description' => 'Aplikasi in-house absensi dan transaksi HC karyawan MTF.',
            'subs'        => [
                'Absensi'              => 'Kendala check-in/check-out atau koreksi absensi.',
                'Cuti dan Izin'        => 'Pengajuan, persetujuan, atau saldo cuti dan izin.',
                'Lembur'               => 'Pengajuan dan persetujuan lembur.',
                'Akses dan Akun'       => 'Login, reset kata sandi, atau akses menu HCEazy.',
                'Transaksi HC Lainnya' => 'Kendala transaksi HC karyawan lain di HCEazy.',
            ],
        ],
        'WebCareers' => [
            'description' => 'Portal lowongan kerja MTF.',
            'subs'        => [
                'Sinkronisasi FKP'     => 'Sinkron data FKP dari HCEazy untuk membuat lowongan.',
                'Publikasi Lowongan'   => 'Pembuatan, perubahan, atau penutupan lowongan.',
                'Akses dan Akun'       => 'Akses pengguna WebCareers.',
            ],
        ],
        'Salesforce' => [
            'description' => 'Aplikasi Salesforce.',
            'subs'        => [
                'Upload Data'          => 'Unggah data massal ke Salesforce.',
                'Akses dan Akun'       => 'Akses pengguna dan hak akses Salesforce.',
                'Koreksi Data'         => 'Perbaikan data yang sudah masuk.',
            ],
        ],
    ];

    public function run(): void
    {
        $categories = model(RequestCategoryModel::class);

        foreach (self::CATEGORIES as $name => $category) {
            $parentId = $this->ensure($categories, null, $name, $category['description']);

            foreach ($category['subs'] as $subName => $subDescription) {
                $this->ensure($categories, $parentId, $subName, $subDescription);
            }
        }
    }

    private function ensure(RequestCategoryModel $categories, ?string $parentId, string $name, string $description): string
    {
        $query = $categories->where('name', $name);
        $query = $parentId === null ? $query->where('parent_id', null) : $query->where('parent_id', $parentId);
        $found = $query->first();

        if ($found !== null) {
            return $found['id'];
        }

        $id = uuid_v7();
        $categories->insert(['id' => $id, 'parent_id' => $parentId, 'name' => $name, 'description' => $description, 'is_active' => 1]);

        return $id;
    }
}
