<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MaterialImport implements
    WithChunkReading,
    WithHeadingRow,
    SkipsEmptyRows,
    WithCalculatedFormulas,
    WithValidation
{
    /**
     * chunkSize
     *
     * @return int
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * headingRow
     *
     * @return int
     */
    public function headingRow(): int
    {
        return 1;
    }

    /**
     * rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:128',
            'description' => 'required|string|max:512',
            'qty' => 'required|numeric|min:0',
            'unit' => 'required|string|max:64',
            'sn' => 'nullable|string|max:128',
        ];
    }
}
