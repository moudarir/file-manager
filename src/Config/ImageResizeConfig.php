<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Config;

use DateTimeInterface;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageResizeConfig
{

    private function __construct(
        public string             $resizePath,
        public array              $thumbs,
        public int                $resizeQuality,
        public bool               $removeAfterResize,
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

        ValidateParam::isBetween($config, 'resizeQuality');

        ValidateParam::isBoolean($config, 'removeAfterResize');

        ValidateParam::configDate($config);

        return new self(
            $config['resizePath'],
            $config['thumbs'],
            $config['resizeQuality'],
            $config['removeAfterResize'],
            $config['dateFormat'],
            $config['customDate'],
        );
    }
}
