<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit;

use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Config\FileManagerConfig;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\FileManager;
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
        $this->expectExceptionMessageIsOrContains(
            'The `upload()` method is mandatory before calling `files()`.'
        );

        $manager->files();
    }

    #[Test]
    public function itReturnsItselfForFluentMethods(): void
    {
        $manager = $this->createFileManager();

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

        $thumb = $file->thumbCollection()->get('large');

        self::assertFileExists($thumb->filepath());
        self::assertSame(400, $thumb->imageWidth());
        self::assertSame(300, $thumb->imageHeight());
    }

    #[Test]
    public function itAppliesWatermarkToOriginalWithoutResize(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'watermarks' => $this->watermarks(['original' => $watermark]),
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

        self::assertNotSame(
            $this->getImageContent($source),
            $this->getImageContent($file->filepath())
        );
    }

    #[Test]
    public function itSkipsThumbnailWatermarkWhenResizeIsNotRequested(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
            ],
            'watermarks' => $this->watermarks(['large' => $watermark]),
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

        self::assertSame(
            $this->getImageContent($file->filepath()),
            $this->getImageContent($source)
        );
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
            'watermarks' => $this->watermarks(['large' => $watermark]),
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->watermark()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);

        $thumb = $file->thumbCollection()->get('large');

        self::assertFileExists($thumb->filepath());
        self::assertSame(400, $thumb->imageWidth());
        self::assertSame(300, $thumb->imageHeight());

        self::assertNotSame(
            $this->getImageContent($source),
            $this->getImageContent($thumb->filepath())
        );
    }

    #[Test]
    public function itAppliesWatermarkToOriginalAndThumbnails(): void
    {
        $source = $this->createJpeg('source.jpg');
        $originalWatermark = $this->createPng('original-watermark.png');
        $largeWatermark = $this->createPng('large-watermark.png');
        $mediumWatermark = $this->createPng('medium-watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 200, 'height' => 200],
            ],
            'watermarks' => $this->watermarks([
                'original' => $originalWatermark,
                'large' => $largeWatermark,
                'medium' => $mediumWatermark,
            ]),
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->watermark()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);

        self::assertNotSame(
            $this->getImageContent($source),
            $this->getImageContent($file->filepath())
        );

        $thumbCollection = $file->thumbCollection();

        self::assertNotNull($thumbCollection);

        $large = $thumbCollection->get('large');
        $medium = $thumbCollection->get('medium');

        self::assertFileExists($large->filepath());
        self::assertFileExists($medium->filepath());

        self::assertSame(400, $large->imageWidth());
        self::assertSame(300, $large->imageHeight());

        self::assertSame(200, $medium->imageWidth());
        self::assertSame(150, $medium->imageHeight());
    }

    #[Test]
    public function itAppliesWatermarkOnlyToConfiguredThumbnails(): void
    {
        $source = $this->createJpeg('source.jpg');
        $watermark = $this->createPng('watermark.png');

        $manager = $this->createFileManager([
            'resizePath' => $this->directory.DIRECTORY_SEPARATOR.'resize',
            'thumbs' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 200, 'height' => 200],
            ],
            'watermarks' => $this->watermarks(['large' => $watermark]),
        ]);

        $files = $manager
            ->upload($source)
            ->resize()
            ->watermark()
            ->files();

        self::assertCount(1, $files);

        $file = $files->first();

        self::assertNotNull($file);

        $thumbCollection = $file->thumbCollection();

        self::assertNotNull($thumbCollection);

        self::assertTrue($thumbCollection->has('large'));
        self::assertTrue($thumbCollection->has('medium'));

        $large = $thumbCollection->get('large');
        $medium = $thumbCollection->get('medium');

        self::assertFileExists($large->filepath());
        self::assertFileExists($medium->filepath());

        self::assertSame(400, $large->imageWidth());
        self::assertSame(300, $large->imageHeight());

        self::assertSame(200, $medium->imageWidth());
        self::assertSame(150, $medium->imageHeight());
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

        self::assertSame(MimeType::JPEG, $file->mimeType());
        self::assertSame('jpg', $file->extension());
        self::assertSame('source.jpg', $file->basename());
        self::assertFileExists($file->filepath());

        $thumbCollection = $file->thumbCollection();

        self::assertNotNull($thumbCollection);

        $large = $thumbCollection->get('large');
        $medium = $thumbCollection->get('medium');

        self::assertSame(MimeType::WEBP, $large->mimeType());
        self::assertSame('webp', $large->extension());
        self::assertSame('source.webp', $large->basename());
        self::assertFileExists($large->filepath());

        self::assertSame(MimeType::WEBP, $medium->mimeType());
        self::assertSame('webp', $medium->extension());
        self::assertSame('source.webp', $medium->basename());
        self::assertFileExists($medium->filepath());
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
            'cropRatioWidth' => 600,
            'cropRatioHeight' => 600,
            'watermarks' => $this->watermarks([
                'large' => $watermark,
                'medium' => $watermark,
            ]),
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

        self::assertSame(MimeType::JPEG, $file->mimeType());
        self::assertSame('jpg', $file->extension());
        self::assertSame('source.jpg', $file->basename());

        self::assertSame(600, $file->imageWidth());
        self::assertSame(600, $file->imageHeight());

        $thumbCollection = $file->thumbCollection();

        self::assertNotNull($thumbCollection);

        $large = $thumbCollection->get('large');
        $medium = $thumbCollection->get('medium');

        self::assertSame(MimeType::WEBP, $large->mimeType());
        self::assertSame('webp', $large->extension());
        self::assertSame('source.webp', $large->basename());
        self::assertFileExists($large->filepath());
        self::assertSame(400, $large->imageWidth());
        self::assertSame(400, $large->imageHeight());

        self::assertSame(MimeType::WEBP, $medium->mimeType());
        self::assertSame('webp', $medium->extension());
        self::assertSame('source.webp', $medium->basename());
        self::assertFileExists($medium->filepath());
        self::assertSame(200, $medium->imageWidth());
        self::assertSame(200, $medium->imageHeight());
    }

    #[Test]
    public function itBuildsUploadedFileCollectionWithoutUpload(): void
    {
        $firstFilepath = $this->createJpeg('picture.jpg');
        $secondFilepath = $this->createJpeg('picture2.jpg');

        $config = FileManagerConfig::create([
            'field' => 'file',
        ]);

        $fileManager = new FileManager($config);

        $result = $fileManager->buildUploadedFileCollection([
            ['filepath' => $firstFilepath],
            ['filepath' => $secondFilepath],
        ])->files();

        self::assertCount(2, $result);

        $files = $result->all();

        self::assertSame($firstFilepath, $files[0]->filepath());
        self::assertSame($secondFilepath, $files[1]->filepath());
    }

    #[Test]
    public function itProcessesBuiltUploadedFileCollectionWithConvert(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $config = FileManagerConfig::create([
            'field' => 'file',
        ]);

        $fileManager = new FileManager($config);

        $result = $fileManager
            ->buildUploadedFileCollection([
                ['filepath' => $filepath],
            ])
            ->convert()
            ->files();

        $file = $result->first();

        self::assertNotNull($file);
        self::assertTrue($file->converted());
        self::assertSame('webp', $file->extension());
        self::assertSame(MimeType::WEBP, $file->mimeType());
        self::assertFileExists($file->filepath());
    }

    #[Test]
    public function itProcessesBuiltUploadedFileCollectionWithWatermark(): void
    {
        $filepath = $this->createJpeg('picture.jpg');
        $watermarkFilepath = $this->createPng('watermark.png');

        $config = FileManagerConfig::create([
            'field' => 'file',
            'watermarks' => [
                'original' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ],
        ]);

        $fileManager = new FileManager($config);

        $result = $fileManager
            ->buildUploadedFileCollection([
                ['filepath' => $filepath],
            ])
            ->watermark()
            ->files();

        self::assertCount(1, $result);
        self::assertSame($filepath, $result->first()?->filepath());
        self::assertFileExists($filepath);
    }

    #[Test]
    public function itProcessesBuiltUploadedFileCollectionWithResize(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $resizePath = $this->directory
            .DIRECTORY_SEPARATOR
            .'resized';

        mkdir($resizePath, 0777, true);

        $config = FileManagerConfig::create([
            'field' => 'file',
            'resizePath' => $resizePath,
            'thumbs' => [
                'large' => [
                    'width' => 400,
                    'height' => 300,
                ],
            ],
        ]);

        $fileManager = new FileManager($config);

        $result = $fileManager
            ->buildUploadedFileCollection([
                ['filepath' => $filepath],
            ])
            ->resize()
            ->files();

        $file = $result->first();

        self::assertNotNull($file);
        self::assertNotNull($file->thumbCollection());
        self::assertTrue($file->thumbCollection()?->has('large') ?? false);

        $thumb = $file->thumbCollection()?->get('large');

        self::assertNotNull($thumb);
        self::assertFileExists($thumb->filepath());
        self::assertSame(400, $thumb->imageWidth());
        self::assertSame(300, $thumb->imageHeight());
    }

    #[Test]
    public function itDoesNotRequireUploadWhenUploadedFileCollectionWasBuilt(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $config = FileManagerConfig::create([
            'field' => 'file',
        ]);

        $fileManager = new FileManager($config);

        $result = $fileManager
            ->buildUploadedFileCollection([
                ['filepath' => $filepath],
            ])
            ->files();

        self::assertCount(1, $result);
        self::assertSame($filepath, $result->first()?->filepath());
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

    private function watermarks(array $watermarks): array
    {
        return array_map(fn ($overlayFilepath) => [
            'overlayFilepath' => $overlayFilepath,
            'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
            'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
            'opacity' => 10,
            'xTransparency' => 5,
            'yTransparency' => 5,
        ], $watermarks);
    }

    private function createJpeg(string $filename, int $width = 800, int $height = 600): string
    {
        $filepath = $this->directory .DIRECTORY_SEPARATOR .$filename;

        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate($image, 255, 255, 255);

        imagefill($image, 0, 0, $background);
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

        $red = imagecolorallocatealpha(
            $image,
            255,
            0,
            0,
            0
        );

        imagefilledrectangle(
            $image,
            20,
            20,
            $width - 1,
            $height - 1,
            $red
        );

        imagepng($image, $filepath);

        imagedestroy($image);

        return $filepath;
    }

    private function getImageContent(string $filepath): string
    {
        $content = file_get_contents($filepath);

        self::assertIsString($content);

        return $content;
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
