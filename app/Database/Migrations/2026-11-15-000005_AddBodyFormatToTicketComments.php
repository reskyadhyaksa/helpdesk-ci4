<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Komentar tiket kini bisa berformat teks biasa (TEXT, semua komentar lama) atau HTML hasil editor
 * yang sudah disaring CommentSanitizer (HTML).
 */
class AddBodyFormatToTicketComments extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('ticket_comments', [
            'body_format' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'TEXT', 'after' => 'body'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('ticket_comments', 'body_format');
    }
}
