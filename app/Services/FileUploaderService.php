<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class FileUploaderService
{
    protected bool $compressImage = false;
    protected int $maxWidth = 1024;

    /**
     * __construct
     *
     * @param  mixed $compressImage
     * @return void
     */
    public function __construct(bool $compressImage = false, int $maxWidth = 1024)
    {
        $this->compressImage = $compressImage;
        $this->maxWidth = $maxWidth;
    }

    /**
     * save
     *
     * @param  UploadedFile $uploadedFile
     * @param  string $oldFileNameToDelete
     * @return string
     */
    public function save(UploadedFile $uploadedFile, $oldFileNameToDelete = null)
    {
        if (!File::exists(public_path('uploads/'))) {
            File::makeDirectory(public_path('uploads/'));
        }
        $fileName = str_replace(' ', '-', pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . Str::uuid() . '.' . $uploadedFile->getClientOriginalExtension();
        if ($this->compressImage) {
            try {
                $img = Image::make($uploadedFile->getRealPath());
                if ($img->width() > $this->maxWidth) {
                    $img->resize($this->maxWidth, null, function ($c) {
                        $c->aspectRatio();
                    });
                }
                $img->save(public_path('uploads/' . $fileName), 75);
            } catch (\Throwable $e) {
                throw $e;
            }
        } else {
            $uploadedFile->move(public_path('uploads'), $fileName);
        }
        if ($oldFileNameToDelete && File::exists(public_path('uploads/' . $oldFileNameToDelete))) {
            File::delete(public_path('uploads/' . $oldFileNameToDelete));
        }
        return $fileName;
    }
}
