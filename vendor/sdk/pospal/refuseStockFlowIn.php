<?php
namespace pospal;
use security\Base;
use security\Encode;
const SKEY = 'like_shopal';     # security-key
define('OMS_POSPAL_HOST','https://oms.b2c.omnixdb.com'.'/api-rest/bc/refuse_stock_flow_in');

class refuseStockFlowIn extends Base{
    protected $request_url = OMS_POSPAL_HOST;

    public static function _instance($option)
    {
        if (self::$_self !== null) {
            return self::$_self;
        }

        try {
            return self::$_self = new refuseStockFlowIn($option);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    public function push($stockFlowId){
        if(!$stockFlowId) return [
            'code' => '200',
            'msg' => '参数data不可为空',
        ];

        $stockFlowId = Encode::encode($stockFlowId);
        $res = $this->curl_post(['stockFlowId' => $stockFlowId,]);
        $this->push_log($stockFlowId,$res);
        return $res;
    }
}