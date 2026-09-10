<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\CommandLineHelper;
use Moudarir\FileManager\Helpers\Common;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;

final readonly class ImageResizer
{

    private function __construct(
        private string $sourceFilepath,
        private string $destinationFilepath,
        private int    $width,
        private int    $height,
        private string $quality,
    ) {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(
        UploadedFileCollection $uploadedFileCollection,
        ImageResizeConfig $config,
    ): void
    {
        $thumbs = $config->thumbs;
        $resizePath = $config->resizePath;
        //$converterPath = Common::imageMagickExecutablePath();

        /**
         * @var UploadedFile $file
         */
        foreach ($uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            $sourceFilepath = $file->filepath();

            foreach ($thumbs as $thumb => $dimensions) {
                $directory = Common::makeDirectory(
                    $resizePath,
                    $file->createdAt(),
                    $config->dateFormat,
                    $thumb
                );
                $destinationFilepath = $directory . $file->basename();
                $width = (int)$dimensions['width'];
                $height = (int)$dimensions['height'];

                if ($width <= 0) {
                    throw FileManagerException::invalidPropertyValue('width');
                }

                if ($height <= 0) {
                    throw FileManagerException::invalidPropertyValue('height');
                }

                new self(
                    $sourceFilepath,
                    $destinationFilepath,
                    $width,
                    $height,
                    $config->quality,
                )->process();

                if ($thumb === 'large') {
                    $file
                        ->changeDirname($directory)
                        ->changeFilepath($destinationFilepath)
                        ->changeFilesize(@filesize($destinationFilepath) ?: 0);

                    if ($file->imageWidth() !== $width || $file->imageHeight() !== $height) {
                        if (($dimensions = @getimagesize($destinationFilepath)) === false) {
                            throw FileManagerException::invalidImage();
                        }

                        $file->changeImageDimensions([
                            'width' => $dimensions[0],
                            'height' => $dimensions[1],
                            'htmlAttributes' => $dimensions[3],
                        ]);
                    }
                }
            }

            if ($config->removeAfterResize === true && is_file($sourceFilepath) === true) {
                unlink($sourceFilepath);
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    private function process(): void
    {
        $command = CommandLineHelper::buildImageMagickCommand(
            $this->sourceFilepath,
            $this->destinationFilepath,
            [
                '-quality ' . escapeshellarg($this->quality),
                '-resize ' . $this->width.'x'.$this->height
            ]
        );

        if (CommandLineHelper::executeCommand($command) === false) {
            throw FileManagerException::imageResizeFailed();
        }

        @chmod($this->destinationFilepath, 0644);
    }
}
