<?php

require '../vendor/autoload.php';

use Dubhunter\Talon\Http\Response\Json;
use GuzzleHttp\Client;
use Phalcon\Cache\Frontend\Data as CacheFrontend;
use Phalcon\Cache\Backend\Libmemcached as Memcache;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Micro;

$di = new FactoryDefault();

$di->set('config', function () {
	$config = new \Phalcon\Config\Adapter\Ini('../conf/config.ini');
	return $config;
});

/**
 * Setting up the cache
 */
$di->set('cache', function() use ($di) {
	$memcacheConfig = $di->get('config')->get('memcache');
	$frontCache = new CacheFrontend(array(
		'lifetime' => $memcacheConfig->lifetime,
	));
	return new Memcache($frontCache, array(
		'servers' => array(
			array(
				'host' => $memcacheConfig->host,
				'port' => $memcacheConfig->port,
				'weight' => 1,
			),
		),
		'client' => array(
			Memcached::OPT_PREFIX_KEY => $memcacheConfig->prefix,
		),
	));
}, true);

function cacheKey($symbol) {
	return 'STOCKS_' . strtoupper($symbol);
}

$app = new Micro($di);

$app->notFound(function () {
	return Json::notFound();
});

$app->get('/{symbol}', function ($symbol) use ($app) {
	/** @var Memcache $cache */
	$cache = $app->getDI()->get('cache');
	$cacheKey = cacheKey($symbol);
	$fields = $cache->get($cacheKey);
	if (!$fields) {
		$client = new Client();
		$response = $client->get(
			'https://www.alphavantage.co/query',
			[
				'query' => [
					'apikey' => $app->getDI()->get('config')->alphavantage->key,
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
		if (isset($data['Note'])) {
			return Json::error($data);
		}
		$data = array_values($data);
		if (!isset($data[0])) {
			return Json::notFound();
		}
		$fields = [];
		foreach ($data[0] as $k => $v) {
			$fields[str_replace(' ', '_', preg_replace('/^[0-9]+\. /', '', $k))] = $v;
		}
		if (!isset($fields['symbol'])) {
			error_log($response->getBody());
			return Json::error();
		}
		$cache->save($cacheKey, $fields, $app->getDI()->get('config')->memcache->lifetime);
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
