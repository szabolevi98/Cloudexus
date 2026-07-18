<?php

namespace Cloudexus\Core;

class CsvExporter
{
    /**
     * Streams rows as a UTF-8 CSV download (BOM + semicolon delimiter, so
     * Hungarian Excel opens it correctly) and ends the request.
     *
     * @param string   $filename Base name without extension.
     * @param string[] $headers  Column header labels.
     * @param iterable $rows     Each row an array of scalar cell values.
     */
    public static function download(string $filename, array $headers, iterable $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Explicit enclosure + escape: PHP 8.4 deprecates the default $escape,
        // and a stray deprecation notice would corrupt the CSV output.
        fputcsv($out, $headers, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }

        fclose($out);
        exit;
    }
}
