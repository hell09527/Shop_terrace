<?php
namespace security;

define('OMS_LOG_HOST',OMS_SERVICE_HOST.'/api-rest/bc/push_log');

class Base{
    protected $unix;
    protected $token;
    protected $id;
    protected static $_self = null;
    protected $log_push_url = OMS_LOG_HOST;

    public function __construct($option)
    {
        $this->token = $option['token'];
        $this->unix = time();
    }

    protected function push_log($id,$msg){
        $param = [
            'entry_type' => get_called_class(),
            'push_id' => $id,
            'platform' => 'BC',
            'msg' => $msg,
        ];
        return ;
//        $this->curl_post($param,$this->log_push_url);
    }

    protected function gen_sign(){
        asort($_sign = [
            $this->token,
            $this->unix,
        ],true);

        return trim(md5(trim(implode('__SHOPAL__', $_sign))));
    }

    protected function generate_uri(){
        $uri = $this->request_url."?sign=".$this->gen_sign();
        $uri .= "&token=".$this->token;
        $uri .= "&unix=".$this->unix;
        return $uri;
    }

    protected function curl_post($array,$url=null)
    {
        $url = $url==null?$this->generate_uri():$url;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //设置提交的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置post数据
        $post_data = $array;
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //获得数据并返回
        return $data;
    }
}