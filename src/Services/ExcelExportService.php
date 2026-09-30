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
     * Génère un vrai classeur Office Open XML (.xlsx), sans dépendance Composer.
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
        $tmpBase = tempnam(sys_get_temp_dir(), 'ticketflow_xlsx_');
        if ($tmpBase === false) {
            throw new RuntimeException('Impossible de créer le fichier Excel temporaire.');
        }
        @unlink($tmpBase);
        $tmp = $tmpBase . '.xlsx';

        $zip = new ZipArchive();
        $result = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            @unlink($tmp);
            throw new RuntimeException('Impossible de créer l\'archive Excel (code ' . (string)$result . ').');
        }

        try {
            $this->zipAdd($zip, '[Content_Types].xml', $this->contentTypesXml());
            $this->zipAdd($zip, '_rels/.rels', $this->rootRelationshipsXml());
            $this->zipAdd($zip, 'docProps/app.xml', $this->appPropertiesXml($sheetName));
            $this->zipAdd($zip, 'docProps/core.xml', $this->corePropertiesXml());
            $this->zipAdd($zip, 'xl/workbook.xml', $this->workbookXml($sheetName));
            $this->zipAdd($zip, 'xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $this->zipAdd($zip, 'xl/styles.xml', $this->stylesXml());
            $this->zipAdd($zip, 'xl/theme/theme1.xml', $this->themeXml());
            $this->zipAdd($zip, 'xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($tmp);
            throw $e;
        }

        if (!$zip->close()) {
            @unlink($tmp);
            throw new RuntimeException('Impossible de finaliser le fichier Excel.');
        }

        $this->assertValidXlsx($tmp);
        return $tmp;
    }

    /**
     * Envoie le fichier XLSX sans aucune sortie parasite avant le ZIP.
     * Cette méthode est volontairement centralisée afin d'éviter les classeurs
     * corrompus par un BOM, un warning PHP ou un buffer HTML.
     */
    public function sendDownload(string $path, string $filename): never
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Le fichier Excel généré est introuvable.');
        }

        $this->assertValidXlsx($path);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        @ini_set('zlib.output_compression', '0');
        if (!headers_sent()) {
            header_remove('Content-Encoding');
            header_remove('Content-Type');
            header_remove('Content-Disposition');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: ' . $this->contentDisposition($filename));
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . (string)filesize($path));
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Content-Type-Options: nosniff');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            @unlink($path);
            throw new RuntimeException('Impossible de lire le fichier Excel généré.');
        }

        fpassthru($handle);
        fclose($handle);
        @unlink($path);
        exit;
    }

    private function zipAdd(ZipArchive $zip, string $name, string $content): void
    {
        if (!$zip->addFromString($name, $content)) {
            throw new RuntimeException('Impossible d\'ajouter « ' . $name . ' » au classeur Excel.');
        }
    }

    private function assertValidXlsx(string $path): void
    {
        if (!is_file($path) || filesize($path) < 500) {
            @unlink($path);
            throw new RuntimeException('Le fichier Excel généré est vide ou incomplet.');
        }

        $handle = fopen($path, 'rb');
        $signature = $handle !== false ? fread($handle, 4) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature === false || substr($signature, 0, 2) !== 'PK') {
            @unlink($path);
            throw new RuntimeException('Le fichier généré n’est pas une archive XLSX valide.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            @unlink($path);
            throw new RuntimeException('Le fichier XLSX généré ne peut pas être relu.');
        }

        $required = [
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ];
        foreach ($required as $entry) {
            if ($zip->locateName($entry) === false) {
                $zip->close();
                @unlink($path);
                throw new RuntimeException('Le classeur Excel est incomplet : ' . $entry . '.');
            }
        }
        $zip->close();
    }

    private function contentDisposition(string $filename): string
    {
        $filename = trim($filename) !== '' ? trim($filename) : 'ticketflow-export.xlsx';
        if (!str_ends_with(mb_strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        $ascii = $ascii !== false ? $ascii : 'ticketflow-export.xlsx';
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii) ?: 'ticketflow-export.xlsx';
        return 'attachment; filename="' . addcslashes($ascii, "\\\"") . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
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
            . '<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
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
            . '<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="0"/>'
            . '<workbookPr defaultThemeVersion="164011"/>'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>'
            . '<sheets><sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
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
            . '<dxfs count="0"/>'
            . '<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
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
        $range = 'A1:' . $lastColumn . max(1, $lastRow);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $range . '"/>'
            . '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
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

        return $xml
            . '</sheetData>'
            . '<autoFilter ref="' . $range . '"/>'
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '</worksheet>';
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
        $xml = '<row r="' . $rowNumber . '"' . ($style === 1 ? ' ht="22" customHeight="1"' : '') . '>';
        foreach ($values as $index => $value) {
            $cell = $this->columnName($index + 1) . $rowNumber;
            if (($style === 0) && (is_int($value) || is_float($value)) && is_finite((float)$value)) {
                $xml .= '<c r="' . $cell . '" s="0"><v>' . $this->xml((string)$value) . '</v></c>';
                continue;
            }
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
        $text = (string)$value;
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return $clean ?? '';
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

    private function appPropertiesXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Microsoft Excel Compatible / TicketFlow</Application>'
            . '<DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $this->xml($sheetName) . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>TicketFlow</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion>'
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

    private function themeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">'
            . '<a:themeElements><a:clrScheme name="Office">'
            . '<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>'
            . '<a:dk2><a:srgbClr val="44546A"/></a:dk2><a:lt2><a:srgbClr val="E7E6E6"/></a:lt2>'
            . '<a:accent1><a:srgbClr val="3157D5"/></a:accent1><a:accent2><a:srgbClr val="70AD47"/></a:accent2>'
            . '<a:accent3><a:srgbClr val="A5A5A5"/></a:accent3><a:accent4><a:srgbClr val="FFC000"/></a:accent4>'
            . '<a:accent5><a:srgbClr val="5B9BD5"/></a:accent5><a:accent6><a:srgbClr val="ED7D31"/></a:accent6>'
            . '<a:hlink><a:srgbClr val="0563C1"/></a:hlink><a:folHlink><a:srgbClr val="954F72"/></a:folHlink>'
            . '</a:clrScheme>'
            . '<a:fontScheme name="Office"><a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme>'
            . '<a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme>'
            . '</a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>';
    }
}
