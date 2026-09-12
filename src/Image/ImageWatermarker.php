<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use GdImage;
use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\Common;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;

final readonly class ImageWatermarker
{

    private function __construct(
        private string             $sourceFilepath,
        private MimeType           $sourceMimeType,
        private string             $overlayFilepath,
        private WatermarkAlignment $horizontalAlignment,
        private WatermarkAlignment $verticalAlignment,
        private int                $opacity,
        private int                $xTransparency,
        private int                $yTransparency,
    ) {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(
        UploadedFileCollection $uploadedFileCollection,
        ImageWatermarkConfig $config,
    ): void
    {
        $watermarks = array_intersect_key($config->watermarks, $config->thumbs);

        if ($watermarks === []) {
            return;
        }

        ini_set('gd.jpeg_ignore_warning', 1);

        /**
         * @var UploadedFile $file
         */
        foreach ($uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            foreach ($watermarks as $thumb => $watermark) {
                $directory = Common::buildDirectoryPath(
                    $config->resizePath,
                    $config->customDate !== null ? $config->customDate : $file->createdAt(),
                    $config->dateFormat,
                    $thumb
                );
                $sourceFilepath = $directory . $file->basename();

                if (is_file($sourceFilepath) === false) {
                    throw FileManagerException::fileNotExists($sourceFilepath);
                }

                // The server path to the image you wish to use as your watermark.
                $overlayFilepath = $watermark['overlayFilepath'];

                if (is_file($overlayFilepath) === false) {
                    continue;
                }

                // Sets the horizontal alignment for the watermark image.
                $horizontalAlignment = $watermark['horizontalAlignment'];

                if (
                    $horizontalAlignment instanceof WatermarkAlignment === false ||
                    $horizontalAlignment->isHorizontal() === false
                ) {
                    $horizontalAlignment = WatermarkAlignment::H_CENTER;
                    // Or throw an exception
                    // throw FileManagerException::invalidPropertyValue('horizontalAlignment');
                }

                // Sets the vertical alignment for the watermark image.
                $verticalAlignment = $watermark['verticalAlignment'];
                if (
                    $verticalAlignment instanceof WatermarkAlignment === false ||
                    $verticalAlignment->isVertical() === false
                ) {
                    $verticalAlignment = WatermarkAlignment::V_MIDDLE;
                    // Or throw an exception
                    // throw FileManagerException::invalidPropertyValue('verticalAlignment');
                }

                // Image opacity.
                $opacity = (int)($watermark['opacity'] ?? 19);

                if ($opacity < 1 || $opacity > 100) {
                    $opacity = 19;
                }

                // If your watermark image is a PNG or GIF image, you may specify a color on
                // the image to be “transparent”.
                // This settings will allow you to specify that color. This works by specifying
                // the “X” and “Y” coordinate pixel (measured from the upper left) within the
                // image that corresponds to a pixel representative of the color you want to be transparent.
                $xTransparency = (int)($watermark['xTransparency'] ?? 4);
                $yTransparency = (int)($watermark['yTransparency'] ?? 4);

                if ($xTransparency < 0 || $xTransparency > 127) {
                    $xTransparency = 4;
                }

                if ($yTransparency < 0 || $yTransparency > 127) {
                    $yTransparency = 4;
                }

                new self(
                    $sourceFilepath,
                    $file->mimeType(),
                    $overlayFilepath,
                    $horizontalAlignment,
                    $verticalAlignment,
                    $opacity,
                    $xTransparency,
                    $yTransparency,
                )->process();
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    private function process(): void
    {
        $sourceImageInfo = self::getImageInfo($this->sourceFilepath);
        $watermarkImageInfo = self::getImageInfo($this->overlayFilepath);

        if (($watermarkMimeType = MimeType::tryFrom($watermarkImageInfo['mime'])) === null) {
            throw FileManagerException::unsupportedImageType($watermarkImageInfo['mime']);
        }

        // Create two image resources
        $sourceResource = self::createImageResource($this->sourceFilepath, $this->sourceMimeType);
        $watermarkResource = self::createImageResource($this->overlayFilepath, $watermarkMimeType);

        try {
            $sourceWidth = $sourceImageInfo['width'];
            $sourceHeight = $sourceImageInfo['height'];
            $watermarkWidth = $watermarkImageInfo['width'];
            $watermarkHeight = $watermarkImageInfo['height'];

            // Allow to set those settings in next release
            $horizontalOffset = 0;
            $verticalOffset = 0;
            $padding = 0;

            if ($this->verticalAlignment === WatermarkAlignment::V_BOTTOM) {
                $verticalOffset *= -1;
            }

            if ($this->horizontalAlignment === WatermarkAlignment::H_RIGHT) {
                $horizontalOffset *= -1;
            }

            // Set the base x-axis and y-axis values
            $xAxis = $horizontalOffset + $padding;
            $yAxis = $verticalOffset + $padding;

            // Set the vertical position
            if ($this->verticalAlignment === WatermarkAlignment::V_MIDDLE) {
                $yAxis += floor($sourceHeight / 2) - floor($watermarkHeight / 2);
            } elseif ($this->verticalAlignment === WatermarkAlignment::V_BOTTOM) {
                $yAxis += $sourceHeight - $watermarkHeight;
            }

            // Set the horizontal position
            if ($this->horizontalAlignment === WatermarkAlignment::H_CENTER) {
                $xAxis += floor($sourceWidth / 2) - floor($watermarkWidth / 2);
            } elseif ($this->horizontalAlignment === WatermarkAlignment::H_RIGHT) {
                $xAxis += $sourceWidth - $watermarkWidth;
            }

            $yAxis = (int)round($yAxis);
            $xAxis = (int)round($xAxis);

            // Build the finalized image
            if (
                in_array($watermarkMimeType, [MimeType::PNG, MimeType::WEBP], true) &&
                function_exists('imagealphablending')
            ) {
                @imagealphablending($sourceResource, true);
            }

            // Set RGB values for text and shadow
            $rgba = imagecolorat($watermarkResource, $this->xTransparency, $this->yTransparency);
            $alpha = ($rgba & 0x7F000000) >> 24;

            // Make the best guess whether we're dealing with an image with alpha transparency or no/binary transparency
            if ($alpha > 0) {
                // copy the image directly, the image's alpha transparency being the sole determinant of blending
                imagecopy(
                    $sourceResource,
                    $watermarkResource,
                    $xAxis,
                    $yAxis,
                    0,
                    0,
                    $watermarkWidth,
                    $watermarkHeight
                );
            } else {
                // set our RGB value from above to be transparent and merge the images with the specified opacity
                imagecolortransparent(
                    $watermarkResource,
                    imagecolorat($watermarkResource, $this->xTransparency, $this->yTransparency)
                );
                imagecopymerge(
                    $sourceResource,
                    $watermarkResource,
                    $xAxis,
                    $yAxis,
                    0,
                    0,
                    $watermarkWidth,
                    $watermarkHeight,
                    $this->opacity
                );
            }

            // We can preserve transparency for PNG or WEBP images
            if (in_array($this->sourceMimeType, [MimeType::PNG, MimeType::WEBP], true)) {
                imagealphablending($sourceResource, false);
                imagesavealpha($sourceResource, true);
            }

            $this->saveImage($sourceResource);
        } finally {
            imagedestroy($sourceResource);
            imagedestroy($watermarkResource);
        }
    }

    /**
     * @throws FileManagerException
     */
    private function saveImage(GdImage $resource): void
    {
        switch ($this->sourceMimeType) {
            case MimeType::GIF:
                if (function_exists('imagegif') === false) {
                    throw FileManagerException::unsupportedImageCreate('gif');
                }

                if (@imagegif($resource, $this->sourceFilepath) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::JPEG:
                if (function_exists('imagejpeg') === false) {
                    throw FileManagerException::unsupportedImageCreate('jpg');
                }

                if (@imagejpeg($resource, $this->sourceFilepath, 100) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::PNG:
                if (function_exists('imagepng') === false) {
                    throw FileManagerException::unsupportedImageCreate('png');
                }

                if (@imagepng($resource, $this->sourceFilepath) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::WEBP:
                if (function_exists('imagewebp') === false) {
                    throw FileManagerException::unsupportedImageCreate('webp');
                }

                if (@imagewebp($resource, $this->sourceFilepath, 100) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            default:
                throw FileManagerException::unsupportedImageCreate();
        }
    }

    /**
     * @throws FileManagerException
     */
    private static function createImageResource(string $path, MimeType $mimeType): GdImage|bool
    {
        switch ($mimeType) {
            case MimeType::GIF:
                if (function_exists('imagecreatefromgif') === false) {
                    throw FileManagerException::unsupportedImageCreate('gif');
                }

                return imagecreatefromgif($path);
            case MimeType::JPEG:
                if (function_exists('imagecreatefromjpeg') === false) {
                    throw FileManagerException::unsupportedImageCreate('jpg');
                }

                return imagecreatefromjpeg($path);
            case MimeType::PNG:
                if (function_exists('imagecreatefrompng') === false) {
                    throw FileManagerException::unsupportedImageCreate('png');
                }

                return imagecreatefrompng($path);
            case MimeType::WEBP:
                if (function_exists('imagecreatefromwebp') === false) {
                    throw FileManagerException::unsupportedImageCreate('webp');
                }

                return imagecreatefromwebp($path);
            default:
                throw FileManagerException::unsupportedImageCreate();
        }
    }

    /**
     * @throws FileManagerException
     */
    private static function getImageInfo(string $filepath): array
    {
        if (($imageInfo = @getimagesize($filepath)) === false) {
            throw FileManagerException::invalidImage();
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime' => $imageInfo['mime'],
        ];
    }
}
