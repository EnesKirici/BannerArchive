<?php

namespace App\Support\Text;

/**
 * Türkçeye özgü büyük/küçük harf dönüşümü.
 *
 * mb_strtoupper('istanbul') → 'ISTANBUL' verir; Türkçede doğrusu 'İSTANBUL'.
 * Noktalı/noktasız i çifti bu yüzden dönüşümden önce elle eşlenir.
 */
final class TurkishCase
{
    public static function upper(string $text): string
    {
        return mb_strtoupper(strtr($text, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    }

    public static function lower(string $text): string
    {
        return mb_strtolower(strtr($text, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
    }
}
