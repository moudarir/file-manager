<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Collections;

use DateTimeImmutable;
use DateTimeInterface;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\Exceptions\FileResourceException;
use Moudarir\File\Exceptions\MimeDetectionException;
use Moudarir\File\File;
use Moudarir\FileManager\Config\CollectionBuilderConfig;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\UploadedFile;

final readonly class CollectionBuilder
{

    private function __construct(
        private string            $filepath,
        private DateTimeInterface $createdAt,
        private ?string           $mimeType = null,
        private ?array            $dimensions = null,
    )
    {
    }

    /**
     * @param list<array{
     *     filepath: string,
     *     mimeType?: string|null,
     *     createdAt?: DateTimeInterface|null,
     *     dimensions?: array{width: int, height: int}
     *  }> $files
     * @throws FileManagerException
     */
    public static function create(
        array $files,
        CollectionBuilderConfig $config,
        bool $ignoreInvalidFiles = false
    ): UploadedFileCollection
    {
        $customDate = $config->customDate !== null ? $config->customDate : new DateTimeImmutable();
        $uploadedFiles = [];

        foreach ($files as $file) {
            if (
                array_key_exists('filepath', $file) === false ||
                empty($file['filepath']) === true
            ) {
                if ($ignoreInvalidFiles === true) {
                    continue;
                }

                throw FileManagerException::invalidKeyValue('filepath');
            }

            if (
                array_key_exists('createdAt', $file) === true &&
                $file['createdAt'] instanceof DateTimeInterface
            ) {
                $createdAt = $file['createdAt'];
            } else {
                $createdAt = $customDate;
            }

            $uploadedFiles[] = new self(
                $file['filepath'],
                $createdAt,
                $file['mimeType'] ?? null,
                $file['dimensions'] ?? null,
            )->build($ignoreInvalidFiles);
        }

        $uploadedFiles = array_filter($uploadedFiles, static fn ($item): bool => $item !== null);

        return new UploadedFileCollection($config->field, $uploadedFiles);
    }

    /**
     * @throws FileManagerException
     */
    private function build(bool $ignoreInvalidFiles = false): ?UploadedFile
    {
        try {
            $providedMimeType = empty($this->mimeType) === false
                ? MimeType::tryFrom($this->mimeType)
                : null;
            $file = File::create($this->filepath, $providedMimeType);

            $fileResource = $file->resource();
            $mimeType = $file->detection()->mimeType();
            $dimensions = null;

            if ($mimeType->isImage()) {
                if (
                    is_array($this->dimensions) === true &&
                    isset($this->dimensions['width'], $this->dimensions['height']) === true
                ) {
                    $width = (int)$this->dimensions['width'];
                    $height = (int)$this->dimensions['height'];
                    $dimensions = [
                        'width' => $width,
                        'height' => $height,
                        'htmlAttributes' => 'width="'.$width.'" height="'.$height.'"',
                    ];
                } else {
                    if (($info = getimagesize($this->filepath)) === false) {
                        if ($ignoreInvalidFiles === true) {
                            return null;
                        }

                        throw FileManagerException::invalidDimensions();
                    }

                    $dimensions = [
                        'width' => $info[0],
                        'height' => $info[1],
                        'htmlAttributes' => $info[3],
                    ];
                }
            }

            return UploadedFile::create(
                $fileResource,
                $mimeType,
                $fileResource->basename(),
                $this->createdAt,
                $dimensions,
            );
        } catch (FileResourceException|MimeDetectionException $exception) {
            if ($ignoreInvalidFiles === true) {
                return null;
            }

            throw FileManagerException::generic($exception->getMessage(), $exception);
        }
    }
}
