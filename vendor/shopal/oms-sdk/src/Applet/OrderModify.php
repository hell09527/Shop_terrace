<?php
/**
 * echo "██╗   ██╗███████╗██╗  ██╗ ██████╗ ██████╗  █████╗ ██╗         ";
 * echo "██║   ██║██╔════╝██║  ██║██╔═══██╗██╔══██╗██╔══██╗██║         ";
 * echo "██║   ██║███████╗███████║██║   ██║██████╔╝███████║██║         ";
 * echo "██║   ██║╚════██║██╔══██║██║   ██║██╔═══╝ ██╔══██║██║         ";
 * echo "╚██████╔╝███████║██║  ██║╚██████╔╝██║     ██║  ██║███████╗    ";
 * echo " ╚═════╝ ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝    ";
 * echo "                                                              ";
 * 日期: 2019/7/7
 * 作者: four-li
 */

namespace OMS\Sdk\Applet;


use OMS\Sdk\ClientInterface;

class OrderModify implements ClientInterface
{
    /** @var string */
    private $tid;

    # 支持修改收货地址相关信息
    /** @var string */
    private $province_name = null;

    /** @var string */
    private $city_name = null;

    /** @var string */
    private $district_name = null;

    /** @var string */
    private $receiver_address = null;

    /** @var string */
    private $receiver_mobile = null;

    /** @var string */
    private $receiver_zip = null;

    /** @var string */
    private $receiver_name = null;

    # 修改身份证信息
    /** @var string */
    private $card_no = null;

    /** @var string */
    private $card_name = null;

    /**
     * @param string $tid
     * @return OrderModify
     */
    public function setTid(string $tid): OrderModify
    {
        $this->tid = $tid;
        return $this;
    }

    /**
     * @param string $province_name
     * @return OrderModify
     */
    public function setProvinceName(string $province_name): OrderModify
    {
        $this->province_name = $province_name;
        return $this;
    }

    /**
     * @param string $city_name
     * @return OrderModify
     */
    public function setCityName(string $city_name): OrderModify
    {
        $this->city_name = $city_name;
        return $this;
    }

    /**
     * @param string $district_name
     * @return OrderModify
     */
    public function setDistrictName(string $district_name): OrderModify
    {
        $this->district_name = $district_name;
        return $this;
    }

    /**
     * @param string $receiver_address
     * @return OrderModify
     */
    public function setReceiverAddress(string $receiver_address): OrderModify
    {
        $this->receiver_address = $receiver_address;
        return $this;
    }

    /**
     * @param string $receiver_mobile
     * @return OrderModify
     */
    public function setReceiverMobile(string $receiver_mobile): OrderModify
    {
        $this->receiver_mobile = $receiver_mobile;
        return $this;
    }

    /**
     * @param string $receiver_zip
     * @return OrderModify
     */
    public function setReceiverZip(string $receiver_zip): OrderModify
    {
        $this->receiver_zip = $receiver_zip;
        return $this;
    }

    /**
     * @param string $receiver_name
     * @return OrderModify
     */
    public function setReceiverName(string $receiver_name): OrderModify
    {
        $this->receiver_name = $receiver_name;
        return $this;
    }

    /**
     * @param string $card_no
     * @return OrderModify
     */
    public function setCardNo(string $card_no): OrderModify
    {
        $this->card_no = $card_no;
        return $this;
    }

    /**
     * @param string $card_name
     * @return OrderModify
     */
    public function setCardName(string $card_name): OrderModify
    {
        $this->card_name = $card_name;
        return $this;
    }

    

    public function getApiParameters()
    {
        return [
            'tid'               => $this->tid,
            'receiver_state'    => $this->province_name,
            'receiver_city'     => $this->city_name,
            'receiver_district' => $this->district_name,
            'receiver_address'  => $this->receiver_address,
            'receiver_mobile'   => $this->receiver_mobile,
            'receiver_zip'      => $this->receiver_zip,
            'receiver_name'     => $this->receiver_name,
            'card_no'           => $this->card_no,
            'card_name'         => $this->card_name,
        ];
    }

    public function getMethod()
    {
        return 'post';
    }

    public function getRoute()
    {
        return 'api-rest/applet/order.modify';
    }

    public function check()
    {
        // TODO: Implement check() method.

        if (true) {

        } else {

            throw new \Exception('参数错误');
        }

    }
}
