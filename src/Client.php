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
        $defaultHeaders = $this->client->getOptions('headers');
        $options = $this->getOptions();
        $options['headers'] = $defaultHeaders + ($options ?? []);

        $this->client = new GuzzleClient($options);

        return $this;
    }
}
