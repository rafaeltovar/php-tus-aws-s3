<?php
namespace TusPhpS3;

use TusPhp\Tus\Server as TusServer,
    TusPhp\Cache\AbstractCache,
    TusPhp\Middleware\Middleware;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

class Server
extends TusServer
{
    protected $storage;
    protected $forceLocationSSL;

    public function __construct(
        AbstractCache $cache,
        AwsS3Adapter $storage,
        Http\Request $request,
        $excludeAttrApiPath = [],
        $forceLocationSSL = true)
    {
        $this->request = $request;
        $this->response   = new Http\Response;
        $this->middleware = new Middleware;

        //$s3->registerStreamWrapper();
        //$cache = $this->cache;

        $this->setCache($cache);
        $this->setStorage($storage);
        //$this->setUploadDir($uploadDir);

        // force ssl on location
        $this->forceLocationSSL = $forceLocationSSL;

        // set api path
        $apiPath = $request->getRequest()->getRequestUri();
        foreach($excludeAttrApiPath as $exclude)
        {
            $apiPath = $request->getRequest()->attributes->has($exclude) ?
                       str_replace(sprintf("/%s", $request->getRequest()->attributes->get($exclude)), "", $request->getRequest()->getRequestUri()) :
                       $apiPath;
        }

        $this->setApiPath($apiPath);

    }

    public function setStorage(AwsS3Adapter $storage)
    {
        $this->storage = $storage;
    }

    public function getStorage() : AwsS3Adapter
    {
        return $this->storage;
    }

    /**
     * Handle all HTTP request.
     *
     * @return HttpResponse|BinaryFileResponse
     */
    public function serve()
    {
        $response = parent::serve();

        if($this->forceLocationSSL && $response->headers->has('location'))
            $response->headers->set('location', str_replace("http://", "https://", $response->headers->get('location')));

        return $response;
    }

    /**
     * Build file object.
     *
     * @param array $meta
     *
     * @return File
     */
    protected function buildFile(array $meta) : \TusPhp\File
    {
        $file = new Storage\S3File($this->getStorage(), $meta['name'], $this->cache);

        if (array_key_exists('offset', $meta)) {
            $file->setMeta($meta['offset'], $meta['size'], $meta['file_path'], $meta['location']);
        }

        return $file;
    }

    /**
     * Verify checksum if available.
     *
     * @param string $checksum
     * @param string $filePath
     *
     * @return bool
     */
    protected function verifyChecksum(string $checksum, string $filePath) : bool
    {
        // TODO fix
        return true;
    }
}
