<?php
/**
 * echo "██╗   ██╗███████╗██╗  ██╗ ██████╗ ██████╗  █████╗ ██╗         ";
 * echo "██║   ██║██╔════╝██║  ██║██╔═══██╗██╔══██╗██╔══██╗██║         ";
 * echo "██║   ██║███████╗███████║██║   ██║██████╔╝███████║██║         ";
 * echo "██║   ██║╚════██║██╔══██║██║   ██║██╔═══╝ ██╔══██║██║         ";
 * echo "╚██████╔╝███████║██║  ██║╚██████╔╝██║     ██║  ██║███████╗    ";
 * echo " ╚═════╝ ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝    ";
 * echo "                                                              ";
 * 日期: 2019/7/6
 * 作者: four-li
 */

namespace OMS\Sdk;

use Curl\Curl;

class Oms
{
    private $platfrom;

    private $signMethod = 'md5';

    private $gatewayUrl;

    private $sdkVersion = 1.0;

    private $format = 'json';

    private $curl;

    public function __construct(string $gatewayUrl, string $platfrom)
    {
        $this->platfrom   = strtolower($platfrom);
        $this->gatewayUrl = rtrim($gatewayUrl, "\/") . '/';
        $this->curl       = new Curl();
    }

    protected function generateSign(array $params): string
    {
        ksort($params);

        $stringToBeSigned = $this->platfrom;
        foreach ($params as $k => $v) {
            if ((is_string($v) || is_numeric($v)) && "@" != substr($v, 0, 1)) {
                $stringToBeSigned .= "$k$v";
            }
        }
        unset($k, $v);
        $stringToBeSigned .= $this->platfrom;

        return strtoupper(md5($stringToBeSigned));
    }

    public function getRawResponse(ClientInterface $client)
    {
        if (!$this->platfrom) return $this->exception('参数platform缺失');

        try {
            $client->check();
        } catch (\Exception $e) {
            return $this->exception($e->getMessage());
        }

        //组装系统参数
        $sysParams["platform"]   = $this->platfrom;
        $sysParams["v"]          = $this->sdkVersion;
        $sysParams["format"]     = $this->format;
        $sysParams["signMethod"] = $this->signMethod;
        $sysParams["method"]     = $client->getMethod();
        $sysParams["route"]      = $client->getRoute();
        $sysParams["timestamp"]  = time();
        $sysParams["oms"]        = 'yaya=3=fourli';

        $apiParams = $client->getApiParameters();

        $parameters = array_merge($sysParams, $apiParams);

        $sign = $this->generateSign($parameters);

        $this->curl->setHeader('oms-token', $sign);

        $this->curl->setHeader('Content-Type', 'application/json; charset=utf-8');

        $this->curl->{$client->getMethod()}($this->gatewayUrl . $client->getRoute(), $parameters);

        return $this->successHandler();
    }

    private function successHandler()
    {
        $resp = $this->curl->getRawResponse();

        if ($this->format === 'json') {
            $view = json_decode($resp, true);
        }

        return $view ?? [];
    }

    private function exception($msg)
    {
        return [
            'code'    => 500,
            'msg'     => $msg,
            'payload' => []
        ];
    }
}
