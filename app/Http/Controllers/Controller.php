<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use NumberFormatter;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function sendResponse($data) {
        return response()->json([
            "error" => false,
            "data" => $data,
        ], 200);
    }

    public function sendError($data) {
        return response()->json([
            "error" => true,
            "data" => $data,
        ], 400);
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
}
