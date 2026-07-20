<?php

namespace App\Http\Services;

use App\Models\Bir2316;
use App\Models\BirSetting;
use FPDF;
use Illuminate\Support\Facades\Storage;

class Bir2316PdfService
{
    private const PAGE_W_PX = 1700;
    private const PAGE_H_PX = 2600;

    // Part IVA Summary 
    private const SUMMARY_BOX = ['left' => 577, 'width' => 281];
    private const SUMMARY_ROWS = [
        19 => 1444, 20 => 1498, 21 => 1552, 22 => 1606, 23 => 1658.5,
        24 => 1712.5, 25 => 1766.5, '25B' => 1822, 26 => 1876.5, 27 => 1931.5, 28 => 1985.5,
    ];

    // Part IV-B 
    private const PARTB_BOX = ['left' => 1345, 'width' => 281];
    private const PARTB_ROWS = [
        29 => 413.5, 30 => 466, 31 => 521.5, 32 => 577.5, 33 => 633.5,
        34 => 687, 35 => 739.5, 36 => 793, 37 => 847, 38 => 900,
        39 => 1005.5, 40 => 1059, 41 => 1112, 42 => 1166, 43 => 1219,
        '44A' => 1295, '44B' => 1345, 45 => 1417.5, 46 => 1471, 47 => 1525,
        48 => 1579, 49 => 1633, 50 => 1687, '51A' => 1768, '51B' => 1822,
        52 => 1873,
    ];

    // Item 9 / 10 
    private const SMW_BOX = ['left' => 584, 'width' => 272];
    private const SMW_ROW_DAY = 782;
    private const SMW_ROW_MONTH = 826;

    public function generate(Bir2316 $bir, bool $withSignature = false, ?string $signatoryNameOverride = null): string
    {
        $pdf = new FPDF('P', 'pt', [$this->px(self::PAGE_W_PX), $this->px(self::PAGE_H_PX)]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTextColor(0, 0, 0);

        $this->render($pdf, $bir, $withSignature, $signatoryNameOverride);

        return $pdf->Output('S');
    }

    private function render(FPDF $pdf, Bir2316 $bir, bool $withSignature, ?string $signatoryNameOverride): void
    {
        $backgroundFile = $withSignature ? 'bir-2316-with-sign.png' : 'bir-2316-without-sign.png';
        $this->background($pdf, $backgroundFile, self::PAGE_W_PX, self::PAGE_H_PX);
        $pdf->SetFont('Helvetica', '', 11);

        $company = BirSetting::forCurrentCompany() ?? new BirSetting();

        $this->renderHeader($pdf, $bir);
        $this->renderEmployeeInfo($pdf, $bir);
        $this->renderEmployerPresent($pdf, $company);
        $this->renderEmployerPrevious($pdf, $bir);
        $this->renderSummary($pdf, $bir);
        $this->renderNonTaxable($pdf, $bir);
        $this->renderTaxable($pdf, $bir);
        $this->renderSignatures($pdf, $bir, $company, $signatoryNameOverride);
    }

    private function renderHeader(FPDF $pdf, Bir2316 $bir): void
    {
        $this->drawDigitBoxes($pdf, (int) $bir->year, 542, 49, 4, 282.5);

        if ($bir->period_from) {
            $from = \Carbon\Carbon::parse($bir->period_from);
            $this->drawDigitBoxes($pdf, (int) $from->format('m'), 1170, 48, 2, 282.5);
            $this->drawDigitBoxes($pdf, (int) $from->format('d'), 1267, 48, 2, 282.5);
        }
        if ($bir->period_to) {
            $to = \Carbon\Carbon::parse($bir->period_to);
            $this->drawDigitBoxes($pdf, (int) $to->format('m'), 1519, 48, 2, 282.5);
            $this->drawDigitBoxes($pdf, (int) $to->format('d'), 1616, 48, 2, 282.5);
        }
    }

    private function renderEmployeeInfo(FPDF $pdf, Bir2316 $bir): void
    {
        $tinGroups = $this->tinGroups($bir->tin);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[0], 343, 34.7, 3, 361.5);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[1], 481, 34.7, 3, 361.5);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[2], 619, 34.7, 3, 361.5);

        $this->drawFittedText($pdf, $bir->full_name(), ['left' => 118, 'width' => 585, 'centerY' => 432.5]);
        $this->drawDigitBoxes($pdf, (int) $bir->rdo_code, 838, 34.3, 3, 432.5);

        $address = (string) ($bir->registered_address ?? '');
        $this->drawFittedText($pdf, $address, ['left' => 119, 'width' => 572, 'centerY' => 510.5, 'uppercase' => false]);

        if (!empty($bir->date_of_birth)) {
            $dob = \Carbon\Carbon::parse($bir->date_of_birth);
            $this->drawDigitBoxes($pdf, (int) $dob->format('m'), 199, 34.5, 2, 726);
            $this->drawDigitBoxes($pdf, (int) $dob->format('d'), 268, 34.5, 2, 726);
            $this->drawDigitBoxes($pdf, (int) $dob->format('Y'), 408, 34.5, 4, 726);
        }

        $contactNumber = preg_replace('/\D/', '', (string) ($bir->contact_number ?? ''));
        if ($contactNumber) {
            $this->drawTextInBoxes($pdf, $contactNumber, ['left' => 465, 'width' => 35.8, 'maxChars' => 11, 'y' => 726]);
        }

        $this->drawAmountRight($pdf, (float) $bir->smw_rate_per_day, self::SMW_ROW_DAY, self::SMW_BOX);
        $this->drawAmountRight($pdf, (float) $bir->smw_rate_per_month, self::SMW_ROW_MONTH, self::SMW_BOX);

        if ($bir->is_mwe) {
            $this->checkBox($pdf, [133, 169], 877);
        }
    }

    private function renderEmployerPresent(FPDF $pdf, BirSetting $company): void
    {
        $tinGroups = $company->tinGroups();
        $this->drawDigitBoxes($pdf, (int) $tinGroups[0], 343, 34.7, 3, 951.5);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[1], 481, 34.7, 3, 951.5);
        $this->drawDigitBoxes($pdf, (int) $tinGroups[2], 619, 34.7, 3, 951.5);

        $this->drawFittedText($pdf, (string) $company->name, ['left' => 119, 'width' => 728, 'centerY' => 1023]);
        $this->drawFittedText($pdf, (string) $company->address, ['left' => 119, 'width' => 572, 'centerY' => 1095.5, 'uppercase' => false]);
        $this->drawTextInBoxes($pdf, (string) $company->zip, ['left' => 721, 'width' => 33, 'maxChars' => 4, 'y' => 1103]);

        $this->checkBox($pdf, [321, 360], 1149.5);
    }

    private function renderEmployerPrevious(FPDF $pdf, Bir2316 $bir): void
    {
        if ($bir->previous_employer_tin) {
            $tinGroups = $this->tinGroups($bir->previous_employer_tin);
            $this->drawDigitBoxes($pdf, (int) $tinGroups[0], 343, 34.7, 3, 1218.5);
            $this->drawDigitBoxes($pdf, (int) $tinGroups[1], 481, 34.7, 3, 1218.5);
            $this->drawDigitBoxes($pdf, (int) $tinGroups[2], 619, 34.7, 3, 1218.5);
        }

        if ($bir->previous_employer_name) {
            $this->drawFittedText($pdf, (string) $bir->previous_employer_name, ['left' => 119, 'width' => 728, 'centerY' => 1290]);
        }

        if ($bir->previous_employer_address) {
            $this->drawFittedText($pdf, (string) $bir->previous_employer_address, ['left' => 120, 'width' => 572, 'centerY' => 1364.5, 'uppercase' => false]);
        }
    }

    private function renderSummary(FPDF $pdf, Bir2316 $bir): void
    {
        $fields = [
            19 => $bir->gross_compensation,
            20 => $bir->less_non_taxable,
            21 => $bir->taxable_comp_present,
            22 => $bir->previous_employer_taxable_income,
            23 => $bir->gross_taxable_comp,
            24 => $bir->tax_due,
            25 => $bir->taxes_withheld_present,
            '25B' => $bir->previous_employer_tax_withheld,
            26 => $bir->total_taxes_withheld - (float) $bir->five_percent_tax_credit,
            27 => $bir->five_percent_tax_credit,
            28 => $bir->total_taxes_withheld,
        ];

        foreach ($fields as $item => $value) {
            $this->drawAmountRight($pdf, (float) $value, self::SUMMARY_ROWS[$item], self::SUMMARY_BOX);
        }
    }

    private function renderNonTaxable(FPDF $pdf, Bir2316 $bir): void
    {
        $fields = [
            29 => $bir->basic_salary_mwe,
            30 => $bir->holiday_pay_mwe,
            31 => $bir->overtime_pay_mwe,
            32 => $bir->night_shift_diff_mwe,
            33 => $bir->hazard_pay_mwe,
            34 => $bir->thirteenth_month_pay,
            35 => $bir->de_minimis_benefits,
            36 => $bir->sss_gsis_phic_hdmf_ee,
            37 => $bir->salaries_other_nontax,
            38 => $bir->total_non_taxable,
        ];

        foreach ($fields as $item => $value) {
            $this->drawAmountRight($pdf, (float) $value, self::PARTB_ROWS[$item], self::PARTB_BOX);
        }
    }

    private function renderTaxable(FPDF $pdf, Bir2316 $bir): void
    {
        $fields = [
            39 => $bir->basic_salary_taxable,
            40 => $bir->representation,
            41 => $bir->transportation,
            42 => $bir->cola,
            43 => $bir->fixed_housing,
            '44A' => $bir->others_taxable_a,
            '44B' => $bir->others_taxable_b,
            45 => $bir->commission,
            46 => $bir->profit_sharing,
            47 => $bir->fees,
            48 => $bir->taxable_13th_month_excess,
            49 => $bir->hazard_pay_taxable,
            50 => $bir->overtime_pay_taxable,
            '51A' => $bir->others_51a,
            '51B' => $bir->others_51b,
            52 => $bir->total_taxable_regular,
        ];

        foreach ($fields as $item => $value) {
            $this->drawAmountRight($pdf, (float) $value, self::PARTB_ROWS[$item], self::PARTB_BOX);
        }
    }

    private function renderSignatures(FPDF $pdf, Bir2316 $bir, BirSetting $company, ?string $signatoryNameOverride): void
    {
        $pdf->SetFont('Helvetica', '', 10);

        $signatoryName = (string) ($signatoryNameOverride ?? $company->signatory_name ?? '');
        $employeeName = $bir->full_name();

        if ($signatoryName) {
            $this->drawFittedText($pdf, $signatoryName, ['left' => 180, 'width' => 644, 'centerY' => 2135, 'uppercase' => false, 'maxFontSize' => 10, 'align' => 'center']); // item 53
            $this->drawFittedText($pdf, $signatoryName, ['left' => 180, 'width' => 644, 'centerY' => 2447, 'uppercase' => false, 'maxFontSize' => 10, 'align' => 'center']); // item 55
        }

        if ($employeeName) {
            $this->drawFittedText($pdf, $employeeName, ['left' => 180, 'width' => 644, 'centerY' => 2222, 'uppercase' => false, 'maxFontSize' => 10, 'align' => 'center']); // item 54
            $this->drawFittedText($pdf, $employeeName, ['left' => 1065, 'width' => 378, 'centerY' => 2487, 'uppercase' => false, 'maxFontSize' => 10, 'align' => 'center']); // item 56
        }
    }

    private function drawAmountRight(FPDF $pdf, float $amount, float $centerYPx, array $box): void
    {
        if (abs($amount) < 0.005) {
            return;
        }

        $text = number_format($amount, 2);
        $boxRight = $this->px($box['left'] + $box['width'] - 10);
        $textWidth = $pdf->GetStringWidth($text);
        $x = $boxRight - $textWidth;
        $y = $this->px($centerYPx) + 6;
        $pdf->Text($x, $y, $text);
    }

    private function tinGroups(?string $tin): array
    {
        $digits = preg_replace('/\D/', '', (string) $tin);
        $chunks = str_split((string) $digits, 3);

        return array_slice(array_pad($chunks, 3, ''), 0, 3);
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
