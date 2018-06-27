<?php
namespace Vitto;


class Formatter
{
    public static function stringToDecimal($string, $decimals = 2, $decPoints = '.', $thousandsSep = '')
    {
        $number = floatval(str_replace(',', '.', str_replace('.', '', $string)));

        return number_format($number, $decimals, $decPoints, $thousandsSep);
    }

    public static function moneyToString($number, $decimals = 2, $decPoints = ',', $thousandsSep = '.', $prefix = '')
    {
        $string = $prefix . number_format($number, $decimals, $decPoints, $thousandsSep);
    }

    public static function stringToFraction($input)
    {
        if (strlen($input) == 1) {
            return floatval($input);
        }

        preg_match('/^(?P<numerator>\d+)\/(?P<denominator>\d+)$/', $input, $fraction);
        $result = $fraction['numerator']/$fraction['denominator'];

        return $result;
    }
}