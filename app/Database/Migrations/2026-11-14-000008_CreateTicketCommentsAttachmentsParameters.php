<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

/**
 * Percakapan tiket, lampiran, dan parameter sistem (batas lampiran, dst.) sesuai ERD HC Helpdesk.
 */
class CreateTicketCommentsAttachmentsParameters extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->createComments();
        $this->createAttachments();
        $this->createParameters();
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_attachments');
        $this->forge->dropTable('ticket_comments');
        $this->forge->dropTable('system_parameters');
    }

    private function createComments(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'UUID'],
            'ticket_id'  => ['type' => 'UUID'],
            'author_id'  => ['type' => 'UUID', 'null' => true],
            'author_type' => ['type' => 'VARCHAR', 'constraint' => 15],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'PUBLIC'],
            'body'       => ['type' => 'TEXT'],
            'source'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'WEB'],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ticket_comments');
    }

    private function createAttachments(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'UUID'],
            'ticket_id'       => ['type' => 'UUID'],
            'comment_id'      => ['type' => 'UUID', 'null' => true],
            'original_name'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'storage_key'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'content_type'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'size_bytes'      => ['type' => 'BIGINT', 'unsigned' => true],
            'checksum_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('comment_id', 'ticket_comments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ticket_attachments');
    }

    private function createParameters(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'UUID'],
            'param_key'   => ['type' => 'VARCHAR', 'constraint' => 60],
            'param_value' => ['type' => 'VARCHAR', 'constraint' => 255],
            'value_type'  => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'STRING'],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('param_key');
        $this->forge->createTable('system_parameters');
    }
}
