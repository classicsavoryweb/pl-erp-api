<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use NumberFormatter;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function sendResponse($data) {
        return response()->json([
            "error" => false,
            "data" => $data,
        ], 200);
    }

    public function sendError($data, $status = 400) {
        return response()->json([
            "error" => true,
            "data" => $data,
        ], $status);
    }

    public function ordinal($number)
    {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        } else {
            return $number . $ends[$number % 10];
        }
    }

    public function convertToChequeWords($amount): string {
        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        $amount = str_replace(',', '', $amount);
        $amount = (float) $amount; 
        $parts = explode('.', number_format($amount, 2, '.', ''));
        $pesos = (int)$parts[0];
        $centavos = (int)$parts[1];

        $words = ucfirst($formatter->format($pesos));
        $words = str_replace('-', ' ', $words);
        $words = ucwords($words);
        $centavosPart = str_pad($centavos, 2, '0', STR_PAD_RIGHT);
        $formattedAmount = number_format($amount, 2, '.', ',');
        return "{$words} & {$centavosPart}/100 ({$formattedAmount})";
    }

    public function getCurrentCutoff() {
        $now = Carbon::now();
        $startTime = "";
        $endTime = "";
        $dayOfMonth = Carbon::now()->day;

        if ($dayOfMonth >= 1 && $dayOfMonth <= 15) {
            $startTime = $now->copy()->subMonth()->startOfMonth()->addDays(25)->startOfDay(); //26
            $endTime = $now->copy()->startOfMonth()->addDays(9)->endOfDay(); //10
        } else {
            $startTime = $now->copy()->startOfMonth()->startOfMonth()->addDays(10)->startOfDay(); //11
            $endTime = $now->copy()->startOfMonth()->addDays(24)->endOfDay(); //25
        }

        return [
            "start_time" => $startTime->toDateString(),
            "end_time" => $endTime->toDateString()
        ];
    }

    public function getPreviousCutOff($date) {
        $now = Carbon::parse($date);
        $day = $now->day;

        if ($day >= 1 && $day <= 15) {
            $startTime = $now->copy()->subMonth()->startOfMonth()->addDays(25);
            $endTime = $now->copy()->startOfMonth()->addDays(9);
        } else {
            $startTime = $now->copy()->startOfMonth()->addDays(10);
            $endTime = $now->copy()->startOfMonth()->addDays(24);
        }

        return [
            "start_time" => $startTime->toDateString(),
            "end_time" => $endTime->toDateString()
        ];
    }

    public function getCutOffForDate($date){
        $carbonDate = Carbon::parse($date);
        $day = $carbonDate->day;

        $cutOff1 = [11,12,13,14,15,16,17,18,19,20,21,22,23,24,25];
        $cutOff2 = [26,27,28,29,30,31,1,2,3,4,5,6,7,8,9,10];
        if (in_array($day, $cutOff2)) {
            if ($day <= 10) { 
                $startTime = $carbonDate->copy()->setDay(26)->subMonth()->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->endOfDay();
            } else {
                $startTime = $carbonDate->copy()->setDay(26)->startOfDay();
                $endTime = $carbonDate->copy()->setDay(10)->addMonth()->endOfDay();
            }
        } else {
            $startTime = $carbonDate->copy()->setDay(11)->startOfDay();
            $endTime = $carbonDate->copy()->setDay(25)->endOfDay();
        }

        return [
            'start_time' => $startTime->toDateString(),
            'end_time' => $endTime->toDateString(),
        ];
    }

    public function formatHours($hours) {
        $formattedHours = $hours;

        if ($formattedHours < 1 && $formattedHours > 0) {
            $formattedHours = .5;
        } else {
            $formattedHours = floor($hours / 0.5) * 0.5;
        }   
        return $formattedHours;
    }

    public function excelDateToDateString($excelDate) {
        if (is_numeric($excelDate)) {
            $unixDate = ($excelDate - 25569) * 86400;
            return gmdate("Y-m-d", $unixDate);
        }
        if (strtotime($excelDate) !== false) {
            return date('Y-m-d', strtotime($excelDate));
        }
        return $excelDate;
    }

    function writeCell(
        DOMDocument $dom,
        DOMElement $sheetData,
        string $cellRef,
        string $value
    ) {
        preg_match('/\d+/', $cellRef, $matches);

        if (!$matches) {
            return;
        }

        $value = htmlspecialchars($value);

        $rowNumber = (int)$matches[0];

        $rowNode = null;

        foreach ($sheetData->childNodes as $node) {

            if (
                $node instanceof DOMElement &&
                $node->tagName === 'row' &&
                (int)$node->getAttribute('r') === $rowNumber
            ) {
                $rowNode = $node;
                break;
            }
        }


        if (!$rowNode) {
            $rowNode = $dom->createElement('row');
            $rowNode->setAttribute('r',$rowNumber);
            $inserted = false;

            foreach ($sheetData->childNodes as $existingRow) {
                if (
                    $existingRow instanceof DOMElement &&(int)$existingRow->getAttribute('r') > $rowNumber) {
                    $sheetData->insertBefore($rowNode, $existingRow);

                    $inserted = true;
                    break;
                }
            }


            if (!$inserted) {
                $sheetData->appendChild($rowNode);
            }
        }

        $cellNode = null;

        foreach ($rowNode->childNodes as $node) {
            if ($node instanceof DOMElement && $node->tagName === 'c' && $node->getAttribute('r') === $cellRef) {
                $cellNode = $node;
                break;
            }
        }

        if (!$cellNode) {

            $cellNode = $dom->createElement('c');
            $cellNode->setAttribute('r',$cellRef);
            $cellNode->setAttribute('t','inlineStr');
            $rowNode->appendChild($cellNode);
        } else {
            $style = $cellNode->getAttribute('s');

            while ($cellNode->hasChildNodes()) {
                $cellNode->removeChild($cellNode->firstChild);
            }

            $cellNode->setAttribute('t','inlineStr');

            if ($style !== '') {
                $cellNode->setAttribute('s', $style);
            }
        }


        $isNode =  $dom->createElement('is');
        $textNode = $dom->createElement('t');
        $textNode->nodeValue = $value;

        $isNode->appendChild($textNode);
        $cellNode->appendChild($isNode);
    }

    function hashExcelPassword($password)
    {
        $password = (string) $password;
        $passwordLength = strlen($password);
        $hash = 0;
        for ($i = $passwordLength - 1; $i >= 0; $i--) {
            $charCode = ord($password[$i]);
            $hash = (($hash >> 14) & 0x01) | (($hash << 1) & 0x7fff);
            $hash ^= $charCode;
        }

        $hash ^= 0x8000 | (ord('N') << 8) | ord('K');
        $hash &= 0x7fff;

        return strtoupper(dechex($hash));
    }

    function normalizeCutOff($startDate, $endDate, $year) {

        $startMonth = (int) explode('-', $startDate)[0];
        $endMonth   = (int) explode('-', $endDate)[0];

        $startYear = $year;
        $endYear   = $year;

        if ($startMonth === 12 && $endMonth === 1) {
            $startYear = $year - 1;
        }

        $startDate = $startYear . '-' . $startDate;
        $endDate   = $endYear . '-' . $endDate;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    function numeric($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    function getTaxRefundPeriod(int $year): array
    {
        return [
            "start" => Carbon::create($year - 1, 12, 26)->format("Y-m-d"),
            "end" => Carbon::create($year, 12, 25)->format("Y-m-d"),
        ];
    }

}
