<?php
namespace Twona\Core\Http\Upload\Storage;

use Twona\Core\Files\Uploaded,
    Twona\Core\Files\FileContent;

use Psr\Log\LoggerInterface;

use Predis\Client as Predis,
    League\Flysystem\AwsS3v3\AwsS3Adapter;

use TusPhp\File,
    TusPhp\Cache\Cacheable,
    TusPhp\Exception;

class S3File
extends File
{
    const PREFIX = "uploads";
    //const UPLOAD_EXPIRE = 86400; // 1 day
    const UPLOAD_MIN_SIZE = 5242880;
    const PARTS = "Parts";
    const UPLOAD_ID = "UploadId";
    const FINAL_FILE_KEY = "FinalFileKey";

    protected $s3Adapter;

    protected $uploadId;
    //protected $redis;
    //protected $logger;

    /**
     * File constructor.
     *
     * @param string|null    $name
     * @param Cacheable|null $cache
     */
    public function __construct(AwsS3Adapter $s3Adapter, string $name = null, Cacheable $cache = null)
    {
        $this->s3Adapter = $s3Adapter;
        $this->name  = $name;
        $this->cache = $cache;
    }

    // public function __construct(AwsS3Adapter $s3Adapter, Predis $redis, LoggerInterface $logger)
    // {
    //
    //     $this->redis = $redis;
    //     $this->logger = $logger;
    // }

    protected function getS3() : AwsS3Adapter
    {
        return $this->s3Adapter;
    }

    protected function getRedis() : Predis
    {
        return $this->redis;
    }

    public function upload(int $totalBytes) : int
    {
        //error_log("INIT offset->".$this->offset." totalBytes-->".$totalBytes);
        if ($this->offset === $totalBytes) {
            return $this->offset;
        }

        $key    = $this->getKey();
        $input  = $this->open($this->getInputStream(), self::READ_BINARY);
        //$output = $this->open($this->getFilePath(), self::APPEND_BINARY);
        try {
            // check size of upload and get output stream
            $output = tmpfile();
            $bytes = 0;
            while ( ! feof($input)) {
                $data  = $this->read($input, self::CHUNK_SIZE);
                $bytes += $this->write($output, $data, self::CHUNK_SIZE);
            }

            // check min multipart size
            if($bytes < $totalBytes && $bytes < self::UPLOAD_MIN_SIZE)
                throw new \Exception(sprintf("Your proposed upload is smaller than the minimum allowed size. (Proposal size: %s, MinSizeAllowed: %s).", $bytes, self::UPLOAD_MIN_SIZE));;

            // check what is next part
            $part = $this->getNextPart($key);

            // create s3 multipart
            if($this->offset == 0) {
                //error_log("Creating multipart upload.");
                $uploadId = $this->createUpload($key);
                //error_log("Upload ID--->".$uploadId);
                // $this->cache->set($key, ['UploadId' => $uploadId]);
            }



            //$filepath = stream_get_meta_data($input)['uri'];
            //$filesize = filesize($filepath);

            $etag = $this->setUpload($key, $part, $output, $bytes);

            $this->offset += $bytes;
            //$part = $this->offset/$filesize;
            $this->cache->set($key, ['offset' => $this->offset]);
            //$this->setLastPart($key, $part, $etag);

            if ($this->offset > $totalBytes) {
                throw new Exception\OutOfRangeException('The uploaded file is corrupt.');
            }

            // if ($this->offset === $totalBytes) {
            //     break;
            // }
            //$this->seek($output, $this->offset);
            // while ( ! feof($input)) {
            //     if (CONNECTION_NORMAL !== connection_status()) {
            //         throw new ConnectionException('Connection aborted by user.');
            //     }
            //     $data  = $this->read($input, self::CHUNK_SIZE);
            //     $bytes = $this->write($output, $data, self::CHUNK_SIZE);
            //     $this->offset += $bytes;
            //     $this->cache->set($key, ['offset' => $this->offset]);
            //     if ($this->offset > $totalBytes) {
            //         throw new OutOfRangeException('The uploaded file is corrupt.');
            //     }
            //     if ($this->offset === $totalBytes) {
            //         break;
            //     }
            // }
        } finally {
            $this->close($input);
            $this->close($output);
        }

        //error_log("FINAL offset->".$this->offset." totalBytes-->".$totalBytes);

        if ($this->offset === $totalBytes) {
            $this->completeUpload($key);
        }

        return $this->offset;
    }


    protected function createUpload(string $key)
    {
        if($this->getS3()->has($this->S3key($key)))
            $this->getS3()->delete($this->S3key($key));

        $result = $this->getS3()->getClient()->createMultipartUpload([
            'Bucket'       => $this->getS3()->getBucket(),
            'Key'          => $this->getS3()->applyPathPrefix($this->S3key($key)),
            'StorageClass' => 'REDUCED_REDUNDANCY',
            'Metadata'     => []
        ]);

        $this->cache->set($key, ["key" => $key]);
        $this->setUploadId($key, $result['UploadId']);

        return $result['UploadId'];
    }

    public function setUpload(string $key, int $part, $stream, $filesize)
    {
        //error_log("filesize---->". $filesize. " paaarrrttt --->".$part);
        $uploadId = $this->getUploadId($key);

        fseek($stream, 0);

        //$fstats = fstat($stream);
        //$filesize = $fstat['size'];

        //$stream = fopen($filename, 'r');
        $result = $this->getS3()->getClient()->uploadPart([
            'Bucket'       => $this->getS3()->getBucket(),
            'Key'          => $this->getS3()->applyPathPrefix($this->S3key($key)),
            'UploadId'     => $uploadId,
            'PartNumber'   => $part,
            'Body'         => fread($stream, $filesize),
            //'Body'         => stream_get_contents($stream)
        ]);

        //error_log(print_r($result, true));
        //fclose($stream);

        //$this->setData($key, null, $part, $result['ETag']);
        $this->setLastPart($key, $part, $result['ETag']);

        return $result['ETag'];
    }

    public function completeUpload($key)
    {
        $data = $this->cache->get($key);

        $uploadId = $data['UploadId'];
        $parts = $data['Parts'];
        //error_log("PARTSSSS-->". print_r($parts, true));

        $result = $this->getS3()->getClient()->completeMultipartUpload([
            'Bucket'        => $this->getS3()->getBucket(),
            'Key'           => $this->getS3()->applyPathPrefix($this->S3key($key)),
            'UploadId'      => $uploadId,
            'MultipartUpload'    => ['Parts' => $parts],
        ]);

        $this->cache->set($key, [self::FINAL_FILE_KEY => $this->S3key($key)]);

        // set expire like complete (not work)
        //$dt = new \DateTime();
        //$expire = $dt->format('D, d M Y H:i:s');
        //$this->cache->set($key, ['expires_at' => $expire]);
    }

    protected function setLastPart($key, $part, $etag)
    {
        $parts = $this->getParts($key);
        $parts[$part] = [
            'PartNumber' => $part,
            'ETag' => $etag,
        ];

        $this->cache->set($key, [self::PARTS => $parts]);
    }

    protected function getParts($key)
    {
        $data = $this->cache->get($key);

        return isset($data[self::PARTS])? $data[self::PARTS]  : [];
    }

    protected function getLastPart($key) : int
    {
        $parts = $this->getParts($key);

        return sizeof($parts);
    }

    protected function getNextPart($key)
    {
        return $this->getLastPart($key) + 1;
    }

    protected function setUploadId($key, string $uploadId)
    {
        $this->cache->set($key, [self::UPLOAD_ID => $uploadId]);
    }

    protected function getUploadId($key): string
    {
        $data = $this->cache->get($key);

        return isset($data[self::UPLOAD_ID])? $data[self::UPLOAD_ID] : "";
    }

    protected function S3key($key) : string
    {
        return sprintf("%s/%s.%s", self::PREFIX, $key, $this->getExtension());
    }

    protected function getExtension() : string
    {
        return pathinfo($this->getName(), PATHINFO_EXTENSION);
    }

    // protected function setData(string $key, string $uploadId = null, int $part = 0, string $ETag = null)
    // {
    //     $data = $this->getData($key);
    //
    //     if(isset($uploadId) && $part == 0) {
    //         $data = $this->_dataValue();
    //         $data['UploadId'] = $uploadId;
    //     }
    //
    //     if($part > 0)
    //     {
    //         $data['Parts'][$part] = [
    //             'PartNumber' => $part,
    //             'ETag' => $ETag,
    //         ];
    //     }
    //
    //     $this->getRedis()->setex($this->_dataKey($key), self::UPLOAD_EXPIRE, serialize($data));
    // }

    // protected function getData($key)
    // {
    //     $this->getRedis()->expire($this->_dataKey($key), self::UPLOAD_EXPIRE);
    //     $value = $this->getRedis()->get($this->_dataKey($key));
    //     return isset($value)? unserialize($value): $this->_dataValue();
    // }
    //
    // protected function _dataValue()
    // {
    //     return [
    //         'UploadId' => null,
    //         'Parts' => []
    //     ];
    // }
    //
    // protected function _dataKey($key)
    // {
    //     return sprintf("%s-data", $key);
    // }

}
