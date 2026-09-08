<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

final readonly class ImageConvertConfig
{
    private function __construct() {
    }

    public static function create(array $config): self
    {
        throw new \LogicException('Not implemented.');
    }
}
