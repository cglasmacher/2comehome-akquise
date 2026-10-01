<?php

namespace App\Services\OnOffice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class Client
{
    public function call(string $action, string $resource, array $parameters): array
    {
        if (! config('onoffice.enabled') || ! config('onoffice.token') || ! config('onoffice.secret')) {
            throw new \RuntimeException('onOffice ist noch nicht konfiguriert.');
        }
        $timestamp = time();
        $actionId = 'urn:onoffice-de-ns:smart:2.5:smartml:action:'.$action;
        $hmac = base64_encode(hash_hmac('sha256', $timestamp.config('onoffice.token').$resource.$actionId, config('onoffice.secret'), true));
        try {
            $response = Http::acceptJson()->timeout(25)->withOptions(['allow_redirects' => false])->post(config('onoffice.url'), [
                'token' => config('onoffice.token'), 'request' => ['actions' => [['timestamp' => $timestamp, 'hmac_version' => 2, 'hmac' => $hmac, 'actionid' => $actionId, 'resourceid' => '', 'identifier' => '', 'resourcetype' => $resource, 'parameters' => $parameters]]]]);
        } catch (ConnectionException $e) {
            throw new ApiException(true);
        }
        if (! $response->successful()) {
            throw new ApiException(true);
        }
        $body = $response->json();
        $result = $body['response']['results'][0] ?? null;
        if (! is_array($result) || ! isset($result['status']['errorcode'])) {
            throw new ApiException(true);
        }
        if ((int) ($body['status']['errorcode'] ?? 0) !== 0 || (int) $result['status']['errorcode'] !== 0) {
            throw new ApiException(false);
        }

        return $result['data']['records'] ?? [];
    }

    public function create(string $resource, array $parameters): int
    {
        $records = $this->call('create', $resource, $parameters);
        $id = $records[0]['id'] ?? null;
        if (! $id) {
            throw new ApiException(true);
        }

        return (int) $id;
    }
}
