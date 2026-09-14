<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Config;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageConvertConfig
{

    private function __construct(public bool $removeAfterConvert)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        ValidateParam::isBoolean($config, 'removeAfterConvert');

        return new self($config['removeAfterConvert']);
    }
}
