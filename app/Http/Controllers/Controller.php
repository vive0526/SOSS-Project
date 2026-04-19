<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class Controller
{
    protected function streamCsvDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Streams an HTML table download that Excel can open (served as .xls).
     *
     * @param  array<int, array{title?: string, headers: array<int, string>, rows: iterable<int, array<int, mixed>>}>  $tables
     */
    protected function streamExcelTablesDownload(string $filename, string $title, array $tables): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $tables) {
            $escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h2>' . $escape($title) . '</h2>';

            foreach ($tables as $table) {
                if (!empty($table['title'])) {
                    echo '<h3>' . $escape($table['title']) . '</h3>';
                }

                echo '<table border="1" cellspacing="0" cellpadding="4">';
                echo '<thead><tr>';
                foreach ($table['headers'] as $header) {
                    echo '<th>' . $escape($header) . '</th>';
                }
                echo '</tr></thead><tbody>';

                foreach ($table['rows'] as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . $escape($cell) . '</td>';
                    }
                    echo '</tr>';
                }

                echo '</tbody></table><br />';
            }

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
