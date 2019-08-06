<?php
set_time_limit(0);
define('OMS_SDK_PATH',__DIR__.'/');
require(OMS_SDK_PATH . 'Autoload.php');

$loader = new Autoload();
$loader->run();
