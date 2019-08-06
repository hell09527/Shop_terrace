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

class RefundSocket implements ClientInterface
{
    /** @var string */
    private $id;

    /**
     * @param string $id
     * @return RefundSocket
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getApiParameters()
    {
        return ['id' => $this->id];
    }

    public function getMethod()
    {
        return 'post';
    }

    public function getRoute()
    {
        return 'api-rest/applet/refund.socket';
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
