<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\\s\\x00-\\x1F]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(self::cell(...), $row), ',', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
