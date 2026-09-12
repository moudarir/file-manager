<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use DateTimeInterface;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageConvertConfig
{

    private function __construct(
        public bool               $removeAfterConvert,
        public array              $thumbs = [],
        public ?string            $resizePath = null,
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
        ValidateParam::isBoolean($config, 'removeAfterConvert');

        ValidateParam::configDate($config);

        if (isset($config['thumbs']['large']) === true) {
            $config['thumbs'] = array_keys($config['thumbs']);
        }

        return new self(
            $config['removeAfterConvert'],
            $config['thumbs'],
            $config['resizePath'],
            $config['dateFormat'],
            $config['customDate'],
        );
    }
}
