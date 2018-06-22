<?php
namespace Vitto;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Utils
{
    const ENVIROMENT = [
        'local' => 'http://vitto-%service%.local/v1/%method%',
        'staging' => '',
        'production' => '',
    ];

    public static function validateSchema($schema, $data)
    {
        $returnObj = new \stdClass();

        try {
            // validate required fields
            if (!empty($schema['required'])) {
                $req = [];
                foreach ($schema['required'] as $required) {
                    foreach ($data as $fieldName => $item) {
                        if (!in_array($required, array_keys($item))) {
                            $req[$required] = $required;
                        }
                    }
                }
                if (count($req) > 0) {
                    throw new \Exception('Required fields.');
                }
            }

            // validate required fields
            /*foreach ($data as $item) {
                foreach ($item as $fieldName => $fieldValue) {
                    dd($schema['properties'][$fieldName]);
                }
            }*/
            /*foreach ($schema['properties'] as $field => $attributes) {
                dd($field);
                dd(gettype($data[$field]));
                if ($data[$field]) {

                }
            }*/

            $returnObj->success = true;

        } catch (\Exception $exception) {
            $returnObj->success = false;
            $returnObj->message = $exception->getMessage();
            $returnObj->data = $req;
        }
        return $returnObj;
    }

    public static function dynamicProperty($obj, $path_str)
    {
        $val = null;

        $path = preg_split('/->/', $path_str);
        $node = $obj;
        while (($prop = array_shift($path)) !== null) {
            if (!is_object($obj) || !property_exists($node, $prop)) {
                $val = null;
                break;

            }
            $val = $node->$prop;

            $node = $node->$prop;
        }

        return $val;
    }

    /* Verifica se esta no horário brasileiro de verao */

    public static function isSummerTime($datetime = '') {
        $year = 2017; //Horario de verão em vigor
        $summertime = array(
            '2017' => array('begin' => '2017-10-15', 'end' => '2018-02-17'),
            '2018' => array('begin' => '2018-11-04', 'end' => '2019-02-16')
        );
        return ($datetime >= $summertime[$year]['begin'] && $datetime <= $summertime[$year]['end']);
    }

    /* Calcula o timezone por estado */

    public static function getTimeZone($uf = 'PR', $time = null) {
        $time = !$time ? date('Y-m-d H:i', time()) : $time;
        //https://pt.wikipedia.org/wiki/Fusos_hor%C3%A1rios_no_Brasil
        //https://pt.wikipedia.org/wiki/Ficheiro:Standard_Timezones_and_DST_of_Brazil.svg
        $block1 = array('FN');
        $block2 = array('PR', 'SC', 'RS', 'ES', 'RJ', 'MG', 'SP', 'GO', 'DF');
        $block3 = array('BA', 'SE', 'AL', 'PE', 'RN', 'PB', 'MA', 'CE', 'PI', 'TO', 'PA', 'AP');
        $block4 = array('MT', 'MS');
        $block5 = array('RR', 'RO', 'AM');
        $block6 = array('AC');

        $summertime = self::isSummerTime($time);

        if (!$summertime && in_array($uf, $block1)) {
            return 'UTC-2';
        }

        if ($summertime && in_array($uf, $block1)) {
            return 'UTC-3';
        }

        if (!$summertime && in_array($uf, $block3)) {
            return 'UTC-3';
        }

        if ($summertime && in_array($uf, $block3)) {
            return 'UTC-4';
        }

        if (!$summertime && in_array($uf, $block4)) {
            return 'UTC-4';
        }

        if ($summertime && in_array($uf, $block4)) {
            return 'UTC-4';
        }

        if (!$summertime && in_array($uf, $block5)) {
            return 'UTC-4';
        }

        if ($summertime && in_array($uf, $block5)) {
            return 'UTC-5';
        }

        if (!$summertime && in_array($uf, $block6)) {
            return 'UTC-5';
        }

        if ($summertime && in_array($uf, $block6)) {
            return 'UTC-6';
        }

        if (in_array($uf, $block2)) {
            return 'UTC-3';
        }
    }

    /*  Calcula horário baseado no fuso horario/ horario de verao por estado */

    public static function timezoneAlign($ufState, $time, $revert = false) {

        $timezone = self::getTimeZone(strtoupper($ufState), $time);

        if (!$revert) {
            /*  Horário base -> horário real  */
            $times = array(
                'UTC-2' => '+1 hours', //Horário de Noronha
                'UTC-3' => '+0 hours', //Horário de Brasilia + Horário de Recife + Horário Cuiabá Verão
                'UTC-4' => '-1 hours', //Horário de Manaus + Horário Cuiabá + Horário de Brasília Verão
                'UTC-5' => '-2 hours', //Horário de Rio Branco
                'UTC-6' => '-3 hours',
            );
        } else {
            /*  Horário real -> horário base  */
            $times = array(
                'UTC-2' => '-1 hours', //Horário de Noronha
                'UTC-3' => '+0 hours', //Horário de Brasilia + Horário de Recife + Horário Cuiabá Verão
                'UTC-4' => '+1 hours', //Horário de Manaus + Horário Cuiabá + Horário de Brasília Verão
                'UTC-5' => '+2 hours', //Horário de Rio Branco
                'UTC-6' => '+3 hours',
            );
        }
        if(!strtotime($time)){
            return false;
        }

        $dateTime = new \DateTime($time);
        if (isset($times[$timezone])) {
            $dateTime->modify($times[$timezone]);
        }
        return $dateTime;
    }

    public static function onTime($now, $opentime, $arrayExceptions = null)
    {

        $todayDate = $now->format("Y-m-d");
        $timeNow = $now->format('H:i');
        $todayDay = $now->format('w');

        $yesterday = $now->modify('-1 day');
        $yesterdayDate = $yesterday->format("Y-m-d");
        $yesterdayDay = $yesterday->format('w');

        $open = false;
        $isExtra ='';

        if (!is_array($opentime)) {
            $arrayTimes = json_decode($opentime);
        } else {
            $arrayTimes = $opentime;
        }

        foreach ($arrayTimes[$todayDay] as $key => $value){
            if (!empty($value->extra) && strcmp($value->extra, $timeNow) > 0){
                $isExtra = $value->extra;
                $open = true;
            }

            if ($value->active == 1){
                if ($value->close < $value->open ){
                    $value->close = '23:59';
                }

                if ($timeNow >= $value->open and $timeNow <= $value->close) {
                    $open = true;
                }
            }
        }
        /* Verifica Exceções */
        if (!empty($arrayExceptions)){
            foreach ($arrayExceptions as $key => $exception) {
                $exceptionTimes = json_decode($exception->times);

                if ($exception->date == $yesterdayDate){
                    if ($exception->status == 'Fechado' AND $isExtra){
                        $open = false;
                    }elseif ($exception->status == 'Aberto'){
                        foreach ($exceptionTimes as $key => $period) {
                            if ($period->extra >= $timeNow) {
                                $open = true;
                            }
                        }
                    }
                }else{
                    if ($exception->status == 'Fechado'){
                        $open = false;
                    }else{
                        foreach ($exceptionTimes as $key => $period) {
                            if ($period->close < $period->open ){
                                $period->close = '23:59';
                            }
                            if ($timeNow >= $period->open and $timeNow <= $period->close) {
                                $open = true;
                            }
                        }
                    }
                }
            }
        }

        return $open;
    }

    /**
     * @param null $data
     * @param string $method
     * @param null $uri
     * @return mixed|string
     */
    public static function requestToDbService($data = null, $method = 'post', $uri = null)
    {
        try {
            $client = new Client();
            $response = $client->{$method}(!empty($uri) ? $uri : env('URL_API_SERVICE_DB'),
                ['json' => $data]
            );

            return json_decode($response->getBody()->getContents());
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return json_encode([
                'response' => [
                    'code' => 400,
                    'errors' => [
                        'Empty Response'
                    ]
                ]
            ]);
        }
    }

    /**
     * @param $service
     * @param $method
     * @param null $data
     * @param string $env
     * @param null $domain
     * @return mixed
     */
    public static function requestServiceMethod($service, $method, $data = null, $domain = null)
    {
        try {
            if (empty($domain)) {
                $domain = self::ENVIROMENT[env('APP_ENV')];
            }

            $client = new Client();
            $response = $client->post(str_replace(['%service%', '%method%'], [$service, $method], $domain),
                ['json' => $data]
            );

            return json_decode($response->getBody()->getContents());
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }
}