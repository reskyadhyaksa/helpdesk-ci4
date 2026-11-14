<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Job title harus cocok persis (huruf besar-kecil dibedakan), termasuk pada unique key-nya.
 */
class MakeJobTitleAccessCaseSensitive extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `request_category_access_job_title` MODIFY `job_title` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `request_category_access_job_title` MODIFY `job_title` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
    }
}
