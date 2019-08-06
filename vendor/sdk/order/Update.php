<?php
namespace order;
use security\Base;
use security\Encode;
const SKEY = 'like_shopal';     # security-key

define('OMS_UPDATE_ORDER_HOST', OMS_SERVICE_HOST.'/api-rest/bc/update_order');

class Update extends Base{

    protected $request_url = OMS_UPDATE_ORDER_HOST;

    public static function _instance($option)
    {
        if (self::$_self !== null) {
            return self::$_self;
        }
        try {
            return self::$_self = new Update($option);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    /**
     * @param $tid :订单号
     * @param $param :Json格式参数
     * @return array|mixed
     */
    public function push($tid, $param){
        if(!$tid || !$param) return [
                'code' => '200',
                'msg' => '参数错误',
            ];
        $res = $this->curl_post(['tid' => $tid, 'param'=> json_encode($param)]);
        return $res;
    }
}