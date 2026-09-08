<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Image;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\File;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageCropConfig;
use Moudarir\FileManager\Image\ImageCropper;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageCropperTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'file-manager-crop-tests-'
            . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->directory, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[Test]
    public function missingConfigurationThrowsException(): void
    {
        $collection = new UploadedFileCollection('file', []);
        $config = $this->createCropConfig();

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains(
            'The configuration of `Image cropping` module is missing.'
        );

        ImageCropper::create($collection, $config, '');
    }

    #[Test]
    public function whitespaceConfigurationThrowsException(): void
    {
        $collection = new UploadedFileCollection('file', []);
        $config = $this->createCropConfig();

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains(
            'The configuration of `Image cropping` module is missing.'
        );

        ImageCropper::create($collection, $config, '   ');
    }

    #[Test]
    public function invalidJsonConfigurationThrowsException(): void
    {
        $collection = new UploadedFileCollection('file', []);
        $config = $this->createCropConfig();

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains(
            'The configuration of `Image cropping` module is missing.'
        );

        ImageCropper::create($collection, $config, '{"width":');
    }

    #[Test]
    public function validConfigurationCreatesCropper(): void
    {
        $collection = new UploadedFileCollection('file', []);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(),
            $this->createCroppingConfig(),
        );

        self::assertInstanceOf(ImageCropper::class, $cropper);

        $cropper->crop();

        self::assertTrue(true);
    }

    #[Test]
    public function emptyCollectionDoesNothing(): void
    {
        $collection = new UploadedFileCollection('file', []);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(),
            $this->createCroppingConfig(),
        );

        $cropper->crop();

        self::assertTrue($collection->isEmpty());
    }

    #[Test]
    public function nonImageFileIsIgnored(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'document.txt';

        self::assertNotFalse(
            file_put_contents($filepath, 'This is not an image.')
        );

        $fileResource = File::create($filepath)->resource();
        $file = UploadedFile::create(
            $fileResource,
            MimeType::PDF,
            'document.pdf',
            new DateTimeImmutable(),
            $fileResource->filesize(),
        );

        $collection = new UploadedFileCollection('file', [$file]);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(),
            $this->createCroppingConfig(),
        );

        $originalContent = file_get_contents($filepath);

        $cropper->crop();

        self::assertSame($originalContent, file_get_contents($filepath));
        self::assertNull($file->imageWidth());
        self::assertNull($file->imageHeight());
    }

    #[Test]
    public function pngImageIsCropped(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 100, 100);

        $file = $this->createUploadedImage(
            $filepath,
            MimeType::PNG,
            100,
            100,
        );
        $originalFilesize = $file->filesize();

        $collection = new UploadedFileCollection('file', [$file]);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(50, 50),
            json_encode([
                'x' => 0,
                'y' => 0,
                'width' => 100,
                'height' => 100,
                'rotate' => 0,
            ], JSON_THROW_ON_ERROR),
        );

        $cropper->crop();
        $dimensions = getimagesize($filepath);

        self::assertNotFalse($dimensions);
        self::assertSame(50, $dimensions[0]);
        self::assertSame(50, $dimensions[1]);

        self::assertSame(50, $file->imageWidth());
        self::assertSame(50, $file->imageHeight());
        self::assertSame('width="50" height="50"', $file->imageDimensions()['htmlAttributes']);

        self::assertSame(filesize($filepath), $file->filesize());
        self::assertNotSame($originalFilesize, $file->filesize());
    }

    #[Test]
    public function cropUpdatesUploadedFileDimensions(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 100, 80);

        $file = $this->createUploadedImage(
            $filepath,
            MimeType::PNG,
            100,
            80,
        );

        $collection = new UploadedFileCollection('file', [$file]);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(40, 30),
            json_encode([
                'x' => 10,
                'y' => 10,
                'width' => 60,
                'height' => 50,
                'rotate' => 0,
            ], JSON_THROW_ON_ERROR),
        );

        $cropper->crop();

        self::assertSame(
            [
                'width' => 40,
                'height' => 30,
                'htmlAttributes' => 'width="40" height="30"',
            ],
            $file->imageDimensions(),
        );

        self::assertSame(40, $file->imageWidth());
        self::assertSame(30, $file->imageHeight());
    }

    #[Test]
    public function cropWithRotationProducesConfiguredDimensions(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 100, 60);

        $file = $this->createUploadedImage(
            $filepath,
            MimeType::PNG,
            100,
            60,
        );

        $collection = new UploadedFileCollection('file', [$file]);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(40, 40),
            json_encode([
                'x' => 0,
                'y' => 0,
                'width' => 60,
                'height' => 100,
                'rotate' => 90,
            ], JSON_THROW_ON_ERROR),
        );

        $cropper->crop();

        $dimensions = getimagesize($filepath);

        self::assertNotFalse($dimensions);
        self::assertSame(40, $dimensions[0]);
        self::assertSame(40, $dimensions[1]);

        self::assertSame(40, $file->imageWidth());
        self::assertSame(40, $file->imageHeight());
    }

    #[Test]
    public function cropWithNegativePositionProducesConfiguredDimensions(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'image.png';

        $this->createPngFile($filepath, 100, 100);

        $file = $this->createUploadedImage(
            $filepath,
            MimeType::PNG,
            100,
            100,
        );

        $collection = new UploadedFileCollection('file', [$file]);
        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(50, 50),
            json_encode([
                'x' => -20,
                'y' => -10,
                'width' => 100,
                'height' => 100,
                'rotate' => 0,
            ], JSON_THROW_ON_ERROR),
        );

        $cropper->crop();

        $dimensions = getimagesize($filepath);

        self::assertNotFalse($dimensions);
        self::assertSame(50, $dimensions[0]);
        self::assertSame(50, $dimensions[1]);

        self::assertSame(50, $file->imageWidth());
        self::assertSame(50, $file->imageHeight());
    }

    #[Test]
    public function pngCropPreservesTransparency(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'transparent.png';

        $this->createTransparentPngFile($filepath, 100, 100);

        $file = $this->createUploadedImage(
            $filepath,
            MimeType::PNG,
            100,
            100,
        );

        $collection = new UploadedFileCollection('file', [$file]);

        $cropper = ImageCropper::create(
            $collection,
            $this->createCropConfig(50, 50),
            json_encode([
                'x' => 0,
                'y' => 0,
                'width' => 100,
                'height' => 100,
                'rotate' => 0,
            ], JSON_THROW_ON_ERROR),
        );

        $cropper->crop();

        $image = imagecreatefrompng($filepath);

        self::assertNotFalse($image);

        $alpha = imagecolorat($image, 0, 0);
        $alpha = ($alpha >> 24) & 0x7F;

        self::assertSame(127, $alpha);

        imagedestroy($image);
    }

    private function createCropConfig(int $width = 400, int $height = 400): ImageCropConfig
    {
        return ImageCropConfig::create([
            'cropRatioWidth' => $width,
            'cropRatioHeight' => $height,
        ]);
    }

    private function createUploadedImage(
        string $filepath,
        MimeType $mimeType,
        int $width,
        int $height,
    ): UploadedFile {
        $fileResource = File::create($filepath)->resource();

        return UploadedFile::create(
            $fileResource,
            $mimeType,
            basename($filepath),
            new DateTimeImmutable(),
            $fileResource->filesize(),
            [
                'width' => $width,
                'height' => $height,
                'htmlAttributes' => 'width="'.$width.'" height="'.$height.'"',
            ],
        );
    }

    private function createCroppingConfig(
        int $x = 0,
        int $y = 0,
        int $width = 100,
        int $height = 100,
        int $rotate = 0,
    ): string {
        return json_encode([
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'rotate' => $rotate,
        ], JSON_THROW_ON_ERROR);
    }

    private function createPngFile(string $filepath, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);

        self::assertNotFalse($image);
        self::assertTrue(imagepng($image, $filepath));

        imagedestroy($image);
    }

    private function createTransparentPngFile(string $filepath, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);

        self::assertNotFalse($image);
        self::assertTrue(imagesavealpha($image, true));

        $transparent = imagecolorallocatealpha(
            $image,
            0,
            0,
            0,
            127,
        );

        self::assertNotFalse($transparent);
        self::assertTrue(
            imagefill($image, 0, 0, $transparent)
        );
        self::assertTrue(imagepng($image, $filepath));

        imagedestroy($image);
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

            $filepath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filepath) === true) {
                $this->removeDirectory($filepath);
                continue;
            }

            unlink($filepath);
        }

        rmdir($directory);
    }
}
