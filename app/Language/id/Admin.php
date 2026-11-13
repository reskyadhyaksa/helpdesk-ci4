<?php

// Judul dipakai di sidebar dan header halaman, jadi cukup diubah di sini.
return [
    'groups' => [
        'tickets'  => 'Ticket Management',
        'settings' => 'System Settings',
        'data'     => 'Data Management',
        'reports'  => 'Report & Export',
    ],

    'pages' => [
        'dashboard' => [
            'title'    => 'Dashboard',
            'subtitle' => 'Ringkasan aktivitas layanan HC Helpdesk.',
        ],
        'tickets' => [
            'title'    => 'All Ticket',
            'subtitle' => 'Semua tiket bantuan dari seluruh karyawan.',
        ],
        'myTickets' => [
            'title'    => 'My Ticket',
            'subtitle' => 'Tiket yang ditugaskan kepada Anda.',
        ],
        'roles' => [
            'title'    => 'Roles Category',
            'subtitle' => 'Kelola peran dan hak akses pengguna.',
            'action'   => 'Tambah Role',
        ],
        'slaPolicies' => [
            'title'    => 'SLA Policies',
            'subtitle' => 'Atur batas waktu respons dan penyelesaian tiket.',
            'action'   => 'Tambah SLA',
        ],
        'requestCategories' => [
            'title'    => 'Request Category',
            'subtitle' => 'Kategori layanan yang dipilih karyawan saat membuat tiket.',
            'action'   => 'Tambah Kategori',
        ],
        'faqs' => [
            'title'    => 'FAQ Management',
            'subtitle' => 'Kelola pertanyaan yang tampil di Pusat Bantuan.',
            'action'   => 'Tambah FAQ',
        ],
        'forms' => [
            'title'    => 'Form Management',
            'subtitle' => 'Atur field formulir untuk tiap kategori permintaan.',
            'action'   => 'Tambah Form',
        ],
        'users' => [
            'title'    => 'User',
            'subtitle' => 'Daftar pengguna portal beserta perannya.',
        ],
        'holidays' => [
            'title'    => 'Holiday',
            'subtitle' => 'Hari libur dan hari off yang tidak dihitung dalam SLA.',
            'action'   => 'Tambah Hari Libur',
        ],
        'reports' => [
            'title'    => 'Report & Analytics',
            'subtitle' => 'Ringkasan performa layanan dan ekspor data tiket.',
            'action'   => 'Ekspor Data',
        ],
    ],

    'users' => [
        'roleUpdated'         => 'Role pengguna berhasil diubah. Berlaku setelah pengguna login ulang.',
        'cannotChangeOwnRole' => 'Anda tidak bisa mengubah role akun sendiri.',
        'roleNotFound'        => 'Role yang dipilih tidak ditemukan.',
        'searchPlaceholder'   => 'Cari nama, NIK, atau email',
        'emptyTitle'          => 'Pengguna tidak ditemukan',
        'emptyMessage'        => 'Pengguna terdaftar otomatis saat pertama kali login ke portal.',
    ],

    'roles' => [
        'createTitle'        => 'Tambah Role',
        'editTitle'          => 'Ubah Role',
        'formSubtitle'       => 'Atur nama role dan permission yang dimilikinya.',
        'created'            => 'Role berhasil ditambahkan.',
        'updated'            => 'Role berhasil diperbarui. Perubahan permission berlaku setelah pengguna login ulang.',
        'deleted'            => 'Role berhasil dihapus.',
        'confirmDelete'      => 'Hapus role ini? Tindakan ini tidak bisa dibatalkan.',
        'systemCannotDelete' => 'Role sistem tidak bisa dihapus.',
        'hasUsers'           => 'Role masih dipakai pengguna. Pindahkan pengguna ke role lain sebelum menghapus.',
        'unknownPermission'  => 'Permission yang dipilih tidak dikenal.',
        'lockedHint'         => 'Selalu aktif untuk role admin agar admin tidak terkunci dari panel.',
        'reloginHint'        => 'Perubahan permission berlaku setelah pengguna login ulang.',
    ],

    'sla' => [
        'createTitle'              => 'Tambah Kebijakan SLA',
        'editTitle'                => 'Ubah Kebijakan SLA',
        'formSubtitle'             => 'Target waktu dihitung sejak tiket dibuat.',
        'created'                  => 'Kebijakan SLA berhasil ditambahkan.',
        'updated'                  => 'Kebijakan SLA berhasil diperbarui.',
        'deleted'                  => 'Kebijakan SLA berhasil dihapus.',
        'confirmDelete'            => 'Hapus kebijakan SLA ini? Tindakan ini tidak bisa dibatalkan.',
        'resolutionBeforeResponse' => 'Target penyelesaian tidak boleh lebih cepat dari target respons pertama.',
        'emptyTitle'               => 'Belum ada kebijakan SLA',
        'emptyMessage'             => 'Tambahkan kebijakan pertama untuk mengatur batas waktu penanganan tiket.',
    ],

    'placeholderTitle'   => 'Halaman ini sedang dikembangkan',
    'placeholderMessage' => 'Data akan tampil di sini setelah fiturnya selesai dibuat.',
    'comingSoon'         => 'Segera hadir',
    'collapseSidebar'    => 'Perkecil sidebar',
    'expandSidebar'      => 'Perluas sidebar',
];
