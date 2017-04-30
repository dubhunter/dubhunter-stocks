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
	$symbol = strtoupper($symbol);
	$client = new Client();
	$yql = 'select Symbol, LastTradePriceOnly, Change, PercentChange, DaysLow, DaysHigh, Volume from yahoo.finance.quotes where symbol = "' . $symbol . '"';
	$response = $client->get(
		'https://query.yahooapis.com/v1/public/yql',
		[
			'query' => [
				'q' => $yql,
				'format' => 'json',
				'env' => 'store://datatables.org/alltableswithkeys',
			],
		]
	);
	if ($response->getStatusCode() != 200) {
		return Json::error();
	}
	$data = json_decode($response->getBody(), true);
	if (!isset($data['query'])) {
		return Json::error();
	}
	if (!isset($data['query']['results'])) {
		return Json::error();
	}
	if (!isset($data['query']['results']['quote'])) {
		return Json::notFound();
	}
	$fields = $data['query']['results']['quote'];
	return Json::ok([
		'symbol' => $fields['Symbol'],
		'price' => round(floatval($fields['LastTradePriceOnly']), 2),
		'change' => round(floatval($fields['Change']), 2),
		'change_percent' => round(floatval($fields['PercentChange']), 2),
		'day_high' => round(floatval($fields['DaysHigh']), 2),
		'day_low' => round(floatval($fields['DaysLow']), 2),
		'volume' => intval($fields['Volume']),
	]);
});

$app->handle();
