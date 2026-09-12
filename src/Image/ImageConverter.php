<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\CommandLineHelper;
use Moudarir\FileManager\Helpers\Common;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;

final readonly class ImageConverter
{

    /**
     * @throws FileManagerException
     */
    public static function create(
        UploadedFileCollection $uploadedFileCollection,
        ImageConvertConfig $config,
    ): void
    {
        $thumbs = $config->thumbs;

        /**
         * @var UploadedFile $file
         */
        foreach ($uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            if ($file->mimeType() === MimeType::WEBP) {
                $file->markAsConverted();
                continue;
            }

            $filesToConvert = [];

            if ($thumbs !== []) {
                foreach ($thumbs as $thumb) {
                    $directory = Common::buildDirectoryPath(
                        $config->resizePath,
                        $config->customDate !== null ? $config->customDate : $file->createdAt(),
                        $config->dateFormat,
                        $thumb
                    );

                    $filesToConvert[$thumb] = [
                        'source' => $directory . $file->basename(),
                        'destination' => $directory . $file->filename() . '.webp',
                    ];
                }
            } else {
                $filesToConvert['large'] = [
                    'source' => $file->filepath(),
                    'destination' => $file->dirname() . DIRECTORY_SEPARATOR . $file->filename() . '.webp',
                ];
            }

            if (self::process($filesToConvert) === true) {
                $newFilepath = $filesToConvert['large']['destination'];

                if (($filesize = filesize($newFilepath)) === false) {
                    foreach ($filesToConvert as $original) {
                        if (is_file($original['destination']) === true) {
                            unlink($original['destination']);
                        }
                    }

                    throw FileManagerException::imageConvertFailed();
                }

                $file
                    ->markAsConverted()
                    ->changeFilepath($newFilepath)
                    ->changeBasename($file->filename().'.webp')
                    ->changeExtension('webp')
                    ->changeMimeType(MimeType::WEBP)
                    ->changeFilesize($filesize);

                if ($config->removeAfterConvert === true) {
                    foreach ($filesToConvert as $original) {
                        if (is_file($original['source']) === true) {
                            unlink($original['source']);
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    private static function process(array $filesToConvert): bool
    {
        if ($filesToConvert === []) {
            return false;
        }

        $convertedFiles = [];

        foreach ($filesToConvert as $fileToConvert) {
            $sourceFilepath = $fileToConvert['source'];
            $destinationFilepath = $fileToConvert['destination'];

            $command = CommandLineHelper::buildImageMagickCommand(
                $sourceFilepath,
                'webp:'.$destinationFilepath,
                [
                    "-quality '75'",
                    '-strip',
                    '-define webp:alpha-quality=90',
                    '-define webp:method=5',
                ]
            );

            if (
                CommandLineHelper::executeCommand($command) === false ||
                is_file($destinationFilepath) === false
            ) {
                if ($convertedFiles !== []) {
                    foreach ($convertedFiles as $filepath) {
                        if (is_file($filepath) === true) {
                            unlink($filepath);
                        }
                    }
                }

                throw FileManagerException::imageConvertFailed();
            }

            $convertedFiles[] = $destinationFilepath;
            @chmod($destinationFilepath, 0644);
        }

        return true;
    }
}
