<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

final readonly class ImageWatermarkConfig
{

    private function __construct(
        public string $resizePath,
        public array $thumbs,
        public array $watermarks,
        public ?string $dateFormat = null,
    )
    {
    }

    public static function create(array $config): self
    {
        return new self(
            $config['resizePath'],
            $config['thumbs'],
            $config['watermarks'],
            $config['dateFormat'],
        );
    }
}
