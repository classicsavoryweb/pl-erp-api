<?php

namespace App\Http\Services;

use App\Models\Bir1601C;
use App\Models\BirSetting;
use FPDF;
use Illuminate\Support\Facades\Storage;

class Bir1601CPdfService
{
    private const PAGE_W_PX = 1700;
    private const PAGE_H_PX = 2600;

    private const AMOUNT_ROWS_PAGE1 = [
        14 => 837, 15 => 909, 16 => 953, 17 => 997, 18 => 1041.5,
        19 => 1086, 20 => 1130, 21 => 1174.5, 22 => 1219, 23 => 1263,
        24 => 1307.5, 25 => 1352, 26 => 1396, 27 => 1440.5, 28 => 1485,
        29 => 1529, 30 => 1574, 31 => 1619.5, 32 => 1664, 33 => 1708,
        34 => 1752, 35 => 1796, 36 => 1840.5,
    ];

    private const AMOUNT_BOX_PAGE1 = [
        'right' => 1525, 'width' => 40,
        'centavoLeft' => 1567, 'centavoWidth' => 41,
    ];

    public function generate(Bir1601C $bir): string
    {
        $pdf = new FPDF('P', 'pt', [$this->px(self::PAGE_W_PX), $this->px(self::PAGE_H_PX)]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTextColor(0, 0, 0);

        $this->renderPage1($pdf, $bir);
        $this->renderPage2($pdf, $bir);

        return $pdf->Output('S');
    }

    private function renderPage1(FPDF $pdf, Bir1601C $bir): void
    {
        $this->background($pdf, 'bir-1601c-page-1.png', self::PAGE_W_PX, self::PAGE_H_PX);
        $pdf->SetFont('Helvetica', '', 11);

        $this->drawDigitBoxes($pdf, (int) $bir->month, 207, 40, 2, 327);
        $this->drawDigitBoxes($pdf, (int) $bir->year, 367, 40, 4, 327);

        $company = BirSetting::forCurrentCompany() ?? new BirSetting();
        $tinGroups = $company->tinGroups();

        $this->checkBox($pdf, $company->is_amended_return ? [509, 547] : [642, 680], 328);

        $this->checkBox($pdf, ((float) $bir->total_taxes_withheld > 0) ? [845, 883] : [958, 996], 328);

        $this->drawDigitBoxes($pdf, (int) $tinGroups[0], 766, 39.7, 3, 415);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[1], 925, 39.7, 3, 415);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[2], 1085, 40, 3, 415);
        $this->drawDigitBoxes($pdf, (int) $company->rdo_code, 1649, 41.67, 3, 415);

        $this->drawTextInBoxes($pdf, (string) $company->name, ['left' => 47, 'width' => 40, 'maxChars' => 40, 'y' => 490]);
        $this->drawWrappedTextInBoxes($pdf, (string) $company->address, [
            ['left' => 47, 'width' => 40, 'maxChars' => 40, 'y' => 562, 'uppercase' => false],
            ['left' => 47, 'width' => 40, 'maxChars' => 35, 'y' => 606, 'uppercase' => false],
        ]);
        $this->drawTextInBoxes($pdf, (string) $company->zip, ['left' => 1483, 'width' => 42, 'maxChars' => 4, 'y' => 606]);

        $this->drawTextInBoxes($pdf, (string) $company->contact_number, ['left' => 289, 'width' => 40, 'maxChars' => 11, 'y' => 653]);
        $this->checkBox($pdf, $company->is_government ? [1442, 1481] : [1242, 1280], 653);

        $this->checkBox($pdf, $company->has_tax_relief ? [485, 524] : [606, 645], 750);

        $fields = [
            14 => $bir->total_compensation,
            15 => $bir->statutory_minimum_wage,
            16 => $bir->holiday_pay_ot_ndf,
            17 => $bir->thirteenth_month_pay,
            18 => $bir->de_minimis_benefits,
            19 => $bir->ee_contributions,
            20 => $bir->other_non_taxable_compensation,
            21 => $this->totalNonTaxable($bir),
            22 => $bir->total_taxable_compensation,
            23 => $bir->taxable_compensation_not_subject_to_wtax,
            24 => $bir->net_taxable_compensation,
            25 => $bir->total_taxes_withheld,
            26 => $bir->add_less_adjustment,
            27 => $bir->total_remittance,
            28 => 0,
            29 => 0,
            30 => 0,
            31 => $bir->tax_still_due,
            32 => 0,
            33 => 0,
            34 => 0,
            35 => 0,
            36 => $bir->total_amount_due,
        ];

        foreach ($fields as $item => $value) {
            $this->drawAmount(
                $pdf,
                (float) $value,
                self::AMOUNT_ROWS_PAGE1[$item],
                self::AMOUNT_BOX_PAGE1['right'],
                self::AMOUNT_BOX_PAGE1['width'],
                self::AMOUNT_BOX_PAGE1['centavoLeft'],
                self::AMOUNT_BOX_PAGE1['centavoWidth']
            );
        }
    }

    private function totalNonTaxable(Bir1601C $bir): float
    {
        return (float) $bir->statutory_minimum_wage
            + (float) $bir->holiday_pay_ot_ndf
            + (float) $bir->thirteenth_month_pay
            + (float) $bir->de_minimis_benefits
            + (float) $bir->ee_contributions
            + (float) $bir->other_non_taxable_compensation;
    }

    private function renderPage2(FPDF $pdf, Bir1601C $bir): void
    {
        $this->background($pdf, 'bir-1601c-page-2.png', self::PAGE_W_PX, self::PAGE_H_PX);
        $pdf->SetFont('Helvetica', '', 9);

        $scheduleRowY = [1 => 484, 2 => 529.5, 3 => 574.5];
        $dueRowY = [1 => 680, 2 => 725, 3 => 770.5];

        foreach ([1, 2, 3] as $n) {
            $period = $bir->{"previous_month_{$n}_period"};
            $datePaid = $bir->{"previous_month_{$n}_date_paid"};
            $bank = $bir->{"previous_month_{$n}_bank"};
            $ror = $bir->{"previous_month_{$n}_ror_number"};
            $taxPaid = (float) $bir->{"previous_month_{$n}_tax_paid"};
            $taxDue = (float) $bir->{"previous_month_{$n}_tax_due"};

            $y = $scheduleRowY[$n];

            if ($period) {
                $digitsOnly = str_replace('/', '', $period);
                $this->drawTextInBoxes($pdf, $digitsOnly, ['left' => 88, 'width' => 39.33, 'maxChars' => 6, 'y' => $y]);
            }
            if ($datePaid) {
                $formattedDate = \Carbon\Carbon::parse($datePaid)->format('mdY');
                $this->drawTextInBoxes($pdf, $formattedDate, ['left' => 324, 'width' => 39.63, 'maxChars' => 8, 'y' => $y]);
            }
            if ($bank) {
                $this->drawTextInBoxes($pdf, $bank, ['left' => 641, 'width' => 39.6, 'maxChars' => 5, 'y' => $y]);
            }
            if ($ror) {
                $this->drawTextInBoxes($pdf, $ror, ['left' => 839, 'width' => 40.67, 'maxChars' => 6, 'y' => $y]);
            }
            $this->drawAmount($pdf, $taxPaid, $y, 1523, 40, 1565, 40.5);

            $dy = $dueRowY[$n];
            $this->drawAmount($pdf, $taxDue, $dy, 689, 40, 730, 40);
            $this->drawAmount($pdf, $taxPaid - $taxDue, $dy, 1365, 40, 1405, 40);
        }

        $this->drawAmount($pdf, (float) $bir->add_less_adjustment, 815.5, 1365, 40, 1405, 40);
    }

    protected function px(float $px): float
    {
        return $px * (72 / 200);
    }

    protected function background(FPDF $pdf, string $file, float $pageWidthPx, float $pageHeightPx): void
    {
        $pdf->AddPage();
        $pdf->Image(
            Storage::disk('hr_system_folder')->path("templates/{$file}"),
            0,
            0,
            $this->px($pageWidthPx),
            $this->px($pageHeightPx)
        );
    }

    protected function drawTextInBoxes(FPDF $pdf, string $text, array $line): void
    {
        $uppercase = $line['uppercase'] ?? true;
        $normalized = $uppercase ? mb_strtoupper($text, 'UTF-8') : $text;
        $encoded = $this->encodeForPdf($normalized);
        $chars = str_split($encoded);

        foreach ($chars as $i => $char) {
            if ($i >= $line['maxChars']) {
                break;
            }

            if ($char === ' ') {
                continue;
            }

            $boxLeft = $line['left'] + $i * $line['width'];
            $this->drawCenteredDigit($pdf, $char, $boxLeft, $line['width'], $line['y']);
        }
    }

    protected function drawWrappedTextInBoxes(FPDF $pdf, string $text, array $lines): void
    {
        $remaining = trim($text);

        foreach ($lines as $line) {
            if ($remaining === '') {
                break;
            }

            $max = $line['maxChars'];

            if (strlen($remaining) <= $max) {
                $chunk = $remaining;
                $remaining = '';
            } else {
                $breakAt = strrpos(substr($remaining, 0, $max + 1), ' ');

                if ($breakAt === false) {
                    $breakAt = $max;
                }

                $chunk = substr($remaining, 0, $breakAt);
                $remaining = ltrim(substr($remaining, $breakAt));
            }

            $this->drawTextInBoxes($pdf, $chunk, $line);
        }
    }

    protected function checkBox(FPDF $pdf, array $xRangePx, float $centerYPx): void
    {
        [$leftPx, $rightPx] = $xRangePx;
        $this->drawCenteredDigit($pdf, 'X', $leftPx, $rightPx - $leftPx, $centerYPx);
    }

    protected function drawAmount(
        FPDF $pdf,
        float $amount,
        float $centerYPx,
        float $boxRightPx,
        float $boxWidthPx,
        float $centavoLeftPx,
        float $centavoWidthPx
    ): void {
        $amount = round(abs($amount), 2);
        $whole = (int) floor($amount + 0.0000001);
        $cents = (int) round(($amount - $whole) * 100);

        $this->drawDigitBoxes($pdf, $whole, $boxRightPx, $boxWidthPx, null, $centerYPx);

        $centsStr = str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
        foreach (str_split($centsStr) as $i => $digit) {
            $boxLeft = $centavoLeftPx + $i * $centavoWidthPx;
            $this->drawCenteredDigit($pdf, $digit, $boxLeft, $centavoWidthPx, $centerYPx);
        }
    }

    protected function drawDigitBoxes(FPDF $pdf, int $number, float $boxRightPx, float $boxWidthPx, ?int $minDigits, float $centerYPx): void
    {
        $digits = str_split((string) $number);

        if ($minDigits !== null) {
            $digits = str_split(str_pad((string) $number, $minDigits, '0', STR_PAD_LEFT));
        }

        $n = count($digits);
        for ($i = 0; $i < $n; $i++) {
            $digit = $digits[$n - 1 - $i];
            $boxRight = $boxRightPx - $i * $boxWidthPx;
            $boxLeft = $boxRight - $boxWidthPx;
            $this->drawCenteredDigit($pdf, $digit, $boxLeft, $boxWidthPx, $centerYPx);
        }
    }

    protected function drawCenteredDigit(FPDF $pdf, string $digit, float $boxLeftPx, float $boxWidthPx, float $centerYPx): void
    {
        $boxLeft = $this->px($boxLeftPx);
        $boxWidth = $this->px($boxWidthPx);
        $textWidth = $pdf->GetStringWidth($digit);
        $x = $boxLeft + ($boxWidth - $textWidth) / 2;
        $y = $this->px($centerYPx) + 3;
        $pdf->Text($x, $y, $digit);
    }

    protected function encodeForPdf(string $text): string
    {
        $encoded = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);

        return $encoded === false ? $text : $encoded;
    }

    protected function drawFittedText(FPDF $pdf, string $text, array $box): void
    {
        $uppercase = $box['uppercase'] ?? true;
        $normalized = $uppercase ? mb_strtoupper($text, 'UTF-8') : $text;
        $encoded = trim($this->encodeForPdf($normalized));

        $restoreFontSize = $box['restoreFontSize'] ?? 11;

        if ($encoded === '') {
            return;
        }

        $maxFontSize = $box['maxFontSize'] ?? 9;
        $minFontSize = $box['minFontSize'] ?? 5.5;
        $boxWidthPt = $this->px($box['width']) - 4;
        $boxLeftPt = $this->px($box['left']) + 2;
        $centerYPt = $this->px($box['centerY']);
        $centered = $box['align'] ?? 'left';

        for ($size = $maxFontSize; $size >= $minFontSize; $size -= 0.5) {
            $pdf->SetFont('Helvetica', '', $size);
            $textWidth = $pdf->GetStringWidth($encoded);
            if ($textWidth <= $boxWidthPt) {
                $x = $centered === 'center' ? $boxLeftPt + ($boxWidthPt - $textWidth) / 2 : $boxLeftPt;
                $pdf->Text($x, $centerYPt + ($size * 0.32), $encoded);
                $pdf->SetFont('Helvetica', '', $restoreFontSize);

                return;
            }
        }

        $pdf->SetFont('Helvetica', '', $minFontSize);
        [$line1, $line2] = $this->splitTextToTwoLines($pdf, $encoded, $boxWidthPt);

        $lineGap = $minFontSize * 0.95;
        $x1 = $centered === 'center' ? $boxLeftPt + ($boxWidthPt - $pdf->GetStringWidth($line1)) / 2 : $boxLeftPt;
        $pdf->Text($x1, $centerYPt - $lineGap * 0.25, $line1);
        if ($line2 !== '') {
            $x2 = $centered === 'center' ? $boxLeftPt + ($boxWidthPt - $pdf->GetStringWidth($line2)) / 2 : $boxLeftPt;
            $pdf->Text($x2, $centerYPt + $lineGap * 0.85, $line2);
        }

        $pdf->SetFont('Helvetica', '', $restoreFontSize);
    }

    private function splitTextToTwoLines(FPDF $pdf, string $text, float $maxWidthPt): array
    {
        $words = explode(' ', $text);
        $line1 = '';
        $line2 = '';
        $onFirstLine = true;

        foreach ($words as $word) {
            if ($onFirstLine) {
                $candidate = $line1 === '' ? $word : $line1 . ' ' . $word;

                if ($pdf->GetStringWidth($candidate) <= $maxWidthPt || $line1 === '') {
                    $line1 = $candidate;
                } else {
                    $onFirstLine = false;
                    $line2 = $word;
                }
            } else {
                $candidate = $line2 === '' ? $word : $line2 . ' ' . $word;

                if ($pdf->GetStringWidth($candidate) <= $maxWidthPt || $line2 === '') {
                    $line2 = $candidate;
                } else {
                    break;
                }
            }
        }

        while ($line2 !== '' && $pdf->GetStringWidth($line2) > $maxWidthPt) {
            $line2 = substr($line2, 0, -1);
        }

        return [$line1, $line2];
    }
}
