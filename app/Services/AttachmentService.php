<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\SystemParameterModel;
use App\Models\TicketAttachmentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Lampiran tiket: validasi (ukuran, jumlah, ekstensi dari parameter sistem) dan penyimpanan.
 * File disimpan di WRITEPATH/uploads/tickets/<ticket_id>/<uuid>.<ext>, di luar folder publik,
 * dan hanya bisa diunduh lewat controller yang memeriksa akses.
 */
class AttachmentService
{
    public const TYPE_PDF = 'application/pdf';

    // Jenis yang aman ditampilkan langsung di browser. SVG dan HTML sengaja tidak ada: keduanya bisa memuat skrip.
    private const PREVIEWABLE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', self::TYPE_PDF, 'text/plain'];

    public static function canPreview(string $contentType): bool
    {
        return in_array(strtolower($contentType), self::PREVIEWABLE_TYPES, true);
    }

    public function __construct(
        private readonly TicketAttachmentModel $attachments,
        private readonly SystemParameterModel $parameters,
    ) {
    }

    /**
     * Batas lampiran untuk ditampilkan di form.
     *
     * @return array{extensions: list<string>, max_mb: int, max_files: int}
     */
    public function limits(): array
    {
        return [
            'extensions' => $this->allowedExtensions(),
            'max_mb'     => $this->parameters->int(SystemParameterModel::ATTACHMENT_MAX_SIZE_MB),
            'max_files'  => $this->parameters->int(SystemParameterModel::ATTACHMENT_MAX_FILES),
        ];
    }

    /**
     * Memvalidasi semua file dari satu form dan membuang input file yang dikosongkan pengguna.
     *
     * @param array<int, mixed> $files
     *
     * @return list<UploadedFile> file yang benar-benar diunggah
     *
     * @throws BusinessException
     */
    public function validateAll(array $files): array
    {
        $uploaded = array_values(array_filter(
            $files,
            static fn ($file): bool => $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE,
        ));

        $max = $this->parameters->int(SystemParameterModel::ATTACHMENT_MAX_FILES);

        if (count($uploaded) > $max) {
            throw new BusinessException(lang('Attachment.tooMany', [$max]));
        }

        foreach ($uploaded as $file) {
            $this->validate($file);
        }

        return $uploaded;
    }

    /**
     * @throws BusinessException
     */
    public function validate(UploadedFile $file): void
    {
        $name = $file->getClientName();

        // Melebihi batas PHP (upload_max_filesize) bukan kegagalan acak: beri tahu batasnya
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new BusinessException(lang('Attachment.tooLargeServer', [$name, (string) ini_get('upload_max_filesize')]));
        }

        if (! $file->isValid()) {
            throw new BusinessException(lang('Attachment.uploadFailed', [$name]));
        }

        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new BusinessException(lang('Attachment.empty', [$name]));
        }

        $maxMb = $this->parameters->int(SystemParameterModel::ATTACHMENT_MAX_SIZE_MB);

        if ($size > $maxMb * 1024 * 1024) {
            throw new BusinessException(lang('Attachment.tooLarge', [$name, $maxMb]));
        }

        // Hanya ekstensi terakhir yang dinilai, jadi "foto.png.php" ditolak
        $allowed = $this->allowedExtensions();

        if (! in_array(strtolower($file->getClientExtension()), $allowed, true)) {
            throw new BusinessException(lang('Attachment.badType', [$name, strtoupper(implode(', ', $allowed))]));
        }
    }

    /**
     * @return array baris lampiran yang tersimpan
     *
     * @throws BusinessException
     */
    public function store(string $ticketId, ?string $commentId, UploadedFile $file): array
    {
        $this->validate($file);

        $extension = strtolower($file->getClientExtension());
        $fileName  = uuid_v7() . '.' . $extension;
        $checksum  = hash_file('sha256', $file->getTempName());
        $directory = $this->baseDirectory() . $ticketId;

        $row = [
            'ticket_id'       => $ticketId,
            'comment_id'      => $commentId,
            'original_name'   => $file->getClientName(),
            'storage_key'     => $ticketId . '/' . $fileName,
            'content_type'    => $file->getMimeType(),
            'size_bytes'      => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];

        $this->moveFile($file, $directory, $fileName);

        $id = $this->attachments->insert($row, true);

        if ($id === false) {
            $this->deleteFile($directory . '/' . $fileName);

            throw new BusinessException(implode(' ', $this->attachments->errors()));
        }

        return ['id' => $id] + $row;
    }

    /**
     * Menghapus file lampiran dari disk (retensi). Kunci penyimpanan harus berbentuk "<uuid tiket>/<uuid>.<ekstensi>"
     * persis seperti yang dibuat store(); bentuk lain ditolak agar tidak ada penghapusan di luar folder unggahan.
     *
     * @return bool true bila file sudah tidak ada (dihapus atau memang hilang); false bila ditolak atau gagal dihapus
     */
    public function remove(array $attachment): bool
    {
        $key = (string) ($attachment['storage_key'] ?? '');

        if (preg_match('#^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.[a-z0-9]{1,10}$#', $key) !== 1) {
            log_message('warning', 'Lampiran dengan kunci penyimpanan tidak wajar tidak dihapus: ' . $key);

            return false;
        }

        $path = $this->baseDirectory() . $key;

        $this->deleteFile($path);
        $this->removeEmptyDirectory(dirname($path));

        return ! is_file($path);
    }

    /**
     * Menghapus file yang sudah terlanjur disimpan bila proses pemanggil dibatalkan.
     *
     * @param list<array> $stored baris hasil store()
     */
    public function discard(array $stored): void
    {
        foreach ($stored as $attachment) {
            $this->deleteFile($this->absolutePath($attachment));
        }
    }

    /**
     * @return list<array>
     */
    public function forTicket(string $ticketId, bool $includeInternal): array
    {
        return $this->attachments->forTicket($ticketId, $includeInternal);
    }

    public function findWithVisibility(string $id): ?array
    {
        return $this->attachments->findWithVisibility($id);
    }

    public function absolutePath(array $attachment): string
    {
        return $this->baseDirectory() . $attachment['storage_key'];
    }

    protected function moveFile(UploadedFile $file, string $directory, string $name): void
    {
        $file->move($directory, $name);
    }

    protected function deleteFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function removeEmptyDirectory(string $directory): void
    {
        if (is_dir($directory) && count(scandir($directory)) === 2) {
            rmdir($directory);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        $list = explode(',', strtolower($this->parameters->value(SystemParameterModel::ATTACHMENT_ALLOWED_EXTENSIONS)));

        return array_values(array_filter(array_map('trim', $list)));
    }

    private function baseDirectory(): string
    {
        return WRITEPATH . 'uploads/tickets/';
    }
}
