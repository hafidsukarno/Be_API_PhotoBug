<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\PatternFill;

class StatisticsReportExport implements FromArray, WithHeadings, WithStyles
{
    protected $data;
    protected $period;

    public function __construct($data, $period)
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Desa',
            'Kecamatan',
            'Jumlah Laporan',
            'Wereng Coklat',
            'Wereng Hijau',
            'Tanggal Laporan',
            'Penyuluh Bertanggung Jawab'
        ];
    }

    public function styles($sheet)
    {
        $sheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        // Header styling
        $sheet->getStyle('1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => PatternFill::FILL_SOLID,
                'startColor' => ['rgb' => '2F5496']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Data rows styling
        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D3D3D3']
                    ]
                ]
            ]);

            // Alternate row colors
            if ($row % 2 == 0) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(PatternFill::FILL_SOLID)
                    ->setStartColor(['rgb' => 'F0F0F0']);
            }
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(25);

        // Center alignment for numeric columns
        $sheet->getStyle("D2:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $sheet;
    }
}
