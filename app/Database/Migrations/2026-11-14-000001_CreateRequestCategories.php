<?php

namespace App\Database\Migrations;

use App\Database\Traits\AuditColumns;
use CodeIgniter\Database\Migration;

class CreateRequestCategories extends Migration
{
    use AuditColumns;

    public function up(): void
    {
        $this->createRequestCategories();
        $this->createJobTitleRules();
        $this->createUserExceptions();
    }

    public function down(): void
    {
        $this->forge->dropTable('request_category_access_user');
        $this->forge->dropTable('request_category_access_job_title');
        $this->forge->dropTable('request_categories');
    }

    private function createRequestCategories(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'UUID'],
            'parent_id'     => ['type' => 'UUID', 'null' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sla_policy_id' => ['type' => 'UUID', 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['parent_id', 'name']);
        $this->forge->addForeignKey('parent_id', 'request_categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('sla_policy_id', 'sla_policies', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('request_categories');
    }

    private function createJobTitleRules(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'UUID'],
            'request_category_id' => ['type' => 'UUID'],
            'job_title'           => ['type' => 'VARCHAR', 'constraint' => 150],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['request_category_id', 'job_title']);
        $this->forge->addForeignKey('request_category_id', 'request_categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('request_category_access_job_title');
    }

    private function createUserExceptions(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'UUID'],
            'user_id'             => ['type' => 'UUID'],
            'request_category_id' => ['type' => 'UUID'],
            'effect'              => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'allow'],
            ...$this->auditFields(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'request_category_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('request_category_id', 'request_categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('request_category_access_user');
    }
}
