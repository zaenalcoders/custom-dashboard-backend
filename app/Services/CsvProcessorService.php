<?php

namespace App\Services;

use SplFileObject;

class CsvProcessorService
{
    /**
     * Class CSVProcessorService
     * 
     * This service provides utilities for processing CSV files, including validation, 
     * schema extraction, row counting, and type detection. It ensures that CSV files 
     * are properly formatted and contain consistent data types.
     * 
     * Constants:
     * - VALID_MIMES: List of valid MIME types for CSV files.
     * 
     * Methods:
     * 
     * - validateFile(string $filePath, bool $checkMime = true, int $sampleSize = 100): array
     *   Validates a CSV file for readability, proper formatting, and consistent data types.
     *   Returns an array of errors or a success message.
     * 
     * - detectTypeValue(string $value): string
     *   Detects the data type of a given value (e.g., integer, float, boolean, datetime, string).
     * 
     * - countCsvRows(string $filePath, bool $includeHeader = false): int
     *   Counts the number of rows in a CSV file, optionally including the header row.
     * 
     * - extractCsvSchema(string $filePath, int $sampleSize = 100): array
     *   Extracts the schema of a CSV file by analyzing a sample of rows to determine column data types.
     * 
     * - detectColumnTypeStreaming(array $values): string
     *   Determines the data type of a column based on a streaming analysis of its values.
     * 
     * - detectDelimiter(string $filePath, array $delimiters = [',', ';'], int $sampleLines = 10): string
     *   Detects the delimiter used in a CSV file by analyzing a sample of lines.
     * 
     * - isReadableCsv(string $filePath, bool $checkMime): bool
     *   Checks if a file is a readable and valid CSV file, optionally verifying its MIME type.
     * 
     * - openCsv(string $filePath): SplFileObject
     *   Opens a CSV file and configures it for reading with the detected delimiter.
     * 
     * - isEmptyRow(?array $row): bool
     *   Checks if a row is empty or contains only whitespace.
     */
    private const VALID_MIMES = [
        'text/csv',
        'text/plain',
        'application/vnd.ms-excel',
        'application/csv',
        'application/x-csv',
        'text/comma-separated-values',
        'text/x-comma-separated-values',
        'text/plain',
        'text/x-csv'
    ];

    private ?string $cachedDelimiter = null;

    /**
     * validateFile
     *
     * @param  mixed $filePath
     * @param  mixed $checkMime
     * @param  mixed $sampleSize
     * @return array
     */
    public function validateFile(string $filePath, bool $checkMime = true, int $sampleSize = 100): array
    {
        if (!$this->isReadableCsv($filePath, $checkMime)) {
            return ["File is not readable or not a valid CSV."];
        }

        $file = $this->openCsv($filePath);
        $header = null;
        $typeHints = [];
        $lineNum = 0;
        $errors = [];

        foreach ($file as $row) {
            if ($this->isEmptyRow($row)) continue;
            $lineNum++;

            if ($header === null) {
                $header = array_map('trim', $row);
                $numCols = count($header);

                if ($numCols < 2) {
                    return ["Invalid header or too few columns."];
                }
                continue;
            }

            if (count($row) !== count($header)) {
                return ["Inconsistent column count at line {$lineNum}."];
            }

            // if ($lineNum <= $sampleSize + 1) {
            //     foreach ($row as $i => $value) {
            //         $value = trim($value);
            //         if ($value === '') continue;
            //         $detected = $this->detectTypeValue($value);
            //         $typeHints[$i] ??= $detected;
            //     }
            // } else {
            //     foreach ($row as $i => $value) {
            //         $value = trim($value);
            //         if ($value === '') continue;
            //         $expected = $typeHints[$i] ?? 'string';
            //         $actual = $this->detectTypeValue($value);

            //         if ($expected !== $actual && !($expected === 'float' && $actual === 'integer')) {
            //             $col = $header[$i] ?? "column $i";
            //             return ["Type mismatch at line {$lineNum} ({$col}): expected {$expected}, found {$actual}."];
            //         }
            //     }
            // }
        }

        if ($lineNum <= 1) {
            $errors[] = "CSV file is empty or has no data.";
        }

        return $errors ?: ['valid' => true];
    }

    /**
     * detectTypeValue
     *
     * @param  mixed $value
     * @return string
     */
    private function detectTypeValue(string $value): string
    {
        $v = strtolower(trim($value));

        if (preg_match('/^-?\d+$/', $v)) return 'integer';
        if (is_numeric($v)) return 'float';
        if (in_array($v, ['true', 'false', 'yes', 'no', '0', '1'], true)) return 'boolean';
        if (
            preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $v)
            || preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}$/', $v)
        ) {
            return 'datetime';
        }

        return 'string';
    }

    /**
     * countCsvRows
     *
     * @param  mixed $filePath
     * @param  mixed $includeHeader
     * @return int
     */
    public function countCsvRows(string $filePath, bool $includeHeader = false): int
    {
        $file = $this->openCsv($filePath);

        $count = 0;
        $hasHeader = false;

        foreach ($file as $row) {
            if ($this->isEmptyRow($row)) continue;
            if (!$hasHeader) {
                $hasHeader = true;
                if (!$includeHeader) continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * extractCsvSchema
     *
     * @param  mixed $filePath
     * @param  mixed $sampleSize
     * @return array
     */
    public function extractCsvSchema(string $filePath, int $sampleSize = 100): array
    {
        $file = $this->openCsv($filePath);
        $header = null;
        $columns = [];

        foreach ($file as $row) {
            if ($this->isEmptyRow($row)) continue;

            if ($header === null) {
                $header = array_map('trim', $row);
                $columns = array_fill_keys($header, []);
                continue;
            }

            foreach ($header as $i => $col) {
                if (!isset($row[$i]) || trim($row[$i]) === '') continue;
                if (count($columns[$col]) < $sampleSize) {
                    $columns[$col][] = trim($row[$i]);
                }
            }

            if (--$sampleSize <= 0) break;
        }

        if (!$header) {
            throw new \Exception("Missing or invalid header in CSV.");
        }

        $schema = [];
        foreach ($columns as $col => $samples) {
            $schema[] = ['name' => $col, 'type' => $this->detectColumnTypeStreaming($samples)];
        }

        return $schema;
    }

    /**
     * detectColumnTypeStreaming
     *
     * @param  mixed $values
     * @return string
     */
    private function detectColumnTypeStreaming(array $values): string
    {
        if (!$values) return 'string';

        $int = $float = $bool = $date = true;

        static $boolValues = ['true', 'false', 'yes', 'no', '0', '1'];

        foreach ($values as $v) {
            $v = trim($v);
            $int &= (bool)preg_match('/^-?\d+$/', $v);
            $float &= is_numeric($v);
            $bool &= in_array(strtolower($v), $boolValues, true);
            $date &= (bool)(preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $v)
                || preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}$/', $v));
        }

        if ($int) return 'integer';
        if ($float) return 'float';
        if ($bool) return 'boolean';
        if ($date) return 'datetime';
        return 'string';
    }

    /**
     * detectDelimiter
     *
     * @param  mixed $filePath
     * @param  mixed $delimiters
     * @param  mixed $sampleLines
     * @return string
     */
    private function detectDelimiter(string $filePath, array $delimiters = [',', ';'], int $sampleLines = 10): string
    {
        if ($this->cachedDelimiter !== null) return $this->cachedDelimiter;

        $file = new SplFileObject($filePath, 'r');
        $scores = array_fill_keys($delimiters, 0);

        foreach (new \LimitIterator($file, 0, $sampleLines) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            foreach ($delimiters as $d) {
                $parts = str_getcsv($line, $d);
                if (count($parts) > 1) {
                    $scores[$d] += count($parts);
                }
            }
        }

        arsort($scores);
        $this->cachedDelimiter = array_key_first($scores) ?: ',';
        return $this->cachedDelimiter;
    }

    /**
     * isReadableCsv
     *
     * @param  mixed $filePath
     * @param  mixed $checkMime
     * @return bool
     */
    private function isReadableCsv(string $filePath, bool $checkMime): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) return false;
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') return false;

        if ($checkMime) {
            $mime = mime_content_type($filePath) ?: '';
            if (!in_array($mime, self::VALID_MIMES, true)) return false;
        }

        return true;
    }

    /**
     * openCsv
     *
     * @param  mixed $filePath
     * @return SplFileObject
     */
    private function openCsv(string $filePath): SplFileObject
    {
        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl($this->detectDelimiter($filePath));
        return $file;
    }

    /**
     * isEmptyRow
     *
     * @param  mixed $row
     * @return bool
     */
    private function isEmptyRow(?array $row): bool
    {
        return empty($row) || (count($row) === 1 && trim($row[0]) === '');
    }
}
