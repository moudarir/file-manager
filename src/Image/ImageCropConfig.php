<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

final readonly class ImageCropConfig
{

    private function __construct(public int $cropRatioWidth, public int $cropRatioHeight)
    {
    }

    public static function create(array $config): self
    {
        return new self($config['cropRatioWidth'], $config['cropRatioHeight']);
    }
}
