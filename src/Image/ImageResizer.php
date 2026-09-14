<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\File\Exceptions\FileResourceException;
use Moudarir\File\Exceptions\MimeDetectionException;
use Moudarir\File\File;
use Moudarir\FileManager\Collections\ThumbCollection;
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
        private int    $quality,
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

        /**
         * @var UploadedFile $file
         */
        foreach ($uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            $sourceFilepath = $file->filepath();
            $date = $config->customDate !== null ? $config->customDate : $file->createdAt();
            $thumbCollection = [];

            foreach ($thumbs as $thumb => $dimensions) {
                $directory = Common::makeDirectory(
                    $resizePath,
                    $date,
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
                    $config->resizeQuality,
                )->process();

                try {
                    $destinationFile = File::create($destinationFilepath, $file->mimeType());
                } catch (FileResourceException|MimeDetectionException $exception) {
                    throw FileManagerException::generic($exception->getMessage(), $exception);
                }

                if (($dimensions = @getimagesize($destinationFilepath)) === false) {
                    throw FileManagerException::invalidImage();
                }

                $thumbCollection[$thumb] = UploadedFile::create(
                    $destinationFile->resource(),
                    $file->mimeType(),
                    $file->originalName(),
                    $date,
                    [
                        'width' => $dimensions[0],
                        'height' => $dimensions[1],
                        'htmlAttributes' => $dimensions[3],
                    ],
                );
            }

            if ($thumbCollection !== []) {
                $file->setThumbCollection(new ThumbCollection($thumbCollection));
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
                '-quality ' . $this->quality,
                '-resize ' . $this->width.'x'.$this->height
            ]
        );

        if (CommandLineHelper::executeCommand($command) === false) {
            throw FileManagerException::imageResizeFailed();
        }

        @chmod($this->destinationFilepath, 0644);
    }
}
