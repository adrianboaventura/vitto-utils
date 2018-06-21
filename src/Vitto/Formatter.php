<?php
namespace Vitto;


class Formatter
{
    public function stringToDecimal($string, $decimals = 2, $decPoints = '.', $thousandsSep = '')
    {
        $number = floatval(str_replace(',', '.', str_replace('.', '', $string)));

        return number_format($number, $decimals, $decPoints, $thousandsSep);
    }

    public function moneyToString($number, $decimals = 2, $decPoints = ',', $thousandsSep = '.', $prefix = '')
    {
        $string = $prefix . number_format($number, $decimals, $decPoints, $thousandsSep);
    }
}