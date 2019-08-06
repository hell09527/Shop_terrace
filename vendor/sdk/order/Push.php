<?php
namespace order;
use security\Base;
use security\Encode;
const SKEY = 'like_shopal';     # security-key
//define('OMS_ORDER_HOST',OMS_SERVICE_HOST.'/api/push_order');
define('OMS_ORDER_HOST',OMS_SERVICE_HOST.'/api-rest/bc/create_order');

class Push extends Base{
    protected $request_url = OMS_ORDER_HOST;

    public static function _instance($option)
    {
        if (self::$_self !== null) {
            return self::$_self;
        }

        try {
            return self::$_self = new Push($option);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    public function push($id){
        $oid = $id;
        if(!$id) return [
                'code' => '200',
                'msg' => '参数id不可为空',
            ];

        $id = Encode::encode($id);
        $res = $this->curl_post(['id' => $id,]);
        $this->push_log($oid,$res);
        return $res;
    }
}