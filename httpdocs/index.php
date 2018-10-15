<?php

require '../vendor/autoload.php';

use Dubhunter\Talon\Http\Response\Json;
use GuzzleHttp\Client;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Micro;

$di = new FactoryDefault();

$di->set('config', function () {
	$config = new \Phalcon\Config\Adapter\Ini('../conf/config.ini');
	return $config;
});

$app = new Micro($di);

$app->notFound(function () {
	return Json::notFound();
});

$app->get('/{symbol}', function ($symbol) use ($app) {
	$client = new Client();
	$response = $client->get(
		'https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=TWLO&apikey=UTA88F40H4IC68YB',
		[
			'query' => [
				'apikey' => $app->getDI()->get('config')->aws->key,
				'function' => 'GLOBAL_QUOTE',
				'symbol' => strtoupper($symbol),
			],
		]
	);
	if ($response->getStatusCode() != 200) {
		return Json::error();
	}
	$data = json_decode($response->getBody(), true);
	if (!is_array($data)) {
		return Json::error();
	}
	$data = array_values($data);
	if (!isset($data[0])) {
		return Json::notFound();
	}
	$fields = [];
	foreach ($data[0] as $k => $v) {
		$fields[str_replace(' ', '_', preg_replace('/^[0-9]+\. /', '', $k))] = $v;
	}
	return Json::ok([
		'symbol' => $fields['symbol'],
		'price' => round(floatval($fields['price']), 2),
		'change' => round(floatval($fields['change']), 2),
		'change_percent' => round(floatval($fields['change_percent']), 2),
		'day_open' => round(floatval($fields['open']), 2),
		'day_high' => round(floatval($fields['high']), 2),
		'day_low' => round(floatval($fields['low']), 2),
		'volume' => round(floatval($fields['volume']), 2),
	]);
});

$app->handle();
