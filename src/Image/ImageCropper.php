<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Upload\UploadedFile;
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
        /**
         * @var UploadedFile $file
         */
        foreach ($this->uploadedFileCollection as $file) {
            if ($file->isImage() === false) {
                continue;
            }

            $filepath = $file->filepath();
            $cropImageW = $this->config->cropRatioWidth;
            $cropImageH = $this->config->cropRatioHeight;

            $imageResource = match ($file->mimeType()) {
                MimeType::GIF => imagecreatefromgif($filepath),
                MimeType::JPEG => imagecreatefromjpeg($filepath),
                MimeType::PNG => imagecreatefrompng($filepath),
                MimeType::WEBP => imagecreatefromwebp($filepath),
                default => false,
            };

            if ($imageResource === false) {
                throw FileManagerException::unableReadPictureSource();
            }

            $cropImageResource = imagecreatetruecolor($cropImageW, $cropImageH);

            try {
                $imageSourceW = $file->imageWidth();
                $imageSourceH = $file->imageHeight();
                $degrees = $this->cropConfig['rotate'];

                // Rotate the source image
                if (is_numeric($degrees) && $degrees != 0) {
                    // PHP's degrees is opposite to CSS's degrees
                    $newImageResource = imagerotate(
                        $imageResource,
                        -$degrees,
                        imagecolorallocatealpha($imageResource, 0, 0, 0, 127)
                    );

                    imagedestroy($imageResource);

                    if ($newImageResource === false) {
                        throw FileManagerException::errorCroppingImage();
                    }

                    $imageResource = $newImageResource;

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

                // Add transparent background to destination image
                if (in_array($file->mimeType(), [MimeType::PNG, MimeType::WEBP])) {
                    imagefill(
                        $cropImageResource,
                        0,
                        0,
                        imagecolorallocatealpha($cropImageResource, 0, 0, 0, 127)
                    );
                    imagesavealpha($cropImageResource, true);
                }

                $result = imagecopyresampled(
                    $cropImageResource,
                    $imageResource,
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
                    MimeType::GIF => imagegif($cropImageResource, $filepath),
                    MimeType::JPEG => imagejpeg($cropImageResource, $filepath, 100),
                    MimeType::PNG => imagepng($cropImageResource, $filepath, 0),
                    MimeType::WEBP => imagewebp($cropImageResource, $filepath, 100),
                    default => false,
                };

                if ($successOnSave === false) {
                    throw FileManagerException::errorSavingImage();
                }
            } finally {
                imagedestroy($imageResource);
                imagedestroy($cropImageResource);
            }

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
