<?php

namespace App\Exports\Uniform;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class UniformArraySheet implements FromArray, WithHeadings, WithTitle , WithStyles
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private bool $freezeHeader = false
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return substr($this->title, 0, 31);
    }

      public function styles(Worksheet $sheet)
    {
        if ($this->freezeHeader) {
            $sheet->freezePane('A2');
            $sheet->freezePane('E2');
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getStyle('D:O')
            ->getNumberFormat()
            ->setFormatCode('###,##0.00');

        $green = new Conditional();
        $green->setConditionType(Conditional::CONDITION_CELLIS);
        $green->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
        $green->addCondition('0');
        $green->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('C6EFCE');

        $red = new Conditional();
        $red->setConditionType(Conditional::CONDITION_CELLIS);
        $red->setOperatorType(Conditional::OPERATOR_LESSTHAN);
        $red->addCondition('0');
        $red->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFC7CE');

        $conditionalStyles = $sheet->getStyle('O2:O' . ($sheet->getHighestRow()))
            ->getConditionalStyles();

        $conditionalStyles[] = $green;
        $conditionalStyles[] = $red;

        $sheet->getStyle('O2:O' . $sheet->getHighestRow())
            ->setConditionalStyles($conditionalStyles);
    }
}
