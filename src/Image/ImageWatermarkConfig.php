<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Image;

use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Helpers\Common;
use Moudarir\FileManager\Helpers\ValidateParam;

final readonly class ImageWatermarkConfig
{

    private const array DEFAULT_CONFIG = [
        'overlayFilepath' => '',
        'horizontalAlignment' => WatermarkAlignment::H_CENTER,
        'verticalAlignment' => WatermarkAlignment::V_MIDDLE,
        'opacity' => 19,
        'xTransparency' => 4,
        'yTransparency' => 4,
    ];

    private function __construct(public array $watermarks)
    {
    }

    /**
     * @throws FileManagerException
     */
    public static function create(array $config): self
    {
        $watermarks = array_map(
            fn ($watermark) => Common::prepareConfig(self::DEFAULT_CONFIG, $watermark),
            $config['watermarks']
        );

        foreach ($watermarks as $watermark) {
            $overlayFilepath = $watermark['overlayFilepath'];

            is_file($overlayFilepath) ||
                throw FileManagerException::fileNotExists($overlayFilepath);

            // Sets the horizontal alignment for the watermark image.
            $horizontalAlignment = $watermark['horizontalAlignment'];

            if (
                $horizontalAlignment instanceof WatermarkAlignment === false ||
                $horizontalAlignment->isHorizontal() === false
            ) {
                throw FileManagerException::invalidKeyValue('horizontalAlignment');
            }

            // Sets the vertical alignment for the watermark image.
            $verticalAlignment = $watermark['verticalAlignment'];
            if (
                $verticalAlignment instanceof WatermarkAlignment === false ||
                $verticalAlignment->isVertical() === false
            ) {
                throw FileManagerException::invalidKeyValue('verticalAlignment');
            }

            ValidateParam::isBetween($watermark, 'opacity');

            ValidateParam::isBetween($watermark, ['xTransparency', 'yTransparency'], [0, 127]);
        }

        return new self($watermarks);
    }
}
