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
        return $prefix . number_format($number, $decimals, $decPoints, $thousandsSep);
    }

    public static function rationalToFraction($input)
    {
        if (strlen($input) == 1) {
            return floatval($input);
        }

        preg_match('/^(?P<numerator>\d+)\/(?P<denominator>\d+)$/', $input, $fraction);
        $result = $fraction['numerator']/$fraction['denominator'];

        return $result;
    }

    public static function fractionToRational($float, $precision = 2)
    {
        $stop  = 0;
        $count = 2;
        while ($stop == 0) {
            $num = round(($float * $count), $precision);
            if (ctype_digit(strval($num))) {
                $rat  = $num . "/" . $count;
                $stop = 1;
            }
            $count++;
        }
        return $rat;
    }
}