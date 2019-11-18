<?php
namespace TusPhpS3;

use TusPhp\Tus\Client AS TusClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;

class Client
extends TusClient
{
    protected $response;

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

    /**
     * Send PATCH request.
     *
     * @param int $bytes
     * @param int $offset
     *
     * @throws TusException
     * @throws FileException
     * @throws ConnectionException
     *
     * @return int
     */
    protected function sendPatchRequest(int $bytes, int $offset) : int
    {
        $data    = $this->getData($offset, $bytes);
        $headers = [
            'Content-Type' => self::HEADER_CONTENT_TYPE,
            'Content-Length' => \strlen($data),
            'Upload-Checksum' => $this->getUploadChecksumHeader(),
        ];
        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => self::UPLOAD_TYPE_PARTIAL];
        } else {
            $headers += ['Upload-Offset' => $offset];
        }
        try {
            $response = $this->getClient()->patch($this->getUrl(), [
                'body' => $data,
                'headers' => $headers,
            ]);

            $this->response = $response;

            return (int) current($this->response->getHeader('upload-offset'));
        } catch (ClientException $e) {
            $this->response = null;
            throw $this->handleClientException($e);
        } catch (ConnectException $e) {
            $this->response = null;
            throw new ConnectionException("Couldn't connect to server.");
        }
    }

    public function getLastResponse() : ?Response
    {
        return $this->response;
    }


}
