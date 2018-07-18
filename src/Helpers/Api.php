<?php

namespace Arionum\Arionum\Helpers;

use Arionum\Arionum\Config;
use Arionum\Arionum\Traits\HasConfig;

/**
 * Class Api
 */
class Api
{
    use HasConfig;

    /**
     * Api constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->setConfig($config);
    }

    /**
     * Post data to a URL endpoint (usually a peer).
     * The data is an array that is JSON encoded and sent as a data parameter.
     * @param string $url
     * @param array  $data
     * @param int    $timeout
     * @param bool   $debug
     * @return bool
     * @throws \Exception
     */
    public function post(string $url, array $data = [], int $timeout = 60, bool $debug = false): bool
    {
        if ($debug) {
            echo PHP_EOL.'Peer post: '.$url.PHP_EOL;
        }

        $postData = http_build_query(
            [
                'data' => json_encode($data),
                "coin" => $this->config->get('coin'),
            ]
        );

        $options = [
            'http' =>
                [
                    'timeout' => $timeout,
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postData,
                ],
        ];

        $context = stream_context_create($options);

        $peerResponse = file_get_contents($url, false, $context);

        if ($debug) {
            echo PHP_EOL.'Peer response: '.$peerResponse.PHP_EOL;
        }

        $result = json_decode($peerResponse, true);

        // The function will return false if something goes wrong
        if ($result['status'] !== 'ok' || $result['coin'] !== $this->config->get('coin')) {
            return false;
        }

        return $result['data'];
    }

    /**
     * Output an API 'ok' response and exit.
     * @param mixed $data
     * @return void
     * @throws \Exception
     */
    public function echo($data): void
    {
        exit(json_encode(
            [
                'status' => 'ok',
                'data'   => $data,
                'coin'   => $this->config->get('coin'),
            ]
        ));
    }

    /**
     * Output an API error and exit.
     * @param mixed $data
     * @return void
     * @throws \Exception
     */
    public function error($data): void
    {
        exit(json_encode(
            [
                'status' => 'error',
                'data'   => $data,
                'coin'   => $this->config->get('coin'),
            ]
        ));
    }
}
