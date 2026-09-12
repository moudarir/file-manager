<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use DateTimeInterface;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageWatermarkConfig
{

    private function __construct(
        public string             $resizePath,
        public array              $thumbs,
        public array              $watermarks,
        public ?string            $dateFormat = null,
        public ?DateTimeInterface $customDate = null,
    )
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        foreach ($config['thumbs'] as $dimensions) {
            if (isset($dimensions['width'], $dimensions['height']) === false) {
                throw FileManagerException::invalidParam('thumbs');
            }
        }

        ValidateParam::configDate($config);

        return new self(
            $config['resizePath'],
            $config['thumbs'],
            $config['watermarks'],
            $config['dateFormat'],
            $config['customDate'],
        );
    }
}
