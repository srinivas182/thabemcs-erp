<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use RuntimeException;
use ZipArchive;

/**
 * Minimal Excel (.xlsx) writer for report tables: one sheet, bold header, number formats for
 * money (R # ##0.00), percent and dates. No external library needed.
 */
final class XlsxWriter
{
    public function write(ReportResult $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }

        $sheetName = mb_substr((string) preg_replace('/[\\\\\/?*\[\]:]/', '', $report->title), 0, 31) ?: 'Report';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        // Style ids: 0 normal, 1 bold, 2 money, 3 number, 4 percent, 5 date, 6 bold money.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;R&quot;\ #,##0.00"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="7"><xf/><xf fontId="1" applyFont="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf numFmtId="4" applyNumberFormat="1"/><xf numFmtId="165" applyNumberFormat="1"/><xf numFmtId="14" applyNumberFormat="1"/><xf numFmtId="164" fontId="1" applyNumberFormat="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($report));
        $zip->close();

        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private function sheet(ReportResult $report): string
    {
        $rows = [$this->row(1, [$this->text('A', 1, $report->title, 1)]), $this->row(2, [$this->text('A', 2, $report->subtitle, 0)])];
        $r = 4;

        $cells = [];
        foreach ($report->columns as $i => $col) {
            $cells[] = $this->text($this->col($i), $r, $col['label'], 1);
        }
        $rows[] = $this->row($r++, $cells);

        $all = [...$report->rows, ...($report->totals !== null ? [$report->totals] : [])];
        foreach ($all as $index => $data) {
            $isTotal = $report->totals !== null && $index === count($report->rows);
            $cells = [];
            foreach ($report->columns as $i => $col) {
                $value = $data[$col['key']] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = $this->col($i).$r;
                $cells[] = match ($col['type']) {
                    'money' => '<c r="'.$ref.'" s="'.($isTotal ? 6 : 2).'"><v>'.(float) $value.'</v></c>',
                    'number' => '<c r="'.$ref.'" s="3"><v>'.(float) $value.'</v></c>',
                    'percent' => '<c r="'.$ref.'" s="4"><v>'.(float) $value.'</v></c>',
                    'date' => '<c r="'.$ref.'" s="5"><v>'.$this->serial((string) $value).'</v></c>',
                    default => $this->text($this->col($i), $r, (string) $value, $isTotal ? 1 : 0),
                };
            }
            $rows[] = $this->row($r++, $cells);
        }

        $widths = '';
        foreach ($report->columns as $i => $c) {
            $widths .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.($c['type'] === 'text' ? 32 : 16).'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>'.$widths.'</cols><sheetData>'.implode('', $rows).'</sheetData></worksheet>';
    }

    /**
     * @param  list<string>  $cells
     */
    private function row(int $n, array $cells): string
    {
        return '<row r="'.$n.'">'.implode('', $cells).'</row>';
    }

    private function text(string $col, int $row, string $value, int $style): string
    {
        return '<c r="'.$col.$row.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->esc($value).'</t></is></c>';
    }

    private function col(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + (($n - 1) % 26)).$name;
        }

        return $name;
    }

    /** Excel date serial (days since 1899-12-30). */
    private function serial(string $date): int
    {
        return (int) round(((int) strtotime($date.' 00:00:00 UTC') - (int) strtotime('1899-12-30 00:00:00 UTC')) / 86400);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
