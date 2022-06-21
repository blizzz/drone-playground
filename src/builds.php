<?php

require_once(__DIR__ . '/../vendor/autoload.php');

const DRONE_HOST = 'https://drone.nextcloud.com/';

$apiKey = \file_get_contents(__DIR__ . '/../.drone_api_key');
$client = new \GuzzleHttp\Client();
$response = $client->request('GET', DRONE_HOST . 'api/user/repos', ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);

$stream = $response->getBody();
$repoList = \json_decode($stream->getContents(), true);

$statusList = [];
foreach ($repoList as $repoItem) {
	if(!$repoItem['active']) {
		continue;
	}

	$repoWasPrinted = false;

	$endpoint = sprintf('api/repos/%s/%s/builds', $repoItem['namespace'], $repoItem['name']);
	$response = $client->request('GET', DRONE_HOST . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);
	$stream = $response->getBody();
	$buildList = \json_decode($stream->getContents(), true);
	foreach ($buildList as $buildItem) {
		if (!isset($statusList[$buildItem['status']])) {
			$statusList[$buildItem['status']] = 1;
		} else {
			$statusList[$buildItem['status']]++;
		}

		if (!in_array($buildItem['status'], ['running', 'pending'])) {
			continue;
		}

		if (!$repoWasPrinted) {
			printf("\n%d\t%s/%s\n", $repoItem['id'], $repoItem['namespace'], $repoItem['name']);
			$repoWasPrinted = true;
		}

		$lineBreak = strpos($buildItem['message'], "\n");
		$title = substr($buildItem['message'],0, min($lineBreak ?: 60, 60));
		$title = str_replace(array("\r", "\n"), '', $title);
		printf("\t- %d\t%s\t%s\n\t\t%s\n", $buildItem['number'], $buildItem['event'], $buildItem['status'], $title);
	}
}

print(PHP_EOL);
foreach ($statusList as $status => $count) {
	printf("%s\t\t%d\n", $status, $count);
}
