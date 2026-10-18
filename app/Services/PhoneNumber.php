<?php

namespace App\Services;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if (! $phone || ! preg_match('/^\\+?[0-9\\s().-]+$/', trim($phone))) {
            return null;
        }
        $digits = preg_replace('/\\D/', '', $phone);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '234'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && preg_match('/^[789]/', $digits)) {
            $digits = '234'.$digits;
        }
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return '+'.$digits;
    }

    public static function searchPrefix(string $search): string
    {
        $digits = preg_replace('/\\D/', '', $search);
        if (str_starts_with($digits, '0')) {
            $digits = '234'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
