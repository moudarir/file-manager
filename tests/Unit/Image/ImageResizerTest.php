<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Image;

use DateTimeImmutable;
use Moudarir\File\File;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageResizeConfig;
use Moudarir\FileManager\Image\ImageResizer;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageResizerTest extends TestCase
{

    private string $sourceDirectory;

    private string $resizeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'file-manager-resize-source-'
            .uniqid('', true);

        $this->resizeDirectory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'file-manager-resize-destination-'
            .uniqid('', true);

        mkdir($this->sourceDirectory, 0777, true);
        mkdir($this->resizeDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->sourceDirectory);
        $this->removeDirectory($this->resizeDirectory);

        parent::tearDown();
    }

    #[Test]
    public function itDoesNothingWhenCollectionIsEmpty(): void
    {
        $collection = new UploadedFileCollection('files', []);

        ImageResizer::create($collection, ImageResizeConfig::create([
            'resizePath' => $this->resizeDirectory,
            'dateFormat' => null,
            'customDate' => null,
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'resizeQuality' => 85,
            'removeAfterResize' => false,
        ]));

        self::assertTrue($collection->isEmpty());
    }

    #[Test]
    public function itSkipsNonImageFiles(): void
    {
        $sourceFilepath = $this->createTextFile();

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig()
        );

        self::assertFileDoesNotExist(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'large'.DIRECTORY_SEPARATOR.$file->basename()
        );

        self::assertSame($sourceFilepath, $file->filepath());
    }

    #[Test]
    public function itCreatesAllConfiguredThumbnails(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                    'medium' => ['width' => 128, 'height' => 128],
                    'small' => ['width' => 50, 'height' => 50],
                ]
            ])
        );

        self::assertFileExists(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'large'.DIRECTORY_SEPARATOR.$file->basename()
        );

        self::assertFileExists(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'medium'.DIRECTORY_SEPARATOR.$file->basename()
        );

        self::assertFileExists(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'small'.DIRECTORY_SEPARATOR.$file->basename()
        );
    }

    #[Test]
    public function itPreservesTheImageRatio(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                ]
            ])
        );

        $dimensions = getimagesize($file->filepath());

        self::assertIsArray($dimensions);
        self::assertSame($file->imageWidth(), $dimensions[0]);
        self::assertSame($file->imageHeight(), $dimensions[1]);
    }

    #[Test]
    public function itUpdatesUploadedFileWithLargeThumbnailInformation(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                ]
            ])
        );

        $expectedFilepath = $this->resizeDirectory
            .DIRECTORY_SEPARATOR
            .'large'
            .DIRECTORY_SEPARATOR
            .$file->basename();

        self::assertSame($expectedFilepath, $file->filepath());
        self::assertSame(
            dirname($expectedFilepath) . DIRECTORY_SEPARATOR,
            $file->dirname()
        );
        self::assertSame(filesize($expectedFilepath), $file->filesize());

        self::assertSame(400, $file->imageWidth());
        self::assertSame(267, $file->imageHeight());
    }

    #[Test]
    public function itDoesNotUpdateUploadedFileWithMediumOrSmallThumbnailInformation(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                    'medium' => ['width' => 128, 'height' => 128],
                    'small' => ['width' => 50, 'height' => 50],
                ]
            ])
        );

        self::assertSame(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'large'.DIRECTORY_SEPARATOR.$file->basename(),
            $file->filepath()
        );

        self::assertSame(400, $file->imageWidth());
        self::assertSame(267, $file->imageHeight());
    }

    #[Test]
    public function itUsesTheOriginalFileAsSourceForEveryThumbnail(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 400],
                    'medium' => ['width' => 128, 'height' => 128],
                    'small' => ['width' => 50, 'height' => 50],
                ]
            ])
        );

        $large = getimagesize(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'large'.DIRECTORY_SEPARATOR.$file->basename()
        );

        $medium = getimagesize(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'medium'.DIRECTORY_SEPARATOR.$file->basename()
        );

        $small = getimagesize(
            $this->resizeDirectory.DIRECTORY_SEPARATOR.'small'.DIRECTORY_SEPARATOR.$file->basename()
        );

        self::assertSame(400, $large[0]);
        self::assertSame(267, $large[1]);

        self::assertSame(128, $medium[0]);
        self::assertSame(85, $medium[1]);

        self::assertSame(50, $small[0]);
        self::assertSame(33, $small[1]);
    }

    #[Test]
    public function itKeepsTheOriginalFileWhenRemoveAfterResizeIsFalse(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'removeAfterResize' => false,
            ])
        );

        self::assertFileExists($sourceFilepath);
    }

    #[Test]
    public function itRemovesTheOriginalFileWhenRemoveAfterResizeIsTrue(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'removeAfterResize' => true,
            ])
        );

        self::assertFileDoesNotExist($sourceFilepath);
        self::assertFileExists($file->filepath());
    }

    #[Test]
    public function itCreatesDateDirectoryWhenDateFormatIsConfigured(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'dateFormat' => 'Y/m/d',
            ])
        );

        $datePath = $file->createdAt()->format('Y/m/d');
        $expectedFilepath = $this->resizeDirectory
            .DIRECTORY_SEPARATOR
            .$datePath
            .DIRECTORY_SEPARATOR
            .'large'
            .DIRECTORY_SEPARATOR
            .$file->basename();

        self::assertFileExists($expectedFilepath);
        self::assertSame($expectedFilepath, $file->filepath());
    }

    #[Test]
    public function itPreservesTheOriginalBasename(): void
    {
        $sourceFilepath = $this->createImage('my-image.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig()
        );

        self::assertSame('my-image.jpg', basename($file->filepath()));
    }

    #[Test]
    public function itResizesEveryFileInTheCollection(): void
    {
        $firstSource = $this->createImage('first.jpg', 1200, 800);
        $secondSource = $this->createImage('second.jpg', 800, 1200);

        $firstFile = $this->createUploadedFile($firstSource);
        $secondFile = $this->createUploadedFile($secondSource);

        ImageResizer::create(
            new UploadedFileCollection(
                'files',
                [$firstFile, $secondFile]
            ),
            $this->createConfig()
        );

        self::assertFileExists($firstFile->filepath());
        self::assertFileExists($secondFile->filepath());

        self::assertSame(400, $firstFile->imageWidth());
        self::assertSame(267, $firstFile->imageHeight());

        self::assertSame(267, $secondFile->imageWidth());
        self::assertSame(400, $secondFile->imageHeight());
    }

    #[Test]
    public function itRejectsInvalidWidth(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        $this->expectException(FileManagerException::class);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 0, 'height' => 400],
                ]
            ])
        );
    }

    #[Test]
    public function itRejectsInvalidHeight(): void
    {
        $sourceFilepath = $this->createImage('source.jpg', 1200, 800);

        $file = $this->createUploadedFile($sourceFilepath);

        $this->expectException(FileManagerException::class);

        ImageResizer::create(
            new UploadedFileCollection('files', [$file]),
            $this->createConfig([
                'thumbs' => [
                    'large' => ['width' => 400, 'height' => 0],
                ]
            ])
        );
    }

    private function createConfig(array $overrides = []): ImageResizeConfig
    {
        $config = [
            'resizePath' => $this->resizeDirectory,
            'dateFormat' => null,
            'customDate' => null,
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'resizeQuality' => 85,
            'removeAfterResize' => false,
        ];

        return ImageResizeConfig::create(array_replace_recursive($config, $overrides));
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

    private function createImage(string $filename, int $width, int $height): string
    {
        $filepath = $this->sourceDirectory.DIRECTORY_SEPARATOR.$filename;

        $image = imagecreatetruecolor($width, $height);

        imagefill(
            $image,
            0,
            0,
            imagecolorallocate($image, 255, 255, 255)
        );

        imagejpeg($image, $filepath, 100);

        imagedestroy($image);

        return $filepath;
    }

    private function createTextFile(): string
    {
        $filepath = $this->sourceDirectory.DIRECTORY_SEPARATOR.'document.txt';

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

            $filepath = $directory.DIRECTORY_SEPARATOR.$file;

            if (is_dir($filepath) === true) {
                $this->removeDirectory($filepath);
                continue;
            }

            unlink($filepath);
        }

        rmdir($directory);
    }
}
