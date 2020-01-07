<?php
namespace Vitto;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class Utils
{
    const ENVIROMENT = [
        'local' => 'http://vitto-%service%.local/v1/%method%',
        'homol' => 'http://homol.api.%service%.vtto.com.br/v1/%method%',
        'production' => 'http://api.%service%.vtto.com.br/v1/%method%',
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
        $year = 2018; //Horario de verão em vigor
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

        $timezone = self::getTimeZone(strtoupper($ufState ?? 'SP'), $time);

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
        $timeNow = $now->format('H:i');
        $todayDay = $now->format('w');

        $open = false;
        $isExtra ='';

        if (!is_array($opentime)) {
            $arrayTimes = json_decode($opentime);
        } else {
            $arrayTimes = $opentime;
        }

        if(!empty($arrayTimes[$todayDay])) {
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
        }
        
        /* Verifica Exceções */
        if (!empty($arrayExceptions)){
            foreach ($arrayExceptions as $key => $exception) {
                $exceptionTimes = json_decode($exception->times);
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

        return $open;
    }

    public static function onTime2($now, $opentime, $arrayExceptions = null) {
        try {
            $onTime     = true;
            $timeNow    = $now->format('H:i');
            $todayDay   = $now->format('w');
            $isExtra    = '';
            $arrayTimes = !empty(!is_array($opentime)) && !is_array($opentime) ? json_decode($opentime) : $opentime;

            foreach ($arrayTimes[$todayDay] as $key => $value) {
                if (!empty($value->extra) && strcmp($value->extra, $timeNow) > 0) {
                    $isExtra = $value->extra;
                    $onTime  = true;
                }

                if ($value->active == 1) {
                    if ($value->close < $value->open) {
                        $value->close = '23:59';
                    }

                    if ($timeNow < $value->open || $timeNow > $value->close) {
                        $onTime = false;
                    }
                }
            }
            /* Verifica Exceções */
            if (!empty($arrayExceptions)) {
                foreach ($arrayExceptions as $key => $exception) {
                    $exceptionTimes = json_decode($exception->times);

                    if ($exception->date == $yesterdayDate) {
                        if ($exception->status == 'Fechado' && $isExtra) {
                            $onTime = false;
                        }
                        elseif ($exception->status == 'Aberto') {
                            foreach ($exceptionTimes as $key => $period) {
                                if ($period->extra >= $timeNow) {
                                    $onTime = true;
                                }
                            }
                        }
                    }
                    else {
                        if ($exception->status == 'Fechado') {
                            $onTime = false;
                        }
                        else {
                            foreach ($exceptionTimes as $key => $period) {
                                if ($period->close < $period->open) {
                                    $period->close = '23:59';
                                }
                                if ($timeNow >= $period->open and $timeNow <= $period->close) {
                                    $onTime = true;
                                }
                            }
                        }
                    }
                }
            }
            /**/

            return $onTime;
        }
        catch (\Exception $exc) {
            \Log::error($exc->getTraceAsString());
            return false;
        }

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
            $response = $client->{$method}(!empty($uri) ? $uri : env('URL_API_SERVICE_DB', 'http://homol.api.db.vtto.com.br/api/v1'),
                ['json' => $data]
            );

            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            Log::error(Psr7\str($e->getRequest()));
            if ($e->hasResponse()) {
                Log::error(Psr7\str($e->getResponse()));
            }
            return json_encode([
                'response' => [
                    'code' => 400,
                    'errors' => [
                        'Empty Response'
                    ]
                ]
            ]);
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

    /**
     * Gera o Hash de password padrão do FUELPHP
     * @param type $string
     * @return type
     */
    public static function createPassword($string) {
        return base64_encode(hash_pbkdf2('sha256', $string, 'put_your_salt_here', 10000, 32, true));
    }

    public static function getUserByHash($hash, $client = 'cardapio', $service = 'account')
    {
        try {
            $domain = self::ENVIROMENT[env('APP_ENV')];
            $method = 'get-user';
            $data = [
                'client' => $client,
                'request' => [
                    'data' => [
                        'x-user-key' => $hash
                    ]
                ]
            ];

            $client = new Client();
            $response = $client->post(str_replace(['%service%', '%method%'], [$service, $method], $domain),
                ['json' => $data]
            );

            $oResponse = json_decode($response->getBody()->getContents());

            $oUser = $oResponse->response->data;

            return $oUser;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    public static function findDeliveryArea($points, $deliveryAreas) {
        try {
            if (empty($deliveryAreas)) {
                return [];
            }

            if ($points[0] == 'x' OR $points[1] == 'x')
            {
                return end($deliveryAreas);
            }

            foreach ($deliveryAreas as $key => $value) {
                $polygon = json_decode ($value->geolocation);
                $polygon = $polygon[0];

                if($polygon[0] != $polygon[count($polygon)-1]){
                    $polygon[count($polygon)] = $polygon[0];
                }
                $j = 0;

                $x = $points[1];
                $y = $points[0];
                $n = count($polygon);
                for ($i = 0; $i < $n; $i++) {
                    $j++;
                    if ($j == $n) {
                        $j = 0;
                    }
                    if ((($polygon[$i]->lat < $y) && ($polygon[$j]->lat >= $y)) || (($polygon[$j]->lat < $y) && ($polygon[$i]->lat >= $y))) {
                        if ($polygon[$i]->lng + ($y - $polygon[$i]->lat) / ($polygon[$j]->lat - $polygon[$i]->lat) * ($polygon[$j]->lng - $polygon[$i]->lng) < $x) {
                            return $value;
                            break;
                        }
                    }
                }

                return [];
            }

        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    /* Truncar n casas decimais em um float. */
    public static function truncate($fFloat, $iDecimals="0")
    {
        if(($cPos = strpos($fFloat, '.')) !== false) {
            $fFloat = floatval(substr($fFloat, 0, $cPos + 1 + $iDecimals));
        }
        return $fFloat;
    }

}