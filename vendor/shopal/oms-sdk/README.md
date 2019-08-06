shopal SDK for PHP  
---

![](https://img.shields.io/badge/oms.sdk-v.1.0-brightgreen.svg)

SDK存放在私有仓库 安装需要指定仓库及gitlab的ssh配置 再修改composer.json文件 

```json
{
"repositories": [
    {
      "type": "vcs",
      "url": "ssh://git@dev03.ushopal.com:10022/ChenChuanFeng/oms-sdk.git"
    }
  ]
}
```

## 下载:

```bash
composer require shopal/oms-sdk
composer require shopal/oms-sdk v1.0.2 # 指定版本
```

## 使用

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use OMS\Sdk\Oms;

# 参数1位服务器地址 参数2为请求客户端编码
$oms = new Oms('four-li.com', 'Yaya');

$res = $oms->getRawResponse(
    (new \OMS\Sdk\Applet\OrderCreate())
        ->setId(1001)
);

var_dump($res);

$client = new \OMS\Sdk\Applet\Employee();

$res = $oms->getRawResponse($client);

var_dump($res);
```

> 更多请求方法 查看src/Example/*.php 或开放平台调试工具 

## 测试

```bash
./vendor/bin/phpunit tests/Oms/Tests/
```
