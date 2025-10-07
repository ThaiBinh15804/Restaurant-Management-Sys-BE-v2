<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadService
{
    /**
     * Upload file và trả về URL
     *
     * @param UploadedFile $file
     * @param string $path
     * @return string $url
     */
    public function uploadImage(UploadedFile $file, string $path): string
    {
        $path = trim($path, '/');
        $storagePath = "public/uploads/$path";

        // Tạo thư mục nếu chưa có
        if (!Storage::exists($storagePath)) {
            Storage::makeDirectory($storagePath, 0755, true);
        }

        // Lưu file
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->storeAs($storagePath, $filename);

        // Trả về URL public
        return Storage::url("uploads/$path/$filename");
    }
}
