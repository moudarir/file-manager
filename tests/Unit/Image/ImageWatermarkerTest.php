<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Image;

use DateTimeImmutable;
use Moudarir\File\File;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageWatermarkConfig;
use Moudarir\FileManager\Image\ImageWatermarker;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageWatermarkerTest extends TestCase
{
    private string $sourceDirectory;

    private string $resizeDirectory;

    private string $watermarkDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'file-manager-watermark-source-'
            .uniqid('', true);

        $this->resizeDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'file-manager-watermark-resize-'
            .uniqid('', true);

        $this->watermarkDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'file-manager-watermark-overlay-'
            .uniqid('', true);

        mkdir($this->sourceDirectory, 0777, true);
        mkdir($this->resizeDirectory, 0777, true);
        mkdir($this->watermarkDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sourceDirectory);
        $this->removeDirectory($this->resizeDirectory);
        $this->removeDirectory($this->watermarkDirectory);

        parent::tearDown();
    }

    #[Test]
    public function itDoesNothingWhenCollectionIsEmpty(): void
    {
        $collection = new UploadedFileCollection('files', []);

        ImageWatermarker::create($collection, $this->createConfig());

        self::assertTrue($collection->isEmpty());
    }

    #[Test]
    public function itDoesNothingWhenNoWatermarksMatchConfiguredThumbnails(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = ImageWatermarkConfig::create([
            'resizePath' => $this->resizeDirectory,
            'dateFormat' => null,
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'watermarks' => [],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertSame($sourceFilepath, $file->filepath());
    }

    #[Test]
    public function itSkipsNonImageFiles(): void
    {
        $sourceFilepath = $this->createTextFile('document.txt');

        $file = $this->createUploadedFile($sourceFilepath);

        $collection = new UploadedFileCollection('files', [$file]);

        ImageWatermarker::create($collection, $this->createConfig());

        self::assertFileExists($sourceFilepath);
    }

    #[Test]
    public function itProcessesOnlyWatermarksMatchingConfiguredThumbnails(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $largeDirectory = $this->resizeDirectory .DIRECTORY_SEPARATOR .'large';

        mkdir($largeDirectory, 0777, true);

        copy(
            $sourceFilepath,
            $largeDirectory.DIRECTORY_SEPARATOR.$file->basename()
        );

        $overlayFilepath = $this->createOverlay('watermark.png');

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 128, 'height' => 128],
            ],
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
                'small' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($largeDirectory.DIRECTORY_SEPARATOR.$file->basename());
    }

    #[Test]
    public function itThrowsWhenConfiguredThumbnailDoesNotExist(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $overlayFilepath = $this->createOverlay('watermark.png');

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        $this->expectException(FileManagerException::class);

        ImageWatermarker::create($collection, $config);
    }

    #[Test]
    public function itSkipsMissingOverlayFile(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $largeDirectory = $this->resizeDirectory .DIRECTORY_SEPARATOR .'large';

        mkdir($largeDirectory, 0777, true);

        $thumbnailPath = $largeDirectory
            .DIRECTORY_SEPARATOR
            .$file->basename();

        copy($sourceFilepath, $thumbnailPath);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $this->watermarkDirectory
                        .DIRECTORY_SEPARATOR
                        .'missing.png',
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itThrowsWhenOverlayIsNotAValidImage(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $largeDirectory = $this->resizeDirectory .DIRECTORY_SEPARATOR .'large';

        mkdir($largeDirectory, 0777, true);

        copy(
            $sourceFilepath,
            $largeDirectory.DIRECTORY_SEPARATOR.$file->basename()
        );

        $overlayFilepath = $this->createTextFile('invalid.png');

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        $this->expectException(FileManagerException::class);

        ImageWatermarker::create($collection, $config);
    }

    #[Test]
    public function itThrowsWhenOverlayImageTypeIsUnsupported(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $largeDirectory = $this->resizeDirectory .DIRECTORY_SEPARATOR .'large';

        mkdir($largeDirectory, 0777, true);

        copy(
            $sourceFilepath,
            $largeDirectory.DIRECTORY_SEPARATOR.$file->basename()
        );

        $overlayFilepath = $this->createBmpOverlay('watermark.bmp');

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        $this->expectException(FileManagerException::class);

        ImageWatermarker::create($collection, $config);
    }

    #[Test]
    public function itAppliesWatermarkToTheConfiguredThumbnail(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 267);

        $file = $this->createUploadedFile($sourceFilepath);

        $largeDirectory = $this->resizeDirectory .DIRECTORY_SEPARATOR .'large';

        mkdir($largeDirectory, 0777, true);

        $thumbnailPath = $largeDirectory .DIRECTORY_SEPARATOR .$file->basename();

        copy($sourceFilepath, $thumbnailPath);

        $overlayFilepath = $this->createOverlay('watermark.png');

        $before = md5_file($thumbnailPath);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
        self::assertNotSame($before, md5_file($thumbnailPath));
    }

    #[Test]
    public function itUsesCenterAlignmentByDefault(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 400);

        $file = $this->createUploadedFile($sourceFilepath);

        $thumbnailPath = $this->prepareThumbnail($file, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png', 100, 100);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'horizontalAlignment' => WatermarkAlignment::H_CENTER,
                    'verticalAlignment' => WatermarkAlignment::V_MIDDLE,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itUsesRightBottomAlignment(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 400);

        $file = $this->createUploadedFile($sourceFilepath);

        $thumbnailPath = $this->prepareThumbnail($file, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png', 100, 100);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
                    'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itFallsBackToDefaultAlignmentWhenAlignmentIsInvalid(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 400);

        $file = $this->createUploadedFile($sourceFilepath);

        $thumbnailPath = $this->prepareThumbnail($file, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png', 100, 100);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'horizontalAlignment' => 'invalid',
                    'verticalAlignment' => 'invalid',
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itFallsBackToDefaultOpacityWhenOpacityIsInvalid(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 400);

        $file = $this->createUploadedFile($sourceFilepath);

        $thumbnailPath = $this->prepareThumbnail($file, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png', 100, 100);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'opacity' => 0,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itFallsBackToDefaultTransparencyCoordinatesWhenInvalid(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 400, 400);

        $file = $this->createUploadedFile($sourceFilepath);

        $thumbnailPath = $this->prepareThumbnail($file, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png', 100, 100);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'xTransparency' => -1,
                    'yTransparency' => 128,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertFileExists($thumbnailPath);
    }

    #[Test]
    public function itUsesTheActualDimensionsOfEachThumbnail(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        $largePath = $this->prepareThumbnail($file, 'large', 400, 267);
        $mediumPath = $this->prepareThumbnail($file, 'medium', 128, 85);

        $overlayFilepath = $this->createOverlay('watermark.png', 40, 40);

        $collection = new UploadedFileCollection('files', [$file]);

        $config = $this->createConfig([
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 128, 'height' => 128],
            ],
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                    'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
                    'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
                ],
                'medium' => [
                    'overlayFilepath' => $overlayFilepath,
                    'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
                    'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
                ],
            ],
        ]);

        $largeBefore = md5_file($largePath);
        $mediumBefore = md5_file($mediumPath);

        ImageWatermarker::create($collection, $config);

        self::assertNotSame($largeBefore, md5_file($largePath));
        self::assertNotSame($mediumBefore, md5_file($mediumPath));
    }

    #[Test]
    public function itProcessesMultipleFiles(): void
    {
        $firstSource = $this->createImage('first.jpg', 400, 400);
        $secondSource = $this->createImage('second.jpg', 400, 400);

        $firstFile = $this->createUploadedFile($firstSource);
        $secondFile = $this->createUploadedFile($secondSource);

        $firstPath = $this->prepareThumbnail($firstFile, 'large');
        $secondPath = $this->prepareThumbnail($secondFile, 'large');

        $overlayFilepath = $this->createOverlay('watermark.png');

        $firstBefore = md5_file($firstPath);
        $secondBefore = md5_file($secondPath);

        $collection = new UploadedFileCollection('files', [$firstFile, $secondFile]);

        $config = $this->createConfig([
            'watermarks' => [
                'large' => [
                    'overlayFilepath' => $overlayFilepath,
                ],
            ],
        ]);

        ImageWatermarker::create($collection, $config);

        self::assertNotSame($firstBefore, md5_file($firstPath));
        self::assertNotSame($secondBefore, md5_file($secondPath));
    }

    private function createConfig(array $overrides = []): ImageWatermarkConfig
    {
        $config = array_replace_recursive(
            [
                'resizePath' => $this->resizeDirectory,
                'dateFormat' => null,
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                ],
                'watermarks' => [
                    'large' => [
                        'overlayFilepath' => $this->watermarkDirectory .DIRECTORY_SEPARATOR .'watermark.png',
                        'horizontalAlignment' => WatermarkAlignment::H_CENTER,
                        'verticalAlignment' => WatermarkAlignment::V_MIDDLE,
                        'opacity' => 19,
                        'xTransparency' => 4,
                        'yTransparency' => 4,
                    ],
                ],
            ],
            $overrides
        );

        return ImageWatermarkConfig::create($config);
    }

    private function createUploadedFile(string $filepath): UploadedFile
    {
        $file = File::create($filepath);
        $mimeType = $file->detection()->mimeType();
        $dimensions = null;

        if ($mimeType->isImage() === true) {
            $info = @getimagesize($filepath);

            self::assertIsArray($info);

            $dimensions = [
                'width' => $info[0],
                'height' => $info[1],
                'htmlAttributes' => $info[3],
            ];
        }

        return UploadedFile::create(
            $file->resource(),
            $mimeType,
            basename($filepath),
            new DateTimeImmutable(),
            $dimensions,
        );
    }

    private function prepareThumbnail(
        UploadedFile $file,
        string $thumb,
        ?int $width = null,
        ?int $height = null,
    ): string {
        $width ??= $file->imageWidth();
        $height ??= $file->imageHeight();

        $directory = $this->resizeDirectory .DIRECTORY_SEPARATOR .$thumb;

        if (is_dir($directory) === false) {
            mkdir($directory, 0777, true);
        }

        $source = $file->filepath();

        if ($width !== $file->imageWidth() || $height !== $file->imageHeight()) {
            $source = $this->createImage($file->basename(), $width, $height);
        }

        $destination = $directory .DIRECTORY_SEPARATOR .$file->basename();

        copy($source, $destination);

        return $destination;
    }

    private function createOverlay(
        string $filename,
        int $width = 50,
        int $height = 50,
    ): string {
        $filepath = $this->watermarkDirectory .DIRECTORY_SEPARATOR .$filename;

        $image = imagecreatetruecolor($width, $height);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha(
            $image,
            0,
            0,
            0,
            127
        );

        imagefill($image, 0, 0, $transparent);

        $color = imagecolorallocatealpha(
            $image,
            0,
            0,
            0,
            0
        );

        imagefilledrectangle(
            $image,
            10,
            10,
            $width - 10,
            $height - 10,
            $color
        );

        imagepng($image, $filepath);

        imagedestroy($image);

        return $filepath;
    }

    private function createBmpOverlay(string $filename): string
    {
        $filepath = $this->watermarkDirectory .DIRECTORY_SEPARATOR .$filename;

        $image = imagecreatetruecolor(50, 50);

        $color = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $color);

        imagebmp($image, $filepath);

        imagedestroy($image);

        return $filepath;
    }

    private function createImage(string $filename, int $width, int $height): string
    {
        $filepath = $this->sourceDirectory .DIRECTORY_SEPARATOR .$filename;

        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate(
            $image,
            255,
            255,
            255
        );

        imagefill($image, 0, 0, $background);

        imagejpeg($image, $filepath, 100);

        imagedestroy($image);

        return $filepath;
    }

    private function createTextFile(string $filename): string
    {
        $filepath = $this->watermarkDirectory .DIRECTORY_SEPARATOR .$filename;

        file_put_contents($filepath, 'test');

        return $filepath;
    }

    private function removeDirectory(string $directory): void
    {
        if (is_dir($directory) === false) {
            return;
        }

        $files = scandir($directory);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $directory .DIRECTORY_SEPARATOR .$file;

            if (is_dir($filepath) === true) {
                $this->removeDirectory($filepath);
                continue;
            }

            unlink($filepath);
        }

        rmdir($directory);
    }
}
