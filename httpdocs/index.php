<?php

require '../vendor/autoload.php';

use Dubhunter\Talon\http\Response\Json;
use GuzzleHttp\Client;
use Phalcon\Mvc\Micro;

define('IPHONE_USER_AGENT', 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_3 like Mac OS X) AppleWebKit/601.1 (KHTML, like Gecko) CriOS/52.0.2743.84 Mobile/13G34 Safari/601.1.46');

$app = new Micro();

$app->notFound(function () {
	return Json::notFound();
});

$app->get('/{symbol}', function ($symbol) {
	$client = new Client([
		'headers' => [
			'User-Agent' => IPHONE_USER_AGENT,
		],
	]);
	$response = $client->get('http://finance.yahoo.com/webservice/v1/symbols/' . $symbol . '/quote?format=json&view=detail');
	if ($response->getStatusCode() != 200) {
		return Json::error();
	}
	$data = json_decode($response->getBody(), true);
	if (!isset($data['list'])) {
		return Json::error();
	}
	if (count($data['list']['resources']) === 0) {
		return Json::notFound();
	}
	$fields = $data['list']['resources'][0]['resource']['fields'];
	return Json::ok([
		'symbol' => $fields['symbol'],
		'price' => round($fields['price'], 2),
		'change' => round($fields['change'], 2),
		'change_percent' => round($fields['chg_percent'], 2),
		'day_high' => round($fields['day_high'], 2),
		'day_low' => round($fields['day_low'], 2),
		'volume' => $fields['volume'],
	]);
});

$app->handle();
