<?php
/**
 * echo "██╗   ██╗███████╗██╗  ██╗ ██████╗ ██████╗  █████╗ ██╗         ";
 * echo "██║   ██║██╔════╝██║  ██║██╔═══██╗██╔══██╗██╔══██╗██║         ";
 * echo "██║   ██║███████╗███████║██║   ██║██████╔╝███████║██║         ";
 * echo "██║   ██║╚════██║██╔══██║██║   ██║██╔═══╝ ██╔══██║██║         ";
 * echo "╚██████╔╝███████║██║  ██║╚██████╔╝██║     ██║  ██║███████╗    ";
 * echo " ╚═════╝ ╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝    ";
 * echo "                                                              ";
 * 日期: 2019/7/6
 * 作者: four-li
 */

namespace OMS\Sdk\Applet;

use OMS\Sdk\ClientInterface;

class Employee implements ClientInterface
{
    public function getApiParameters()
    {
        return [];
    }

    public function getMethod()
    {
        return 'get';
    }

    public function getRoute()
    {
        return 'api-rest/applet/employee.list';
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
