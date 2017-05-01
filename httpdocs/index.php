<?php

require '../vendor/autoload.php';

use Dubhunter\Talon\Http\Response\Json;
use GuzzleHttp\Client;
use Phalcon\Mvc\Micro;

$app = new Micro();

$app->notFound(function () {
	return Json::notFound();
});

$app->get('/{symbol}', function ($symbol) {
	$symbol = 'twlo';
	$client = new Client();
	$response = $client->get(
		'http://finance.google.com/finance/info',
		[
			'query' => [
				'q' => strtoupper($symbol),
				'client' => 'ig',
			],
		]
	);
	if ($response->getStatusCode() != 200) {
		return Json::error();
	}
	$body = str_replace('//', '', $response->getBody());
	$data = json_decode($body, true);
	if (!is_array($data)) {
		return Json::error();
	}
	if (!isset($data[0])) {
		return Json::notFound();
	}
	$fields = $data[0];
	return Json::ok([
		'symbol' => $fields['t'],
		'price' => round(floatval($fields['l']), 2),
//		'time' => round(strtotime($fields['lt']), 2),
		'change' => round(floatval($fields['c']), 2),
		'change_percent' => round(floatval($fields['cp']), 2),
		'day_high' => 0,
		'day_low' => 0,
		'volume' => 0,
	]);
});

$app->handle();
