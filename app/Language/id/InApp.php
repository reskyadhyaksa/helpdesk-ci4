<?php

// Teks notifikasi dalam aplikasi (lonceng). Tidak memuat catatan internal atau data kontak.
return [
    'assigned' => [
        'title' => 'Tiket ditugaskan kepada Anda',
        'body'  => '{0}: {1} (sebagai {2})',
    ],
    'status' => [
        'title'     => 'Status tiket diperbarui',
        'escalated' => 'Tiket dieskalasi',
        'body'      => '{0}: {1} → {2}',
    ],
    'comment' => [
        'title'    => 'Balasan baru pada tiket',
        'internal' => 'Catatan internal baru',
        'body'     => '{0}: pesan baru dari {1}',
    ],
    'mention' => [
        'title' => 'Anda disebut dalam tiket',
        'body'  => '{0}: {1} menyebut Anda dalam sebuah pesan',
    ],
    'sla' => [
        'response_warning'    => 'Batas respons hampir tercapai',
        'response_breached'   => 'Batas respons terlewat',
        'resolution_warning'  => 'Batas penyelesaian hampir tercapai',
        'resolution_breached' => 'Batas penyelesaian terlewat',
        'body'                => '{0} — {1}',
    ],
    'roles' => [
        'assignee' => 'PIC Helpdesk',
        'resolver' => 'PIC Resolver',
    ],
    'someone' => 'seseorang',
];
