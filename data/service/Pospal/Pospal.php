<?php
namespace data\service\Pospal;

use data\model\BcPospalUserRecordModel;
use data\model\UserModel as UserModel;
use data\service\BaseService;
use data\service\Promotion;


/**
 * 银豹对接
 */
class Pospal extends BaseService
{

    # todo  备注
    # customerNum number 会员号
    # customerUid 识别id
    # 手机号不是唯一值
    # 会员号是唯一址
    # 用户手机号 姓名可重复

    # ns_member_account  积分
    private $user;
    private $record;
    private $host;
    private $appId;
    private $appKey;
    private $userList;

    function __construct()
    {
        parent::__construct();
        $this->user     = new UserModel();
        $this->record   = new BcPospalUserRecordModel();
        $this->host     = 'https://area24-win.pospal.cn:443/pospal-api2/openapi/v1/';

        $this->userList = [];
        #正式
        $this->appId  = 'E1738E7DAB979D0F656A6159EB7433A0';
        $this->appKey = '557125669601679356';
    }

    public function getRecordInfo($user_tel){
        $condition['user_tel'] = $user_tel;
        $condition['status']   = 'success';
        $res                   = $this->record->getInfo($condition);
        return $res;
    }


    # 根据BC uid  查询银豹唯一标识
    public function getCustomerUidByUid($uid){
        $condition['uid']    = $uid;
        $condition['status'] = 'success';
        $res                 = $this->record->getInfo($condition, 'customer_uid');
        if(!$res) return false;
        return $res['customer_uid'];
    }

    # 根据银豹唯一标识  查询BC uid
    public function getUidByCustomerUid($CustomerUid){
        $condition['customer_uid']    = $CustomerUid;
        $condition['status']          = 'success';
        $res                 = $this->record->getInfo($condition, 'uid');
        if(!$res) return false;
        return $res['uid'];
    }


    /**
     * @param $customerUid  唯一标识id
     * @return mixed
     * 根据会员在银豹系统的唯一标识查询
     */
    public function getUserInfoByCustomerUid($customerUid)
    {
        $url = $this->host . 'customerOpenApi/queryByUid';
        $arr = [
            'appId'        => $this->appId,
            'customerUid'  => $customerUid,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    /**
     * @param $customerNum  会员号
     * @return mixed
     * 根据会员号查询会员
     */
    public function getUserInfoByCustomerNumber($customerNum)
    {
        $url = $this->host . 'customerOpenApi/queryByNumber';
        $arr = [
            'appId'        => $this->appId,
            'customerNum'  => $customerNum,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }


    /**
     * @param $tel  手机号
     * @return mixed
     * 根据会员手机号查询会员
     */
    public function getUserInfoByCustomerTel($tel)
    {
        $url = $this->host . 'customerOpenApi/queryBytel';
        $arr = [
            'appId'        => $this->appId,
            'customerTel'  => $tel,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    /**
     * @return mixed
     * 分页查询全部会员
     */
    public function getUserInfoPages()
    {
        $arr = array(
            "appId" => $this->appId,    // Pospal配置的访问凭证
        );

        $obj = $this->UserApi($arr);
        if (!$obj) return false;

        return $this->userList;
    }


    # 请求会员列表api参数 获取result
    private function UserApi($arr)
    {
        $jsondata  = json_encode($arr);
        $url       = $this->host . 'customerOpenApi/queryCustomerPages';
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        $arrRes    = json_decode($row, true);

        $this->pushUser($arrRes['data']['result']);

        if (count($arrRes['data']['result']) >= 100 && isset($arrRes['data']['postBackParameter'])) {
            $pageParam = $arrRes['data']['postBackParameter'];
            $arr['postBackParameter'] = [
                'parameterType'  => $pageParam['parameterType'],
                'parameterValue' => $pageParam['parameterValue'],
            ];
            $this->UserApi($arr);
        }

        return $arrRes;
    }

    private function pushUser($users)
    {

        foreach ($users as $v) {
            if (!in_array($v, $this->userList)) array_push($this->userList, $v);
        }

        return true;
    }



    # 创建用户
    public function createLoginUser($userInfo,$uid,$user_tel)
    {
        $url = $this->host . 'customerOpenApi/add';
        $arr = [
            'appId'        => $this->appId,
            'customerInfo' => $userInfo,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        $this->requestPospalRecord($row,$uid,$user_tel);
        return json_decode($row, true);
    }

    # 修改会员基本信息
    public function updateLoginUser($userInfo)
    {
        $url = $this->host . 'customerOpenApi/updateBaseInfo';
        $arr = [
            'appId'        => $this->appId,
            'customerInfo' => $userInfo,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    # 修改会员余额积分
    public function updateUserPoint($customerUid,$balanceIncrement,$pointIncrement){
        $url = $this->host . 'customerOpenApi/updateBalancePointByIncrement';
        $arr = [
            'appId'             => $this->appId,
            "customerUid"       => $customerUid,
            "balanceIncrement"  => $balanceIncrement,
            "pointIncrement"    => $pointIncrement,
            "dataChangeTime"    => date('Y-m-d h:i:s', time())
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }



    # 设置会员积分      todo ..... 暂不考虑线上积分
    public function setUserPoint($uid){
        # 获取积分

//        $customer_uid = $this->getCustomerUidByUid($uid);
//        $res = $this->getUserInfoByCustomerUid($customer_uid);
//        if($res){
//            $res['data']['point'];
//        }


        # 操作积分

        # 推送积分

    }



    // 模拟提交数据函数
    private function _request($url, $data, $signature)
    {
        $time = time();
        $curl = curl_init();// 启动一个CURL会话
        // 设置HTTP头
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "User-Agent: openApi",
            "Content-Type: application/json; charset=utf-8",
            "accept-encoding: gzip,deflate",
            "time-stamp: " . $time,
            "data-signature: " . $signature
        ));
        curl_setopt($curl, CURLOPT_URL, $url);         // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);        // Post提交的数据包

        curl_setopt($curl, CURLOPT_POST, 1);        // 发送一个常规的Post请求

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 获取的信息以文件流的形式返回
        $output = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话

        return $output; // 返回数据
    }

    # 添加日志
    private function requestPospalRecord($row,$uid,$user_tel){
        $res = json_decode($row, true);
        if($res['status'] == 'error'){
            $data = [
                'uid'              => $uid,                            # BC uid
                'user_tel'         => $user_tel,                       # BC 手机号码  唯一
                'status'           => $res['status'],                  # success or error
                'pospal_store_id'  => 1,                               # 线下门店id
                'err_code'         => $res['errorCode'],               # 错误码
                'err_msg'          => $res['messages'][0],             # 错误信息
                'create_time'      => date('Y-m-d H:i:s',time()),      # 创建时间
            ];
        }else{
            $data = [
                'uid'               => $uid,                            # BC uid
                'user_tel'          => $user_tel,                       # BC 手机号码  唯一
                'customer_uid'      => $res['data']['customerUid'],     # pospal 唯一识别id
                'pospal_store_id'   => 1,                               # 线下门店id
                'customer_num'      => $res['data']['number'],          # 会员号
                'status'            => $res['status'],                  # success or error
                'create_time'       => date('Y-m-d H:i:s',time()),      # 创建时间
            ];
        }
        \think\Db::name('bc_pospal_user_record')->insert($data);
    }


    # 查询访问量配置
    public function queryAccessTimes(){
        $url = $this->host . 'openApiLimitAccess/queryAccessTimes';
        $arr = [
            'appId'        => $this->appId,
        ];

        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    # 查询每日访问量
    public function queryDailyAccess(){
        $url = $this->host . 'openApiLimitAccess/queryDailyAccessTimesLog';
        $arr = [
            'appId'        => $this->appId,
            'beginDate'    => date("Y-m-d"),
            'endDate'      => date("Y-m-d",strtotime("+1 day"))
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }


    # 推送地址查询
    public function queryPushUrl(){
        $url = $this->host . 'openNotificationOpenApi/queryPushUrl';
        $arr = [
            'appId'        => $this->appId,
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    # 推送地址修改
    public function updatePushUrl(){
        $url = $this->host . 'openNotificationOpenApi/updatePushUrl';
        $arr = [
            'appId'        => $this->appId,
            "pushUrl"      => "https://www.bonnieclyde.cn/index.php?s=/api/pospal/pospalPush"
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    # 根据单据序列号查询
    public function queryTicketBySn($sn){
        $url = $this->host . 'ticketOpenApi/queryTicketBySn';
        $arr = [
            'appId'   => $this->appId,
            "sn"      => $sn
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }


    # 根据退款单查询原始单
    public function querySellTicketByRefunTicketSn($sn){
        $url = $this->host . 'ticketOpenApi/querySellTicketByRefunTicketSn';
        $arr = [
            'appId'          => $this->appId,
            "refunTcketSn"   => $sn
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }


    public function test($sn){
        $url = $this->host . 'ticketOpenApi/querySellTicketByRefunTicketSn';
        $arr = [
            'appId'          => $this->appId,
            "refunTcketSn"   => $sn
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    public function getAllProductsCategoryList()
    {
        $url       = $this->host . 'productOpenApi/queryProductCategoryPages';
        $arr       = [
            'appId' => $this->appId,
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }

    public function usePromotionCoupon()
    {
        $url       = 'https://area24-win.pospal.cn:443/pospal-api/api/auth/openapi/promotioncouponcode/use/';
        $arr       = [
            'appId' => $this->appId,
            'code'  => '00006',
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }


    public function test1()
    {
        $url       = $this->host . 'productOpenApi/queryProductByBarcode';
        $arr       = [
            'appId' => $this->appId,
            'barcode' => 721802179557
        ];
        $jsondata  = json_encode($arr);
        $signature = strtoupper(md5($this->appKey . $jsondata));
        $row       = $this->_request($url, $jsondata, $signature);
        return json_decode($row, true);
    }




    # 获取
}
