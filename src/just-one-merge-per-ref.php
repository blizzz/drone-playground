<?php

require_once(__DIR__ . '/../vendor/autoload.php');

const DRONE_HOST = 'https://drone.nextcloud.com/';

$apiKey = \file_get_contents(__DIR__ . '/../.drone_api_key');
$client = new \GuzzleHttp\Client();
$response = $client->request('GET', DRONE_HOST . 'api/user/repos', ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);

$stream = $response->getBody();
$repoList = \json_decode($stream->getContents(), true);

class BuildInfo {
	public string $repo;
	public int $number;
	public string $ref;

	public function __construct(string $repo, int $number, string $ref) {
		$this->repo = $repo;
		$this->number = $number;
		$this->ref = $ref;
	}
}

$buildsToStop = [];
$latestMergeBuilds = [];

foreach ($repoList as $repoItem) {
	if(!$repoItem['active']) {
		continue;
	}

	$endpoint = sprintf('api/repos/%s/%s/builds', $repoItem['namespace'], $repoItem['name']);
	$response = $client->request('GET', DRONE_HOST . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);
	$stream = $response->getBody();
	$buildList = \json_decode($stream->getContents(), true);
	foreach ($buildList as $buildItem) {
		if (!in_array($buildItem['status'], ['running', 'pending'])
			|| ($buildItem['event'] !== 'push')
			|| strpos($buildItem['message'], 'Merge pull request') === false )
		{
			continue;
		}

		if (!isset($latestMergeBuilds[$repoItem['slug']])) {
			$latestMergeBuilds[$repoItem['slug']] = [];
		}
		if (!isset($latestMergeBuilds[$repoItem['slug']][$buildItem['ref']])) {
			$latestMergeBuilds[$repoItem['slug']][$buildItem['ref']] = $buildItem['number'];
			continue;
		}
		if ($buildItem['number'] > $latestMergeBuilds[$repoItem['slug']][$buildItem['ref']]) {
			$buildsToStop[] = new BuildInfo($repoItem['slug'], $latestMergeBuilds[$repoItem['slug']], $buildItem['ref']);
			$latestMergeBuilds[$repoItem['slug']][$buildItem['ref']] = $buildItem['number'];
		} else if ($buildItem['number'] < $latestMergeBuilds[$repoItem['slug']][$buildItem['ref']]) {
			$buildsToStop[] = new BuildInfo($repoItem['slug'], $buildItem['number'], $buildItem['ref']);
		}
	}
}

print("Keeping\n");
foreach ($latestMergeBuilds as $repo => $build) {
	foreach ($build as $ref => $number) {
		printf("%s\t%s\t=> %d\n", $repo, $ref, $number);
	}
}
print(PHP_EOL . PHP_EOL);

print("Stopping\n");
foreach ($buildsToStop as $build) {
	/** @var $build BuildInfo */
	printf("%s\t%s\t=> %d\n", $build->repo, $build->ref, $build->number);

	$endpoint = sprintf('api/repos/%s/builds/%d', $build->repo, $build->number);
	$client->request('DELETE', DRONE_HOST . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);
}



