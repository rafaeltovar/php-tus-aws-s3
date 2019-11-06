<?php
namespace TusPhpS3\Http;

use TusPhp\Response;

class UploadHttpResponse
extends Response
{
    public function __construct()
    {
        parent::__construct();
        $this->createOnly(true);
    }
}
