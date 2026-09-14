<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use GdImage;
use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Collections\CollectionInterface;
use Moudarir\FileManager\Config\ImageWatermarkConfig;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\UploadedFile;

final readonly class ImageWatermarker
{

    /**
     * @param UploadedFile $source
     * @param array{
     *     overlayFilepath: string,
     *     horizontalAlignment: WatermarkAlignment,
     *     verticalAlignment: WatermarkAlignment,
     *     opacity: int,
     *     xTransparency: int,
     *     yTransparency: int
     * } $watermark
     */
    private function __construct(private UploadedFile $source, private array $watermark)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(CollectionInterface $collection, ImageWatermarkConfig $config): void
    {
        ini_set('gd.jpeg_ignore_warning', 1);

        /**
         * @var UploadedFile $file
         */
        foreach ($collection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            $imagesToProcess = [];
            $originalWatermark = $config->watermarks['original'] ?? null;

            if ($originalWatermark !== null) {
                $imagesToProcess[] = [$file, $originalWatermark];
            }

            $thumbCollection = $file->thumbCollection();

            if ($thumbCollection !== null && $thumbCollection->isEmpty() === false) {
                foreach ($config->watermarks as $name => $watermark) {
                    if ($name === 'original') {
                        continue;
                    }

                    if ($thumbCollection->has($name) === false) {
                        continue;
                    }

                    $imagesToProcess[] = [$thumbCollection->get($name), $watermark];
                }
            }

            if ($imagesToProcess === []) {
                continue;
            }

            /**
             * @var UploadedFile $source
             */
            foreach ($imagesToProcess as [$source, $watermark]) {
                $sourceFilepath = $source->filepath();

                is_file($sourceFilepath) ||
                    throw FileManagerException::fileNotExists($sourceFilepath);

                new self($source, $watermark)->process();
            }
        }
    }

    /**
     * @throws FileManagerException
     */
    private function process(): void
    {
        $overlayFilepath = $this->watermark['overlayFilepath'];

        if (($imageInfo = @getimagesize($overlayFilepath)) === false) {
            throw FileManagerException::invalidImage();
        }

        if (($watermarkMimeType = MimeType::tryFrom($imageInfo['mime'])) === null) {
            throw FileManagerException::unsupportedImageType($imageInfo['mime']);
        }

        // Create two image resources
        $sourceFilepath = $this->source->filepath();
        $sourceMimeType = $this->source->mimeType();
        $sourceResource = self::createImageResource($sourceFilepath, $sourceMimeType);
        $watermarkResource = self::createImageResource($overlayFilepath, $watermarkMimeType);

        try {
            $sourceWidth = $this->source->imageWidth();
            $sourceHeight = $this->source->imageHeight();
            $watermarkWidth = $imageInfo[0];
            $watermarkHeight = $imageInfo[1];
            $verticalAlignment = $this->watermark['verticalAlignment'];
            $horizontalAlignment = $this->watermark['horizontalAlignment'];

            // Allow to set those settings in next release
            $horizontalOffset = 0;
            $verticalOffset = 0;
            $padding = 0;

            if ($verticalAlignment === WatermarkAlignment::V_BOTTOM) {
                $verticalOffset *= -1;
            }

            if ($horizontalAlignment === WatermarkAlignment::H_RIGHT) {
                $horizontalOffset *= -1;
            }

            // Set the base x-axis and y-axis values
            $xAxis = $horizontalOffset + $padding;
            $yAxis = $verticalOffset + $padding;

            // Set the vertical position
            if ($verticalAlignment === WatermarkAlignment::V_MIDDLE) {
                $yAxis += floor($sourceHeight / 2) - floor($watermarkHeight / 2);
            } elseif ($verticalAlignment === WatermarkAlignment::V_BOTTOM) {
                $yAxis += $sourceHeight - $watermarkHeight;
            }

            // Set the horizontal position
            if ($horizontalAlignment === WatermarkAlignment::H_CENTER) {
                $xAxis += floor($sourceWidth / 2) - floor($watermarkWidth / 2);
            } elseif ($horizontalAlignment === WatermarkAlignment::H_RIGHT) {
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

            $xTransparency = $this->watermark['xTransparency'];
            $yTransparency = $this->watermark['yTransparency'];

            // Set RGB values for text and shadow
            $rgba = imagecolorat($watermarkResource, $xTransparency, $yTransparency);
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
                    imagecolorat($watermarkResource, $xTransparency, $yTransparency)
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
                    $this->watermark['opacity']
                );
            }

            // We can preserve transparency for PNG or WEBP images
            if (in_array($sourceMimeType, [MimeType::PNG, MimeType::WEBP], true)) {
                imagealphablending($sourceResource, false);
                imagesavealpha($sourceResource, true);
            }

            self::saveImage($sourceResource, $sourceFilepath, $sourceMimeType);
        } finally {
            imagedestroy($sourceResource);
            imagedestroy($watermarkResource);
        }
    }

    /**
     * @throws FileManagerException
     */
    private static function saveImage(GdImage $resource, string $filepath, MimeType $mimeType): void
    {
        switch ($mimeType) {
            case MimeType::GIF:
                if (function_exists('imagegif') === false) {
                    throw FileManagerException::unsupportedImageCreate('gif');
                }

                if (@imagegif($resource, $filepath) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::JPEG:
                if (function_exists('imagejpeg') === false) {
                    throw FileManagerException::unsupportedImageCreate('jpg');
                }

                if (@imagejpeg($resource, $filepath, 100) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::PNG:
                if (function_exists('imagepng') === false) {
                    throw FileManagerException::unsupportedImageCreate('png');
                }

                if (@imagepng($resource, $filepath) === false) {
                    throw FileManagerException::failToSaveImage();
                }
                break;
            case MimeType::WEBP:
                if (function_exists('imagewebp') === false) {
                    throw FileManagerException::unsupportedImageCreate('webp');
                }

                if (@imagewebp($resource, $filepath, 100) === false) {
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
    private static function createImageResource(string $filepath, MimeType $mimeType): GdImage|bool
    {
        switch ($mimeType) {
            case MimeType::GIF:
                if (function_exists('imagecreatefromgif') === false) {
                    throw FileManagerException::unsupportedImageCreate('gif');
                }

                return imagecreatefromgif($filepath);
            case MimeType::JPEG:
                if (function_exists('imagecreatefromjpeg') === false) {
                    throw FileManagerException::unsupportedImageCreate('jpg');
                }

                return imagecreatefromjpeg($filepath);
            case MimeType::PNG:
                if (function_exists('imagecreatefrompng') === false) {
                    throw FileManagerException::unsupportedImageCreate('png');
                }

                return imagecreatefrompng($filepath);
            case MimeType::WEBP:
                if (function_exists('imagecreatefromwebp') === false) {
                    throw FileManagerException::unsupportedImageCreate('webp');
                }

                return imagecreatefromwebp($filepath);
            default:
                throw FileManagerException::unsupportedImageCreate();
        }
    }
}
