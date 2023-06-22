<?php
namespace TusPhpS3\Cache;

use TusPhpS3\Config;

use Aws\S3\S3ClientInterface;

use TusPhp\Cache\AbstractCache;

class AwsS3Cache
extends AbstractCache
{
    const BUCKET = 'AWS_S3_BUCKET';
    const PREFIX = 'AWS_S3_CACHE_PREFIX';
    const PUT_OPTIONS = 'AWS_S3_CACHE_PUT_REQUEST_OPTIONS';

    private $prefix;

    /**
     * AwsS3Cache constructor.
     *
     * @param array $options
     */
    public function __construct(private S3ClientInterface $client)
    {
        $prefix = str_replace("//", "/", sprintf('%s%s', Config::GET(self::PREFIX), 'parts'));
        $this->setPrefix(Config::GET(self::PREFIX));
    }

    private function serialize($value) : string
    {
        if(\is_array($value))
        {
            $value = json_encode($value);
        }

        return $value;
    }

    private function deserialize(string $serialized)
    {
        $value = json_decode($serialized, true);

        if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
            $value = $serialized;
        }

        return $value;
    }

    private function key(string $key) : string
    {
        return sprintf("%s%s", $this->getPrefix(), $key);
    }

    /**
     * Get data associated with the key.
     *
     * @param string $key
     * @param bool   $withExpired
     *
     * @return mixed
     */
    public function get(string $key, bool $withExpired = false)
    {
        $request = [
            'Key' => $this->key($key),
            'Bucket' => Config::get(self::BUCKET)
        ];

        $result = $this->client->getObject(
            $request
        );

        return $this->deserialize($result['Body']);
    }

    /**
     * Set data to the given key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function set(string $key, $value)
    {
        $content = $this->serialize($value);

        $request = Config::get(self::PUT_OPTIONS);

        $request = [...$request, ...[
            'Key' => $this->key($key),
            'Bucket' => Config::get(self::BUCKET),
            'Body' => $content

        ]];

        $this->client->putObject(
            $request
        );
    }

    /**
     * Delete data associated with the key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->deleteAll([$key]);
    }

    /**
     * Delete all data associated with the keys.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function deleteAll(array $keys): bool
    {
        $request = [
            'Bucket' => Config::get(self::BUCKET),
            'Objects' => array_map(fn($k) => ['Key' => $this->key($k)], $keys)
        ];

        $this->client->deleteObjects(
            $request
        );

        return true;
    }

    /**
     * Get cache keys.
     *
     * @return array
     */
    public function keys(): array
    {
        $request = [
            'Bucket' => Config::get(self::BUCKET),
            'Prefix' => $this->getPrefix()

        ];

        $this->client->listObjects(
            $request
        );

        return array_map(fn($r) => $r['Key'], $result['Contents']);
    }

    /**
     * Set cache prefix.
     *
     * @param string $prefix
     *
     * @return self
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
    }

    /**
     * Get cache prefix.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

}
