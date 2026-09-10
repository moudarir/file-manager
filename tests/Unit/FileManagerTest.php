<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\FileManager;
use Moudarir\FileManager\FileManagerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'moudarir-file-manager-'
            .uniqid('', true);

        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[Test]
    public function itThrowsWhenUploadIsNotRequested(): void
    {
        $manager = $this->createFileManager();

        $this->expectException(FileManagerException::class);
        $this->expectExceptionMessageIsOrContains('The `upload()` method is mandatory before calling `files()`.');

        $manager->files();
    }

    #[Test]
    public function itReturnsItselfForFluentMethods(): void
    {
        $manager = $this->createFileManager();
        var_dump($this->croppingConfig());

        self::assertSame($manager, $manager->upload());
        self::assertSame($manager, $manager->crop($this->croppingConfig()));
        self::assertSame($manager, $manager->resize());
        self::assertSame($manager, $manager->watermark());
        self::assertSame($manager, $manager->convert());
    }

    #[Test]
    public function itUploadsFileFromExplicitFilepath(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager([
            'allowedMimeTypes' => [MimeType::JPEG],
        ]);

        $files = $manager->upload($source)->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertFileExists($file->filepath());
        self::assertSame(MimeType::JPEG, $file->mimeType());
    }

    #[Test]
    public function itSkipsCropWhenCroppingConfigIsEmpty(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager();

        $files = $manager
            ->upload($source)
            ->crop('')
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame(800, $file->imageWidth());
        self::assertSame(600, $file->imageHeight());
    }

    #[Test]
    public function itCropsUploadedImage(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager();

        $files = $manager
            ->upload($source)
            ->crop($this->croppingConfig())
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame(400, $file->imageWidth());
        self::assertSame(400, $file->imageHeight());
    }

    #[Test]
    public function itResizesUploadedImage(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertFileExists($file->filepath());
        self::assertSame(400, $file->imageWidth());
        self::assertSame(300, $file->imageHeight());
    }

    #[Test]
    public function itSkipsWatermarkWhenResizeIsNotRequested(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'watermarks' => $this->watermarks($watermark),
        ]);

        $files = $manager
            ->upload($source)
            ->watermark()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame(800, $file->imageWidth());
        self::assertSame(600, $file->imageHeight());
    }

    #[Test]
    public function itAppliesWatermarkAfterResize(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'watermarks' => $this->watermarks($watermark),
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->watermark()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertFileExists($file->filepath());
        self::assertSame(400, $file->imageWidth());
        self::assertSame(300, $file->imageHeight());
    }

    #[Test]
    public function itConvertsUploadedImage(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager();

        $files = $manager
            ->upload($source)
            ->convert()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertFileExists($file->filepath());
        self::assertSame(MimeType::WEBP, $file->mimeType());
        self::assertSame('webp', $file->extension());
        self::assertSame('source.webp', $file->basename());
    }

    #[Test]
    public function itResizesAndConvertsUploadedImage(): void
    {
        $source = $this->createJpeg('source.jpg');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 200, 'height' => 200],
            ],
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->convert()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertSame(MimeType::WEBP, $file->mimeType());
        self::assertSame('webp', $file->extension());
        self::assertSame('source.webp', $file->basename());

        self::assertFileExists(
            $this->directory
            .DIRECTORY_SEPARATOR
            .'resize'
            .DIRECTORY_SEPARATOR
            .'large'
            .DIRECTORY_SEPARATOR
            .'source.webp'
        );

        self::assertFileExists(
            $this->directory
            .DIRECTORY_SEPARATOR
            .'resize'
            .DIRECTORY_SEPARATOR
            .'medium'
            .DIRECTORY_SEPARATOR
            .'source.webp'
        );
    }

    #[Test]
    public function itExecutesTheCompleteImageProcessingPipeline(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 200, 'height' => 200],
            ],
            'watermarks' => $this->watermarks($watermark),
        ]);

        $files = $manager
            ->upload($source)
            ->crop($this->croppingConfig(600, 600))
            ->resize()
            ->watermark()
            ->convert()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);
        self::assertFileExists($file->filepath());
        self::assertSame(MimeType::WEBP, $file->mimeType());
        self::assertSame('webp', $file->extension());
        self::assertSame('source.webp', $file->basename());

        self::assertFileExists(
            $this->directory
            .DIRECTORY_SEPARATOR
            .'resize'
            .DIRECTORY_SEPARATOR
            .'large'
            .DIRECTORY_SEPARATOR
            .'source.webp'
        );

        self::assertFileExists(
            $this->directory
            .DIRECTORY_SEPARATOR
            .'resize'
            .DIRECTORY_SEPARATOR
            .'medium'
            .DIRECTORY_SEPARATOR
            .'source.webp'
        );
    }

    private function createFileManager(array $config = []): FileManager
    {
        $config = array_merge(
            [
                'field' => 'file',
                'uploadPath' => $this->directory .DIRECTORY_SEPARATOR .'upload',
                'overwrite' => true,
                'encryptName' => false,
            ],
            $config
        );

        return new FileManager(FileManagerConfig::create($config));
    }

    private function croppingConfig(
        int $width = 400,
        int $height = 400,
        int $x = 0,
        int $y = 0,
        int|float $rotate = 0,
    ): string {
        return json_encode([
            'rotate' => $rotate,
            'width' => $width,
            'height' => $height,
            'x' => $x,
            'y' => $y,
        ], JSON_THROW_ON_ERROR);
    }

    private function watermarks(string $overlayFilepath): array
    {
        return [
            'large' => [
                'overlayFilepath' => $overlayFilepath,
                'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
                'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
                'opacity' => 10,
                'xTransparency' => 5,
                'yTransparency' => 5,
            ],
        ];
    }

    private function createJpeg(string $filename, int $width = 800, int $height = 600): string
    {
        $filepath = $this->directory .DIRECTORY_SEPARATOR .$filename;

        $image = imagecreatetruecolor($width, $height);

        imagejpeg($image, $filepath, 90);

        imagedestroy($image);

        return $filepath;
    }

    private function createPng(string $filename, int $width = 100, int $height = 100): string
    {
        $filepath = $this->directory .DIRECTORY_SEPARATOR .$filename;

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

        imagepng($image, $filepath);

        imagedestroy($image);

        return $filepath;
    }

    private function removeDirectory(string $directory): void
    {
        if (is_dir($directory) === false) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $filepath = $directory .DIRECTORY_SEPARATOR .$item;

            if (is_dir($filepath) === true) {
                $this->removeDirectory($filepath);
                continue;
            }

            unlink($filepath);
        }

        rmdir($directory);
    }
}
