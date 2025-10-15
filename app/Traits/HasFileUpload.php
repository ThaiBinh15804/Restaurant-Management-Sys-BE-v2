<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasFileUpload
{
    /**
     * Upload file to storage with custom path pattern.
     * Returns the full URL to store in database.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $entityType Entity type (e.g., 'employee', 'customer')
     * @param string $entityId Entity ID
     * @param string|null $oldAvatarUrl Old avatar URL to delete (optional)
     * @return string The full URL of the stored file
     */
    protected function uploadFile(
        UploadedFile $file,
        string $entityType,
        string $entityId,
        ?string $oldAvatarUrl = null
    ): string {
        if ($oldAvatarUrl) {
            $this->deleteFileByUrl($oldAvatarUrl);
        }

        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . Str::random(10) . '.' . $extension;

        $path = "assets/{$entityType}/{$entityId}";

        $storedPath = $file->storeAs($path, $filename, 'public');

        return url('storage/' . $storedPath);
    }

    /**
     * Delete file from storage by its path.
     *
     * @param string $filePath File path relative to storage/app/public
     * @return bool
     */
    protected function deleteFile(string $filePath): bool
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }

    /**
     * Delete file from storage by its full URL.
     *
     * @param string $fileUrl Full URL of the file
     * @return bool
     */
    protected function deleteFileByUrl(string $fileUrl): bool
    {
        $urlPath = parse_url($fileUrl, PHP_URL_PATH);
        
        if (!$urlPath) {
            return false;
        }

        $relativePath = preg_replace('#^/storage/#', '', $urlPath);
        
        return $this->deleteFile($relativePath);
    }

    /**
     * Get the full URL for a stored file.
     *
     * @param string|null $filePath File path
     * @return string|null
     */
    protected function getFileUrl(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }

        if (Storage::disk('public')->exists($filePath)) {
            return url('storage/' . $filePath);
        }

        return null;
    }

    /**
     * Extract entity type from controller class name.
     * Example: EmployeeController -> employee
     *
     * @return string
     */
    protected function getEntityTypeFromController(): string
    {
        $className = class_basename(get_class($this));
        
        $entityName = str_replace('Controller', '', $className);
        
        return Str::lower($entityName);
    }
}
