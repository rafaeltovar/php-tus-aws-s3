<?php
namespace TusPhpS3\Http;

use TusPhp\Response as TusResponse;

class Response
extends TusResponse
{
    public function __construct()
    {
        $this->createOnly(true);
    }
}
