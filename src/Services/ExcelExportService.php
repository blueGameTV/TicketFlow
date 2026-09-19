<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

final class ExcelExportService
{
    /**
     * Valide une période inclusive limitée à 6 mois.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function validateRange(string $from, string $to): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);

        if (!$start || $start->format('Y-m-d') !== $from) {
            throw new RuntimeException('La date de début est invalide.');
        }
        if (!$end || $end->format('Y-m-d') !== $to) {
            throw new RuntimeException('La date de fin est invalide.');
        }
        if ($end < $start) {
            throw new RuntimeException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $maxEnd = $start->add(new DateInterval('P6M'));
        if ($end > $maxEnd) {
            throw new RuntimeException('Une extraction ne peut pas couvrir plus de 6 mois.');
        }

        return [$start, $end];
    }

    /**
     * Génère un fichier XLSX sans dépendance Composer.
     * L'extension PHP zip est requise.
     *
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public function createXlsx(string $sheetName, array $headers, array $rows): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('L\'extension PHP zip est requise pour générer les fichiers Excel (.xlsx).');
        }
        if ($headers === []) {
            throw new RuntimeException('Aucune colonne à exporter.');
        }

        $sheetName = $this->sanitizeSheetName($sheetName);
        $tmp = tempnam(sys_get_temp_dir(), 'ticketflow_xlsx_');
        if ($tmp === false) {
            throw new RuntimeException('Impossible de créer le fichier Excel temporaire.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Impossible de créer l\'archive Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());
        $zip->addFromString('docProps/core.xml', $this->corePropertiesXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        $zip->close();

        return $tmp;
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\\\/\?\*\[\]:]/u', '-', trim($name)) ?: 'Export';
        $name = mb_substr($name, 0, 31);
        return $name !== '' ? $name : 'Export';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF3157D5"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1" applyFill="1" applyFont="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|null>> $rows
     */
    private function worksheetXml(array $headers, array $rows): string
    {
        $columnCount = count($headers);
        $lastColumn = $this->columnName($columnCount);
        $lastRow = count($rows) + 1;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . max(1, $lastRow) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $this->columnsXml($headers)
            . '<sheetData>';

        $xml .= $this->rowXml(1, $headers, 1);
        $rowNumber = 2;
        foreach ($rows as $row) {
            $values = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);
            $xml .= $this->rowXml($rowNumber, $values, 0);
            $rowNumber++;
        }

        $xml .= '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . max(1, $lastRow) . '"/>'
            . '</worksheet>';

        return $xml;
    }

    /** @param list<string> $headers */
    private function columnsXml(array $headers): string
    {
        $xml = '<cols>';
        foreach ($headers as $index => $header) {
            $position = $index + 1;
            $width = min(45, max(12, mb_strlen($header) + 4));
            if (in_array(mb_strtolower($header), ['titre', 'description', 'applications / logins'], true)) {
                $width = 38;
            }
            $xml .= '<col min="' . $position . '" max="' . $position . '" width="' . $width . '" customWidth="1"/>';
        }
        return $xml . '</cols>';
    }

    /** @param list<string|int|float|null> $values */
    private function rowXml(int $rowNumber, array $values, int $style): string
    {
        $xml = '<row r="' . $rowNumber . '">';
        foreach ($values as $index => $value) {
            $cell = $this->columnName($index + 1) . $rowNumber;
            $text = $this->cleanCellValue($value);
            $xml .= '<c r="' . $cell . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
                . $this->xml($text) . '</t></is></c>';
        }
        return $xml . '</row>';
    }

    private function cleanCellValue(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = (string) $value;
        // XML 1.0 interdit certains caractères de contrôle.
        return preg_replace('/[^\P{C}\t\n\r]/u', '', $text) ?? '';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>TicketFlow</Application><AppVersion>8.0</AppVersion>'
            . '</Properties>';
    }

    private function corePropertiesXml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>TicketFlow</dc:creator><cp:lastModifiedBy>TicketFlow</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }
}
