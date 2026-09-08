<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use Moudarir\Helpers\JsonHelper;

final readonly class ImageCropper
{

    private function __construct(
        private UploadedFileCollection $uploadedFileCollection,
        private ImageCropConfig        $config,
        private array                  $cropConfig,
    ) {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(
        UploadedFileCollection $uploadedFileCollection,
        ImageCropConfig $config,
        string $croppingConfig,
    ): self
    {
        if (trim($croppingConfig) === '' || ($cropConfig = JsonHelper::decode($croppingConfig)) === null) {
            throw FileManagerException::missingConfig("Image cropping");
        }

        return new self(
            $uploadedFileCollection,
            $config,
            $cropConfig,
        );
    }

    /**
     * @throws FileManagerException
     */
    public function crop(): void
    {
        if ($this->uploadedFileCollection->isEmpty()) {
            return;
        }

        foreach ($this->uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            $filepath = $file->filepath();
            $imageSource = match ($file->mimeType()) {
                MimeType::GIF => imagecreatefromgif($filepath),
                MimeType::JPEG => imagecreatefromjpeg($filepath),
                MimeType::PNG => imagecreatefrompng($filepath),
                MimeType::WEBP => imagecreatefromwebp($filepath),
                default => false,
            };

            if ($imageSource === false) {
                throw FileManagerException::unableReadPictureSource();
            }

            $imageSourceW = $file->imageWidth();
            $imageSourceH = $file->imageHeight();
            $degrees = $this->cropConfig['rotate'];

            // Rotate the source image
            if (is_numeric($degrees) && $degrees != 0) {
                // PHP's degrees is opposite to CSS's degrees
                $newImageSource = imagerotate(
                    $imageSource,
                    -$degrees,
                    imagecolorallocatealpha($imageSource, 0, 0, 0, 127)
                );

                imagedestroy($imageSource);

                if ($newImageSource === false) {
                    throw FileManagerException::errorCroppingImage();
                }

                $imageSource = $newImageSource;

                $deg = abs($degrees) % 180;
                $arc = ($deg > 90 ? (180 - $deg) : $deg) * M_PI / 180;

                $imageSourceW = ($file->imageWidth() * cos($arc)) + ($file->imageHeight() * sin($arc));
                $imageSourceH = ($file->imageWidth() * sin($arc)) + ($file->imageHeight() * cos($arc));

                // Fix rotated image miss 1px issue when degrees < 0
                $imageSourceW -= 1;
                $imageSourceH -= 1;
            }

            $tmpImageW = $this->cropConfig['width'];
            $tmpImageH = $this->cropConfig['height'];
            $cropImageW = $this->config->cropRatioWidth;
            $cropImageH = $this->config->cropRatioHeight;

            $sourceX = $this->cropConfig['x'];
            $sourceY = $this->cropConfig['y'];
            $sourceW = 0;
            $sourceH = 0;

            $cropX = 0;
            $cropY = 0;
            $cropW = 0;
            $cropH = 0;

            if ($sourceX <= -$tmpImageW || $sourceX > $imageSourceW) {
                $sourceX = 0;
            }
            if ($sourceX <= 0) {
                $cropX = -$sourceX;
                $sourceX = 0;
                $sourceW = $cropW = min($imageSourceW, $tmpImageW + $sourceX);
            } elseif ($sourceX <= $imageSourceW) {
                $sourceW = $cropW = min($tmpImageW, $imageSourceW - $sourceX);
            }

            if ($sourceW <= 0 || $sourceY <= -$tmpImageH || $sourceY > $imageSourceH) {
                $sourceY = 0;
            }
            if ($sourceY <= 0) {
                $cropY = -$sourceY;
                $sourceY = 0;
                $sourceH = $cropH = min($imageSourceH, $tmpImageH + $sourceY);
            } elseif ($sourceY <= $imageSourceH) {
                $sourceH = $cropH = min($tmpImageH, $imageSourceH - $sourceY);
            }

            // Scale to destination position and size
            $ratio = $tmpImageW / $cropImageW;
            $cropX /= $ratio;
            $cropY /= $ratio;
            $cropW /= $ratio;
            $cropH /= $ratio;

            $cropImage = imagecreatetruecolor($cropImageW, $cropImageH);

            // Add transparent background to destination image
            if (in_array($file->mimeType(), [MimeType::PNG, MimeType::WEBP])) {
                imagefill(
                    $cropImage,
                    0,
                    0,
                    imagecolorallocatealpha($cropImage, 0, 0, 0, 127)
                );
                imagesavealpha($cropImage, true);
            }

            $result = imagecopyresampled(
                $cropImage,
                $imageSource,
                (int)$cropX,
                (int)$cropY,
                (int)$sourceX,
                (int)$sourceY,
                (int)$cropW,
                (int)$cropH,
                (int)$sourceW,
                (int)$sourceH
            );

            if ($result === false) {
                throw FileManagerException::errorCroppingImage();
            }

            $successOnSave = match ($file->mimeType()) {
                MimeType::GIF => imagegif($cropImage, $filepath),
                MimeType::JPEG => imagejpeg($cropImage, $filepath, 100),
                MimeType::PNG => imagepng($cropImage, $filepath, 0),
                MimeType::WEBP => imagewebp($cropImage, $filepath, 100),
                default => false,
            };

            if ($successOnSave === false) {
                throw FileManagerException::errorSavingImage();
            }

            imagedestroy($imageSource);
            imagedestroy($cropImage);

            if (is_file($filepath) === false) {
                throw FileManagerException::errorSavingImage();
            }

            $file
                ->changeFilesize(@filesize($filepath) ?: 0)
                ->changeImageDimensions([
                    'width' => $cropImageW,
                    'height' => $cropImageH,
                    'htmlAttributes' => 'width="'.$cropImageW.'" height="'.$cropImageH.'"',
                ]);
        }
    }
}
