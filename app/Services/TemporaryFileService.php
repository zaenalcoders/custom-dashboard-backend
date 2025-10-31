<?php

namespace App\Services;

use App\Models\TemporaryFile;

class TemporaryFileService
{

    /**
     * useFile
     *
     * @param  mixed $fileName
     * @return void
     */
    public function useFile($fileName)
    {
        TemporaryFile::where('file_name', $fileName)->update(['is_used' => 1]);
    }

    /**
     * removeFile
     *
     * @param  mixed $fileName
     * @return void
     */
    public function removeFile($fileName)
    {
        TemporaryFile::where('file_name', $fileName)->delete();
        if ($fileName && file_exists(public_path('uploads/' . $fileName))) {
            unlink(public_path('uploads/' . $fileName));
        }
    }

    /**
     * clearFile
     *
     * @return void
     */
    public function clearFile()
    {
        foreach (TemporaryFile::where('is_used', 0)->get() as $item) {
            if ($item->file_name && file_exists(public_path('uploads/' . $item->file_name))) {
                unlink(public_path('uploads/' . $item->file_name));
            }
            $item->delete();
        }
    }
}
