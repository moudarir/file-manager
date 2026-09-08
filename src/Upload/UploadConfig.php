<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Upload;

use Moudarir\File\Enum\MimeType;

final readonly class UploadConfig
{

    /**
     * @param list<MimeType> $allowedMimeTypes
     */
    private function __construct(
        public string  $field,
        public string  $uploadPath,
        public ?string $dateFormat = null,
        public int     $maxFilesize = 0,
        public int     $maxImageWidth = 0,
        public int     $maxImageHeight = 0,
        public int     $minImageWidth = 0,
        public int     $minImageHeight = 0,
        public ?int    $maxUploadedFiles = null,
        public array   $allowedMimeTypes = [],
        public bool    $overwrite = false,
        public bool    $encryptName = true,
    ) {
    }

    public static function create(array $config): self
    {
        return new self(
            $config['field'],
            $config['uploadPath'],
            $config['dateFormat'],
            $config['maxFilesize'],
            $config['maxImageWidth'],
            $config['maxImageHeight'],
            $config['minImageWidth'],
            $config['minImageHeight'],
            $config['maxUploadedFiles'],
            $config['allowedMimeTypes'],
            $config['overwrite'],
            $config['encryptName'],
        );
    }
}
