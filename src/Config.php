<?php

declare(strict_types=1);

namespace TusPhpS3;

use TusPhp\Config as TusConfig;

class Config extends TusConfig {
    private const DEFAULT_CONFIG_PATH = __DIR__ . '/Config/server.php';
}