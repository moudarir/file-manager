<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

final readonly class ImageConvertConfig
{

    private function __construct(
        public bool $removeAfterConvert,
        public array $thumbs = [],
        public ?string $resizePath = null,
        public ?string $dateFormat = null,
    )
    {
    }

    public static function create(array $config): self
    {
        return new self(
            $config['removeAfterConvert'],
            $config['thumbs'],
            $config['resizePath'],
            $config['dateFormat'],
        );
    }
}
