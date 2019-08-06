<?php

namespace data\service\Order;
/**
//$client = Runsa::instance ();
# 添加会员
$memberInfo = [
'phone'    => '13971777435',
'cstName'  => '撒浪嘿呦',        #sys_user    nick_name
'realName' => '陈传烽',          #ns_member   member_name
'cstSrc'   => 'SIT',
'srcVal'   => 'BC0001',
'sex'      => 1,                #sys_user    sex
'email'    => mt_rand (10000, 99999) . '@zz.com',         #sys_user    user_email
];
//$client->addMember ($memberInfo);

# 更新会员等级
$updLevel = [
'customer' => [
'id' => 100000000005
],
'remark'   => 'test-str',
'account' => [
'id' => 2,
],
'valDate'=>"2019-07-31 23:59:59",
'rank' =>[
'rankName' => null,
'id' => 'B',            # 降级这里参数是A
'rankIndex' =>2,        # 降级这里参数是1
]
];
$client->updLevel($updLevel);

$updataMemberInfo = [
'cstId'    => 100000000001,
'cstName'  => '撒浪嘿呦',
'realName' => '陈传烽',
'cstType'  => 0,
//    'regTime' =>
];
//$client->updateMember ($updataMemberInfo);

$findMemberInfo = [
'phone' => '13971777435',
];

//$client->findMember ($findMemberInfo);
 */
class RunSa
{
    private $remoteService = 'http://hd1vpc03.runsasoft.com:16484/';
    private $apiId = 'BC';
    private $apiSecret = '206d662c4f724c2e9dff76da90539c95';
    private $target = 'BC0001';
    private static $self;
    private $requestFullUrl;


    private function __construct()
    {
        // todo
        #$this->validApi();
    }

    public function addMember($memberInfo)
    {
        $path = 'crm-rest/customer/createMember';
        return $this->setFullUrl($path)->requset(json_encode($memberInfo));
    }

    public function updLevel($updLevel)
    {
        $path = 'crm-rest/customer/rankAdjustDetail/add';
        return $this->setFullUrl($path)->requset(json_encode($updLevel));
    }

    public function updateMember($updateMemberInfo)
    {
        $path = 'crm-rest/customer/modify';
        return $this->setFullUrl($path)->requset(json_encode($updateMemberInfo));
    }

    public function findMember($findMemberInfo)
    {
        $path = 'crm-rest/customer/info';
        return $this->setFullUrl($path)->requset(json_encode($findMemberInfo));
    }

    private function validApi()
    {
        $path = 'crm-rest/common/validApiTopParam';
        $parma = [
            'apiId' => $this->apiId,
            'target' => $this->target,
        ];
        $validRes = $this->setFullUrl($path, false)->requset(json_encode($parma));
        if (!isset($validRes) || json_decode($validRes[1], true)['code'] != '20000' || json_decode($validRes[1], true)['content']['valid'] != 'true') {
            $this->debug($validRes);
        }

        return;
    }

    protected function setFullUrl($path, $hasCommonParam = true)
    {
        $this->requestFullUrl = $this->remoteService . $path;

        if ($hasCommonParam) {
            $ts = time();
            $ran = strtoupper(substr(md5(uniqid(microtime(true), true) . mt_rand(100, 999)), 1, 10));
            $param = [
                'apiId' => $this->apiId,
                'nonce' => "{$ran}",
                'timestamp' => "{$ts}",
                'target' => $this->target,
                'apiSecret' => $this->apiSecret
            ];
            sort($param);
            $sign = md5(implode('', $param));

            $getParam = [
                'apiId' => $this->apiId,
                'apiSign' => $sign,
                'nonce' => "{$ran}",
                'timestamp' => "{$ts}",
                '_type' => 'json',
                'target' => $this->target,
            ];
            $getParamStr = $this->createLinkstringUrlencode($getParam);

            $this->requestFullUrl .= '?' . $getParamStr;
        }

        return $this;
    }

    function requset($jsonString)
    {
        if (!$this->requestFullUrl) return ['请求地址错误'];
        $url = $this->requestFullUrl;
        $this->requestFullUrl = null;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json; charset=utf-8",
                "Content-Length: " . strlen($jsonString)]
        );
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [$return_code, $return_content];
    }

    private function createLinkstringUrlencode($para)
    {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg .= $key . "=" . urlencode($val) . "&";
        }
        $arg = substr($arg, 0, count($arg) - 2);
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return $arg;
    }

    static public function instance()
    {
        if (self::$self === null) {
            self::$self = new Runsa();
        }

        return self::$self;
    }

}