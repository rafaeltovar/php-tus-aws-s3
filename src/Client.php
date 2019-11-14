<?php
namespace TusPhpS3;

use TusPhp\Tus\Client AS TusClient;
use GuzzleHttp\Client as GuzzleClient;

class Client
extends TusClient
{
    public function __construct(GuzzleClient $client)
    {
        parent::__construct("", []);

        $this->setHttpClient($client);
    }

    public function setHttpClient(GuzzleClient $client) : self
    {
        $defaultHeaders = $this->client->getConfig('headers');
        $options = $client->getConfig();
        $options['headers'] = $defaultHeaders + ($options['headers'] ?? []);

        $this->client = new GuzzleClient($options);

        return $this;
    }
}
