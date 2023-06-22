<?php
namespace TusPhpS3\Http;

use TusPhp\Request as TusRequest;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class Request
extends TusRequest
{

    public function setRequest(HttpRequest $request)
    {
        $this->request = $request;
    }
    /**
     * Validate file name.
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function isValidFilename(string $filename): bool
    {
        $forbidden = ['../', '"', "'", '/', '\\', ':'];

        foreach ($forbidden as $char) {
            if (false !== strpos($filename, $char)) {
                return false;
            }
        }

        return true;
    }
}
