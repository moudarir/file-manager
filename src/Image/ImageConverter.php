<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Collections\CollectionInterface;
use Moudarir\FileManager\Config\ImageConvertConfig;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\CommandLineHelper;

final readonly class ImageConverter
{

    private const string WEBP_EXTENSION = 'webp';

    /**
     * @throws FileManagerException
     */
    public static function create(CollectionInterface $collection, ImageConvertConfig $config): void
    {
        foreach ($collection->all() as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            if ($file->mimeType() === MimeType::WEBP) {
                $file->markAsConverted();
                continue;
            }

            $thumbCollection = $file->thumbCollection();
            $files = $thumbCollection !== null && $thumbCollection->isEmpty() === false
                ? $thumbCollection->all()
                : [$file];

            $sourcesToDelete = [];
            $convertedFiles = [];
            $convertedUploadedFiles = [];

            foreach ($files as $fileToConvert) {
                $source = $fileToConvert->filepath();
                $basename = $fileToConvert->filename() . '.' . self::WEBP_EXTENSION;
                $destination = $fileToConvert->dirname() . DIRECTORY_SEPARATOR . $basename;

                self::process($source, $destination, $convertedFiles);

                if (($filesize = filesize($destination)) === false) {
                    self::purgeOnError([...$convertedFiles, $destination]);

                    throw FileManagerException::imageConvertFailed();
                }

                $convertedFiles[] = $destination;
                $sourcesToDelete[] = $source;
                $convertedUploadedFiles[] = [
                    'convertedFile' => $fileToConvert,
                    'destination' => $destination,
                    'basename' => $basename,
                    'filesize' => $filesize,
                ];
            }

            if ($convertedUploadedFiles !== []) {
                foreach ($convertedUploadedFiles as $converted) {
                    $convertedFile = $converted['convertedFile'];
                    $convertedFile
                        ->markAsConverted()
                        ->changeFilepath($converted['destination'])
                        ->changeBasename($converted['basename'])
                        ->changeExtension(self::WEBP_EXTENSION)
                        ->changeMimeType(MimeType::WEBP)
                        ->changeFilesize($converted['filesize']);
                }
            }

            if ($config->removeAfterConvert === true) {
                self::removeSources($sourcesToDelete);
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    private static function process(string $source, string $destination, array $convertedFiles = []): void
    {
        $command = CommandLineHelper::buildImageMagickCommand(
            $source,
            'webp:'.$destination,
            [
                "-quality '75'",
                '-strip',
                '-define webp:alpha-quality=90',
                '-define webp:method=5',
            ]
        );

        if (
            CommandLineHelper::executeCommand($command) === false ||
            is_file($destination) === false
        ) {
            self::purgeOnError($convertedFiles);
            throw FileManagerException::imageConvertFailed();
        }
    }

    /**
     * @param string[] $sources
     */
    private static function removeSources(array $sources): void
    {
        foreach ($sources as $source) {
            if (is_file($source) === true) {
                unlink($source);
            }
        }
    }

    /**
     * @param string[] $convertedFiles
     */
    private static function purgeOnError(array $convertedFiles): void
    {
        foreach ($convertedFiles as $filepath) {
            if (is_file($filepath) === true) {
                unlink($filepath);
            }
        }
    }
}
