# oiapi

地址：[https://oiapi.net/](https://oiapi.net)

----


## 安装说明

在目录中运行以下指令：

```shell

composer require oiapi/oiapi

```

如出现类似提示：

```text

Could not find package oiapi/oiapi. It was however found via repository search, which i
ndicates a consistency issue with the repository.

```

你可以输入

```shell

composer config -g --unset repos.packagist

```

以便于切换 `composer` 的源为官方源，最好提前备份。


----

## 使用示例


```php

<?php
require __DIR__ . '/vendor/autoload.php';

/**
 * 直接使用 Oiapi\Client 类
 */

/*
$client = new Oiapi\Client('aword');

echo Oiapi\Utils::json($client->curl()->json());
echo PHP_EOL;
echo Oiapi\Utils::json($client->get('sickl')->json());
*/

/**
 * 使用 Oiapi\Manager 类 (推荐)
 * 单列类，能够能加简便的使用
 */

$Manager = Oiapi\Manager::getInstance();  // 单列模式
/*
echo PHP_EOL;
echo '============Manager===========';
echo PHP_EOL;
echo $Manager->go(1);
echo PHP_EOL;
echo $Manager->go('sickl');
echo PHP_EOL;
echo $Manager->go('一言');
*/
echo $Manager->go('啾啾');

```

> Tips：可以查看 [test.php](test.php)


----


## 参数说明

在 `Manager` 中 含有一些方法

```php

/**
 * @param int $id        api ID
 * @return object
 */

Oiapi\Manager::getById(int $id);  //通过id获取接口信息，返回值为object

/**
 * @param string $name        api名字
 * @return object
 */
Oiapi\Manager::getByName(string $name);  //通过名字获取接口信息，返回值为object

/**
 * @param int | string $api    访问的api，可以是id与名称
 * @param mixed $data        请求数据，使用POST时填写数据
 * @param array $route        路由参数[a, b, c]
 * @return mixed
 */
Oiapi\Manager::go(int | string $api, mixed $data = null, array $route = []);  //通过名字获取接口信息，返回值为object


```

以上方法可以实例化之后调用

