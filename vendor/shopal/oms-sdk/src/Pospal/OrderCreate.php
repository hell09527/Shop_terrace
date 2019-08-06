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

namespace OMS\Sdk\Pospal;


use OMS\Sdk\ClientInterface;

class OrderCreate implements ClientInterface
{
    /** @var string */
    private $store_code;

    /** @var string */
    private $sn;

    public function getApiParameters()
    {
        return [
            'store_code' => $this->store_code,
            'sn'         => $this->sn
        ];
    }

    public function getMethod()
    {
        return 'post';
    }

    public function getRoute()
    {
        return 'api-rest/pospal/order.create';
    }

    public function check()
    {
        // TODO: Implement check() method.

        if (
            !$this->store_code || !$this->sn
        ) {

            throw new \Exception('参数错误');
        }

    }

    /**
     * @param string $store_code
     * @return OrderCreate
     */
    public function setStoreCode(string $store_code): OrderCreate
    {
        $this->store_code = $store_code;
        return $this;
    }

    /**
     * @param string $sn
     * @return OrderCreate
     */
    public function setSn(string $sn): OrderCreate
    {
        $this->sn = $sn;
        return $this;
    }
}
