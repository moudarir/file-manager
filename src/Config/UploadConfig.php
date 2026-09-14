<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Config;

use DateTimeInterface;
use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class UploadConfig
{

    /**
     * @param list<MimeType> $allowedMimeTypes
     */
    private function __construct(
        public string             $field,
        public string             $uploadPath,
        public ?string            $dateFormat = null,
        public ?DateTimeInterface $customDate = null,
        public int                $maxFilesize = 0,
        public int                $maxImageWidth = 0,
        public int                $maxImageHeight = 0,
        public int                $minImageWidth = 0,
        public int                $minImageHeight = 0,
        public ?int               $maxUploadedFiles = null,
        public array              $allowedMimeTypes = [],
        public bool               $overwrite = false,
        public bool               $encryptName = true,
    ) {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        ValidateParam::notLessThan($config, [
            'maxFilesize',
            'maxImageWidth',
            'maxImageHeight',
            'minImageWidth',
            'minImageHeight',
        ]);

        ValidateParam::isBoolean($config, ['overwrite', 'encryptName']);

        if (ValidateParam::containsOnlyEnumCases(MimeType::class, $config['allowedMimeTypes']) === false) {
            throw FileManagerException::invalidParam('allowedMimeTypes');
        }

        if ($config['maxUploadedFiles'] !== null) {
            ValidateParam::notLessThan($config, 'maxUploadedFiles', 1);
        }

        ValidateParam::configDate($config);

        return new self(
            $config['field'],
            $config['uploadPath'],
            $config['dateFormat'],
            $config['customDate'],
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
