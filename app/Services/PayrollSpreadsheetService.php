<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class PayrollSpreadsheetService
{
    private const XML_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @var list<string> */
    private const TEMPLATE_HEADERS = [
        'Office', 'Employee Number', 'Name', 'Position', 'Gross Salary',
        'PERA', 'Hazard Pay', 'Clothing Allowance', 'Subsistence Allowance', 'Laundry Allowance', 'Medical Allowance',
        'Total Compensation', 'Withholding Tax', 'GSIS Contrib.',
        'EMER.', 'CONSO.', 'POLICY', 'GFAL', 'MPL (GSIS_LOAN)', 'COMPUTER', 'MPL LITE', 'GSL',
        'Pag ibig Contrib.', 'CALAMITY', 'MPL (PAGIBIG_LOAN)', 'HOUSING', 'MP2',
        'SALAMAT MABUHAY', 'FINES', 'MONTHLY DUES', 'CITY SAVINGS', 'FCB', 'Cebu CFI', 'BDO',
        'TOTAL DEDUCTIONS', 'NTHP Before LBP', 'LBP Loan Deduction', 'Net Take Home Pay',
    ];

    /**
     * @param  array{headers: list<string>, rows: array<int, array<int, mixed>>, totals: array<int, mixed>}  $data
     */
    public function generate(array $data, int $month, int $year): string
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the payroll spreadsheet.');
        }
        $archive = new ZipArchive;
        $opened = false;
        try {
            if (! copy(resource_path('payroll-register.xlsx'), $path) || $archive->open($path) !== true) {
                throw new RuntimeException('Unable to open the payroll spreadsheet template.');
            }
            $opened = true;
            $sheet = new DOMDocument;
            $sheet->loadXML($archive->getFromName('xl/worksheets/sheet1.xml'));
            $xpath = new DOMXPath($sheet);
            $xpath->registerNamespace('s', self::XML_NAMESPACE);
            $sheetData = $xpath->query('//s:sheetData')->item(0);
            $headerRow = $xpath->query('s:row[@r="3"]', $sheetData)->item(0);
            $bodyStyles = $this->styles($xpath, $sheetData, 4);
            $totalStyles = $this->styles($xpath, $sheetData, 12);
            $headers = [...self::TEMPLATE_HEADERS, ...array_values(array_diff($data['headers'], self::TEMPLATE_HEADERS))];
            $sourceColumns = array_flip($data['headers']);
            $lastColumn = $this->column(count($headers) + 1);
            $totalRowNumber = max(8, count($data['rows'])) + 4;
            if (count($headers) > count(self::TEMPLATE_HEADERS)) {
                $columns = $xpath->query('//s:cols')->item(0);
                $remainingColumns = $xpath->query('s:col[@max="16384"]', $columns)->item(0);
                $remainingColumns->setAttribute('min', (string) (count($headers) + 2));
                $extraColumns = $sheet->createElementNS(self::XML_NAMESPACE, 'col');
                foreach (['min' => 40, 'max' => count($headers) + 1, 'width' => 16, 'customWidth' => 1, 'style' => 1] as $attribute => $value) {
                    $extraColumns->setAttribute($attribute, (string) $value);
                }
                $columns->insertBefore($extraColumns, $remainingColumns);
            }
            $duesCell = $xpath->query('s:c[@r="AE3"]', $headerRow)->item(0);
            $headerRow->replaceChild($this->cell($sheet, 'AE3', sprintf('%02d/%d DUES', $month, $year), 32), $duesCell);
            foreach (array_slice($headers, count(self::TEMPLATE_HEADERS), preserve_keys: true) as $index => $header) {
                $headerRow->appendChild($this->cell($sheet, $this->column($index + 2).'3', $header, 5));
            }
            foreach (iterator_to_array($sheetData->childNodes) as $row) {
                if ($row instanceof DOMElement && (int) $row->getAttribute('r') > 3) {
                    $sheetData->removeChild($row);
                }
            }
            foreach ([...$data['rows'], $data['totals']] as $rowIndex => $values) {
                $isTotal = $rowIndex === count($data['rows']);
                $rowNumber = $isTotal ? $totalRowNumber : $rowIndex + 4;
                $row = $sheet->createElementNS(self::XML_NAMESPACE, 'row');
                $row->setAttribute('r', (string) $rowNumber);
                foreach ($headers as $index => $header) {
                    $column = $this->column($index + 2);
                    $value = isset($sourceColumns[$header]) ? $values[$sourceColumns[$header]] : ($index < 4 ? '' : 0.0);
                    if ($isTotal && $index < 4) {
                        $value = $index === 2 ? 'GRAND TOTAL' : '';
                    }
                    $style = ($isTotal ? $totalStyles : $bodyStyles)[$column] ?? ($isTotal ? 18 : ($index < 4 ? 9 : 1));
                    $row->appendChild($this->cell($sheet, $column.$rowNumber, $value, $style));
                }
                $sheetData->appendChild($row);
            }
            $xpath->query('//s:dimension')->item(0)->setAttribute('ref', 'A1:'.$lastColumn.$totalRowNumber);
            $archive->addFromString('xl/worksheets/sheet1.xml', $sheet->saveXML());
            $workbook = new DOMDocument;
            $workbook->loadXML($archive->getFromName('xl/workbook.xml'));
            $workbookPath = new DOMXPath($workbook);
            $workbookPath->registerNamespace('s', self::XML_NAMESPACE);
            foreach (iterator_to_array($workbookPath->query('//s:definedName')) as $name) {
                if ($name->getAttribute('name') === '_xlnm.Print_Area') {
                    $name->nodeValue = 'Payroll!$B$1:$'.$lastColumn.'$'.$totalRowNumber;
                } elseif ($name->getAttribute('name') === '_xlnm._FilterDatabase') {
                    $name->parentNode->removeChild($name);
                }
            }
            $archive->addFromString('xl/workbook.xml', $workbook->saveXML());
            $archive->deleteName('xl/calcChain.xml');
            foreach (['[Content_Types].xml' => 'PartName', 'xl/_rels/workbook.xml.rels' => 'Target'] as $part => $attribute) {
                $document = new DOMDocument;
                $document->loadXML($archive->getFromName($part));
                foreach (iterator_to_array($document->documentElement->childNodes) as $node) {
                    if ($node instanceof DOMElement && str_ends_with($node->getAttribute($attribute), 'calcChain.xml')) {
                        $node->parentNode->removeChild($node);
                    }
                }
                $archive->addFromString($part, $document->saveXML());
            }
            if (! $archive->close()) {
                throw new RuntimeException('Unable to save the payroll spreadsheet.');
            }
            $opened = false;

            return file_get_contents($path);
        } finally {
            if ($opened) {
                $archive->close();
            }
            unlink($path);
        }
    }

    /** @return array<string, int> */
    private function styles(DOMXPath $xpath, DOMElement $sheetData, int $rowNumber): array
    {
        $styles = [];
        foreach ($xpath->query('s:row[@r="'.$rowNumber.'"]/s:c', $sheetData) as $cell) {
            $styles[preg_replace('/\d+/', '', $cell->getAttribute('r'))] = (int) $cell->getAttribute('s');
        }

        return $styles;
    }

    private function cell(DOMDocument $sheet, string $reference, string|int|float|null $value, int $style): DOMElement
    {
        $cell = $sheet->createElementNS(self::XML_NAMESPACE, 'c');
        $cell->setAttribute('r', $reference);
        $cell->setAttribute('s', (string) $style);
        if (is_int($value) || is_float($value)) {
            $cell->appendChild($sheet->createElementNS(self::XML_NAMESPACE, 'v', number_format($value, 2, '.', '')));
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $text = $sheet->createElementNS(self::XML_NAMESPACE, 't');
            $text->setAttribute('xml:space', 'preserve');
            $text->appendChild($sheet->createTextNode(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value ?? '')));
            $inline = $sheet->createElementNS(self::XML_NAMESPACE, 'is');
            $inline->appendChild($text);
            $cell->appendChild($inline);
        }

        return $cell;
    }

    private function column(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $number--;
            $column = chr(65 + $number % 26).$column;
            $number = intdiv($number, 26);
        }

        return $column;
    }
}
