<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExporter
{
    private const BRAND_COLOR = '1F4E79';
    private const ACCENT_COLOR = '2E75B6';
    private const LIGHT_FILL = 'EAF3F8';
    private const BORDER_COLOR = 'B7C9D6';

    public function export(string $format, string $filenameBase, string $title, array $headers, array $rows, ?string $pdfHtml = null): Response
    {
        return match ($format) {
            'csv' => $this->csv($filenameBase, $title, $headers, $rows),
            'excel' => $this->excel($filenameBase, $title, $headers, $rows),
            'pdf' => $this->pdf($filenameBase, $pdfHtml ?? ''),
            default => new Response('Неподдерживаемый формат отчета.', Response::HTTP_BAD_REQUEST),
        };
    }

    private function csv(string $filenameBase, string $title, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($title, $headers, $rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [$title], ';');
            fputcsv($output, ['Сформировано', date('d.m.Y H:i')], ';');
            fputcsv($output, ['Строк в отчете', count($rows)], ';');
            fputcsv($output, [], ';');
            fputcsv($output, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $this->attachment($filenameBase, 'csv'));

        return $response;
    }

    private function excel(string $filenameBase, string $title, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($title, $headers, $rows): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Отчет');

            $columnCount = max(1, count($headers));
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
            $lastDataRow = max(6, 5 + count($rows));

            $spreadsheet->getProperties()
                ->setCreator('Автосалон')
                ->setTitle($title)
                ->setSubject($title);

            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', $title);
            $sheet->setCellValue('A2', 'Сформировано: '.date('d.m.Y H:i'));
            $sheet->setCellValue('A3', 'Строк в отчете: '.count($rows));
            $sheet->fromArray($headers, null, 'A5');

            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A6');
            } else {
                $sheet->mergeCells("A6:{$lastColumn}6");
                $sheet->setCellValue('A6', 'Нет данных для отчета');
            }

            $sheet->getDefaultRowDimension()->setRowHeight(22);
            $sheet->getRowDimension(1)->setRowHeight(34);
            $sheet->freezePane('A6');
            $sheet->setAutoFilter("A5:{$lastColumn}{$lastDataRow}");

            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_COLOR]],
            ]);

            $sheet->getStyle("A5:{$lastColumn}5")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ACCENT_COLOR]],
            ]);

            $sheet->getStyle("A6:{$lastColumn}{$lastDataRow}")->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::BORDER_COLOR],
                    ],
                ],
            ]);

            $sheet->getStyle('A2:A3')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::BRAND_COLOR]],
            ]);

            for ($rowNumber = 6; $rowNumber <= $lastDataRow; $rowNumber++) {
                if ($rowNumber % 2 === 0) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB(self::LIGHT_FILL);
                }
            }

            for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $this->attachment($filenameBase, 'xlsx'));
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function pdf(string $filenameBase, string $html): Response
    {
        if ($html === '') {
            return new Response('Не найден шаблон PDF-отчета.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $this->attachment($filenameBase, 'pdf'));

        return $response;
    }

    private function attachment(string $filenameBase, string $extension): string
    {
        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filenameBase.'-'.date('Y-m-d-H-i').'.'.$extension
        );
    }
}
