<?php
namespace employee;
use security\Base;
use security\Encode;

const SKEY = 'like_shopal';     # security-key
//define('OMS_EMPLOYEE_HOST',OMS_SERVICE_HOST.'/api-rest/bc/get_employee_list');
define('OMS_EMPLOYEE_HOST',OMS_SERVICE_HOST.'/api-rest/bc/get_employee');

class Push extends Base{
    protected $request_url = OMS_EMPLOYEE_HOST;

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

    # 湖区员工列表
    public function getEmployeeList(){
        $res = $this->curl_post([]);
        return $res;
    }
}