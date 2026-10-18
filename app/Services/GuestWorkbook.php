<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuestWorkbook
{
    public const CSV_HEADERS = ['Event Date', 'Event Session', 'Full Name', 'Phone', 'Gender', 'Home Address', 'Heard About', 'Comment'];

    private const ALIASES = [
        'event date' => 'event_date', 'event session' => 'event', 'events' => 'event', 'event' => 'event',
        'full name' => 'full_name', 'name' => 'full_name', 'phone' => 'phone_raw',
        'gender' => 'gender', 'home address' => 'home_address', 'heard about' => 'heard_about',
        'how did you hear about the conference' => 'heard_about', 'comment' => 'entry_notes',
        'timestamp' => 'captured_at', 'date' => 'captured_at',
        'follow up officer' => 'legacy_officer', 'follow up status' => 'legacy_status',
    ];

    public function read(string $content, string $extension): array
    {
        return $extension === 'csv' ? $this->csv($content) : $this->xlsx($content);
    }

    private function csv(string $content): array
    {
        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            $this->reject('Use a UTF-8 CSV without binary content.');
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($stream);
        try {
            $headers = fgetcsv($stream, null, ',', '"', '');
            $headers = $headers ? array_map('trim', $headers) : [];
            if (count($headers) !== 8 || count(array_unique($headers)) !== 8 || array_diff(self::CSV_HEADERS, $headers)) {
                $this->reject('Use all eight named columns in the Guest Service CSV template. Column order may vary.');
            }
            $rows = [];
            $number = 1;
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $number++;
                if (! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                    continue;
                }
                $errors = count($values) !== 8 ? ['Expected eight cells in this CSV row.'] : [];
                $raw = $errors ? ['unmapped_values' => $values] : array_combine($headers, $values);
                $rows[] = $this->row('CSV', $number, $raw, $errors);
                $this->limit(count($rows) <= 10000, 'Upload at most 10,000 non-empty rows.');
            }

            return ['sheets' => [['name' => 'CSV', 'headers' => $headers, 'rows' => count($rows)]], 'rows' => $rows];
        } finally {
            fclose($stream);
        }
    }

    private function xml(ZipArchive $zip, string $path): DOMXPath
    {
        $xml = $zip->getFromName($path);
        if ($xml === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            $this->reject('The workbook contains missing or unsafe XML parts.');
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                $this->reject('The workbook contains malformed XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($doc);
    }

    private function xlsx(string $content): array
    {
        $temporary = tmpfile();
        if ($temporary === false) {
            $this->reject('Unable to open a temporary workbook file.');
        }
        fwrite($temporary, $content);
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open(stream_get_meta_data($temporary)['uri']) !== true) {
                $this->reject('Upload a valid .xlsx workbook.');
            }
            $opened = true;
            $this->limit($zip->numFiles <= 2000, 'Workbook has too many ZIP entries.');
            $size = 0;
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $size += $stat['size'];
                $this->limit($size <= 30 * 1024 * 1024, 'Expanded workbook exceeds 30 MB.');
                if (isset($names[$name]) || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')
                    || preg_match('/vbaProject|externalLinks|embeddings|activeX/i', $name)) {
                    $this->reject('Workbook contains unsupported or unsafe ZIP parts. Use a plain .xlsx without macros, embedded objects or external links.');
                }
                $names[$name] = true;
            }
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            if ($workbook->evaluate('string(//*[local-name()="workbookPr"]/@date1904)') === '1'
                || $workbook->evaluate('string(//*[local-name()="workbookPr"]/@date1904)') === 'true') {
                $this->reject('1904-date workbooks are unsupported. Save using the standard 1900 date system.');
            }
            $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $targets = [];
            foreach ($relationships->query('//*[local-name()="Relationship"]') as $relation) {
                if ($relation->getAttribute('TargetMode') === 'External') {
                    $this->reject('External workbook relationships are unsupported.');
                }
                $target = $relation->getAttribute('Target');
                $target = str_starts_with($target, '/xl/') ? substr($target, 1) : 'xl/'.$target;
                if (str_contains($target, '..') || str_contains($target, '\\')) {
                    $this->reject('Invalid worksheet path.');
                }
                $targets[$relation->getAttribute('Id')] = $target;
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $shared = $this->xml($zip, 'xl/sharedStrings.xml');
                foreach ($shared->query('//*[local-name()="si"]') as $item) {
                    $strings[] = $this->text($shared, $item);
                    $this->limit(count($strings) <= 200000, 'Workbook has too many shared strings.');
                }
            }
            $allRows = [];
            $sheets = [];
            foreach ($workbook->query('//*[local-name()="sheet"]') as $sheet) {
                $sheetName = $sheet->getAttribute('name');
                $this->limit(mb_strlen($sheetName) <= 255 && ! in_array($sheetName, array_column($sheets, 'name'), true), 'Invalid or repeated worksheet name.');
                $id = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                if (! isset($targets[$id])) {
                    $this->reject('Worksheet relationship is missing.');
                }
                $worksheet = $this->xml($zip, $targets[$id]);
                $headers = null;
                $sheetCount = 0;
                $physicalRow = 0;
                $seenRows = [];
                foreach ($worksheet->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                    $physicalRow++;
                    $number = (int) ($row->getAttribute('r') ?: $physicalRow);
                    $this->limit($number >= 1 && $number <= 1048576 && ! isset($seenRows[$number]), 'Invalid or repeated worksheet row number.');
                    $seenRows[$number] = true;
                    $cells = [];
                    $formulaColumns = [];
                    $nextColumn = 0;
                    foreach ($worksheet->query('./*[local-name()="c"]', $row) as $cell) {
                        $reference = $cell->getAttribute('r');
                        if ($reference !== '' && ! preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/D', $reference, $parts)) {
                            $this->reject('Invalid worksheet cell reference.');
                        }
                        $column = $reference === '' ? $nextColumn : $this->column($parts[1]);
                        $this->limit($column < 100, 'Guest worksheets must use at most 100 columns.');
                        $nextColumn = $column + 1;
                        $value = $worksheet->evaluate('string(./*[local-name()="v"])', $cell);
                        $type = $cell->getAttribute('t');
                        if ($type === 's') {
                            if (! ctype_digit($value) || ! array_key_exists((int) $value, $strings)) {
                                $this->reject('Invalid shared string reference.');
                            }
                            $value = $strings[(int) $value];
                        } elseif ($type === 'inlineStr') {
                            $value = $this->text($worksheet, $cell);
                        }
                        if ($worksheet->query('./*[local-name()="f"]', $cell)->length > 0 || $type === 'e') {
                            $formulaColumns[] = $column;
                            $value = '[UNSAFE FORMULA/ERROR] '.$value;
                        }
                        $this->limit(mb_strlen($value) <= 10000, 'A workbook cell exceeds 10,000 characters.');
                        $cells[$column] = $value;
                    }
                    if (! array_filter($cells, fn ($value) => trim($value) !== '')) {
                        continue;
                    }
                    if ($headers === null) {
                        ksort($cells);
                        $headers = $cells;
                        $mapped = $this->map(array_fill_keys(array_values($headers), ''));
                        if (! in_array('full_name', array_keys($mapped), true) || ! in_array('phone_raw', array_keys($mapped), true) || $formulaColumns) {
                            $this->reject('Every non-empty worksheet needs NAME / Full Name and PHONE headers, without formulas.');
                        }
                        $known = array_filter(array_map(fn ($header) => self::ALIASES[$this->header($header)] ?? null, $headers));
                        if (count($known) !== count(array_unique($known))) {
                            $this->reject('A worksheet has multiple headers mapping to the same field.');
                        }

                        continue;
                    }
                    $raw = [];
                    foreach ($cells as $column => $value) {
                        $raw[$headers[$column] ?? 'Unmapped column '.($column + 1)] = $value;
                    }
                    $allRows[] = $this->row($sheetName, $number, $raw, $formulaColumns ? ['Formula/error cells are quarantined; replace them with reviewed literal values.'] : []);
                    $sheetCount++;
                    $this->limit(count($allRows) <= 10000, 'Upload at most 10,000 non-empty rows across worksheets.');
                }
                $sheets[] = ['name' => $sheetName, 'headers' => array_values($headers ?? []), 'rows' => $sheetCount];
                $this->limit(count($sheets) <= 50, 'Upload at most 50 worksheets.');
            }

            return ['sheets' => $sheets, 'rows' => $allRows];
        } finally {
            if ($opened) {
                $zip->close();
            }
            fclose($temporary);
        }
    }

    private function row(string $sheet, int $number, array $raw, array $errors): array
    {
        return ['sheet' => $sheet, 'number' => $number, 'raw' => $raw, 'mapped' => $this->map($raw), 'errors' => $errors];
    }

    public function map(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $header => $value) {
            $key = self::ALIASES[$this->header((string) $header)] ?? null;
            if ($key) {
                $mapped[$key] = trim((string) $value);
            }
        }

        return $mapped;
    }

    private function header(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/[\s_-]+/u', ' ', $value)));
    }

    private function text(DOMXPath $xpath, DOMElement $element): string
    {
        $result = '';
        foreach ($xpath->query('.//*[local-name()="t"]', $element) as $part) {
            $result .= $part->textContent;
        }

        return $result;
    }

    private function column(string $letters): int
    {
        $column = 0;
        foreach (str_split($letters) as $letter) {
            $column = $column * 26 + ord($letter) - 64;
        }

        return $column - 1;
    }

    private function limit(bool $valid, string $message): void
    {
        if (! $valid) {
            $this->reject($message);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
