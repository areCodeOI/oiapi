<?php
require __DIR__ . '/vendor/autoload.php';

/*
$client = new Oiapi\Client('aword');

echo Oiapi\Utils::json($client->curl()->json());
echo PHP_EOL;
echo Oiapi\Utils::json($client->get('sickl')->json());
*/

$Manager = Oiapi\Manager::getInstance();
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
