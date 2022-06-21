<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

require_once(__DIR__ . '/../vendor/autoload.php');

const DRONE_HOST = 'https://drone.nextcloud.com/';

$repo = $argv[1];
$buildId = (int)$argv[2];

if (strpos($repo, '/') === false) {
	error_log('Invalid repo' . $repo);
	exit(1);
}

if ($buildId === 0) {
	error_log('Invalid build id ' . $argv[2]);
	exit(2);
}

$apiKey = \file_get_contents(__DIR__ . '/../.drone_api_key');
$client = new \GuzzleHttp\Client();
$endpoint = sprintf('api/repos/%s/builds/%d', $repo, $buildId);

// restart is documented with POST, but it would not stop a running process
$response = $client->request('DELETE', DRONE_HOST . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);
$response = $client->request('POST', DRONE_HOST . $endpoint, ['headers' => ['Authorization' => 'Bearer ' . $apiKey]]);

if ($response->getStatusCode() > 299) {
	print($response->getStatusCode() . PHP_EOL);
	exit(3);
}
print($response->getStatusCode() . PHP_EOL);
