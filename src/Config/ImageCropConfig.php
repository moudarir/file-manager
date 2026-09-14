<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Config;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageCropConfig
{

    private function __construct(public int $cropRatioWidth, public int $cropRatioHeight)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        ValidateParam::notLessThan($config, ['cropRatioWidth', 'cropRatioHeight'], 1);

        return new self($config['cropRatioWidth'], $config['cropRatioHeight']);
    }
}
