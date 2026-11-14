<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\UsesUuidV7;
use CodeIgniter\Model;

class RequestCategoryAccessJobTitleModel extends Model
{
    use Auditable;
    use UsesUuidV7;

    protected $table            = 'request_category_access_job_title';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $beforeInsert     = ['generateUuid'];
    protected $allowedFields    = ['request_category_id', 'job_title'];

    protected $validationRules = [
        'request_category_id' => 'required|string',
        'job_title'           => 'required|max_length[150]',
    ];

    /**
     * @return list<string> id kategori yang punya aturan untuk jabatan ini
     */
    public function categoryIdsForJobTitle(string $jobTitle): array
    {
        // Kolom job_title bersifat case-sensitive (utf8mb4_bin), jadi jabatan harus cocok persis.
        $rows = $this->select('request_category_id')
            ->where('job_title', $jobTitle)
            ->findAll();

        return array_column($rows, 'request_category_id');
    }

    /**
     * @return list<string> job title yang dipakai di aturan akses kategori mana pun
     */
    public function usedJobTitles(): array
    {
        $rows = $this->distinct()->select('job_title')->orderBy('job_title', 'ASC')->findAll();

        return array_column($rows, 'job_title');
    }

    /**
     * @return list<string>
     */
    public function jobTitlesFor(string $categoryId): array
    {
        $rows = $this->select('job_title')
            ->where('request_category_id', $categoryId)
            ->orderBy('job_title', 'ASC')
            ->findAll();

        return array_column($rows, 'job_title');
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array<string, list<string>> [id kategori => daftar jabatan]; kategori tanpa aturan tidak ada
     */
    public function jobTitlesByCategory(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = $this->select('request_category_id, job_title')
            ->whereIn('request_category_id', $categoryIds)
            ->orderBy('job_title', 'ASC')
            ->findAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['request_category_id']][] = $row['job_title'];
        }

        return $grouped;
    }

    /**
     * Jadikan daftar jabatan kategori ini persis sama dengan $jobTitles.
     *
     * @param list<string> $jobTitles
     */
    public function replaceFor(string $categoryId, array $jobTitles): void
    {
        $jobTitles = array_values(array_unique($jobTitles));
        $existing  = $this->jobTitlesFor($categoryId);
        $removed   = array_diff($existing, $jobTitles);

        if ($removed !== []) {
            $this->where('request_category_id', $categoryId)->whereIn('job_title', $removed)->delete();
        }

        // Satu per satu lewat insert() agar callback Auditable berjalan (insertBatch tidak menjalankannya)
        foreach (array_diff($jobTitles, $existing) as $jobTitle) {
            $this->insert(['request_category_id' => $categoryId, 'job_title' => $jobTitle]);
        }
    }
}
