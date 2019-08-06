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

namespace OMS\Sdk;


interface ClientInterface
{
    /** @return bool @throws \Exception */
    public function check();

    /** @return string */
    public function getRoute();

    /** @return string */
    public function getMethod();

    /** @return array|object */
    public function getApiParameters();
}
