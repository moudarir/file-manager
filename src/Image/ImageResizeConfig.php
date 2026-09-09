<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

final readonly class ImageResizeConfig
{

    private function __construct(
        public string $resizePath,
        public array $thumbs,
        public string $quality,
        public bool $removeAfterResize,
        public ?string $dateFormat = null,
    )
    {
    }

    public static function create(array $config): self
    {
        return new self(
            $config['resizePath'],
            $config['thumbs'],
            $config['quality'],
            $config['removeAfterResize'],
            $config['dateFormat'],
        );
    }
}
