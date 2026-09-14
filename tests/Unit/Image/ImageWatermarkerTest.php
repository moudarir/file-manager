<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Image;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\File;
use Moudarir\FileManager\Collections\ThumbCollection;
use Moudarir\FileManager\Image\ImageWatermarkConfig;
use Moudarir\FileManager\Image\ImageWatermarker;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageWatermarkerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'file-manager-watermarker-'
            . uniqid('', true);

        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function itAppliesWatermarkToOriginalImage(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);

        $before = $this->getImageContent($sourceFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'original' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $after = $this->getImageContent($sourceFilepath);

        self::assertNotSame($before, $after);
    }

    #[Test]
    public function itDoesNotApplyThumbnailWatermarkToOriginalImage(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);

        $before = $this->getImageContent($sourceFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'big' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $after = $this->getImageContent($sourceFilepath);

        self::assertSame($before, $after);
    }

    #[Test]
    public function itAppliesWatermarkToMatchingThumbnail(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $thumbFilepath = $this->createSourceImage('big.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);
        $thumb = $this->createUploadedFile($thumbFilepath);

        $file->setThumbCollection(new ThumbCollection(['big' => $thumb]));

        $before = $this->getImageContent($thumbFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'big' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $after = $this->getImageContent($thumbFilepath);

        self::assertNotSame($before, $after);
    }

    #[Test]
    public function itDoesNotApplyWatermarkToNonMatchingThumbnail(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $thumbFilepath = $this->createSourceImage('big.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);
        $thumb = $this->createUploadedFile($thumbFilepath);

        $file->setThumbCollection(new ThumbCollection(['big' => $thumb]));

        $before = $this->getImageContent($thumbFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'small' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $after = $this->getImageContent($thumbFilepath);

        self::assertSame($before, $after);
    }

    #[Test]
    public function itAppliesWatermarkToOriginalAndMatchingThumbnail(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $thumbFilepath = $this->createSourceImage('big.jpg');

        $originalWatermarkFilepath = $this->createWatermarkImage('original-watermark.png');

        $thumbnailWatermarkFilepath = $this->createWatermarkImage('thumbnail-watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);
        $thumb = $this->createUploadedFile($thumbFilepath);

        $file->setThumbCollection(new ThumbCollection(['big' => $thumb]));

        $originalBefore = $this->getImageContent($sourceFilepath);
        $thumbBefore = $this->getImageContent($thumbFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'original' => [
                    'overlayFilepath' => $originalWatermarkFilepath,
                ],
                'big' => [
                    'overlayFilepath' => $thumbnailWatermarkFilepath,
                ],
            ])
        );

        $originalAfter = $this->getImageContent($sourceFilepath);
        $thumbAfter = $this->getImageContent($thumbFilepath);

        self::assertNotSame($originalBefore, $originalAfter);
        self::assertNotSame($thumbBefore, $thumbAfter);
    }

    #[Test]
    public function itDoesNotApplyOriginalWatermarkToThumbnail(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $thumbFilepath = $this->createSourceImage('big.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);
        $thumb = $this->createUploadedFile($thumbFilepath);

        $file->setThumbCollection(new ThumbCollection(['big' => $thumb]));

        $sourceBefore = $this->getImageContent($sourceFilepath);
        $thumbBefore = $this->getImageContent($thumbFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'original' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $sourceAfter = $this->getImageContent($sourceFilepath);
        $thumbAfter = $this->getImageContent($thumbFilepath);

        self::assertNotSame($sourceBefore, $sourceAfter);
        self::assertSame($thumbBefore, $thumbAfter);
    }

    #[Test]
    public function itDoesNotApplyThumbnailWatermarkWhenThumbCollectionIsAbsent(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);

        self::assertNull($file->thumbCollection());

        $before = $this->getImageContent($sourceFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'big' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $after = $this->getImageContent($sourceFilepath);

        self::assertSame($before, $after);
    }

    #[Test]
    public function itAppliesWatermarkOnlyToConfiguredThumbnails(): void
    {
        $sourceFilepath = $this->createSourceImage('original.jpg');
        $bigFilepath = $this->createSourceImage('big.jpg');
        $mediumFilepath = $this->createSourceImage('medium.jpg');
        $smallFilepath = $this->createSourceImage('small.jpg');

        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $file = $this->createUploadedFile($sourceFilepath);

        $file->setThumbCollection(
            new ThumbCollection([
                'big' => $this->createUploadedFile($bigFilepath),
                'medium' => $this->createUploadedFile($mediumFilepath),
                'small' => $this->createUploadedFile($smallFilepath),
            ])
        );

        $bigBefore = $this->getImageContent($bigFilepath);
        $mediumBefore = $this->getImageContent($mediumFilepath);
        $smallBefore = $this->getImageContent($smallFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'big' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
                'small' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $bigAfter = $this->getImageContent($bigFilepath);
        $mediumAfter = $this->getImageContent($mediumFilepath);
        $smallAfter = $this->getImageContent($smallFilepath);

        self::assertNotSame($bigBefore, $bigAfter);
        self::assertSame($mediumBefore, $mediumAfter);
        self::assertNotSame($smallBefore, $smallAfter);
    }

    #[Test]
    public function itProcessesMultipleUploadedImages(): void
    {
        $firstFilepath = $this->createSourceImage('first.jpg');
        $secondFilepath = $this->createSourceImage('second.jpg');
        $watermarkFilepath = $this->createWatermarkImage('watermark.png');

        $firstFile = $this->createUploadedFile($firstFilepath);
        $secondFile = $this->createUploadedFile($secondFilepath);

        $firstBefore = $this->getImageContent($firstFilepath);
        $secondBefore = $this->getImageContent($secondFilepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$firstFile, $secondFile]),
            $this->createConfig([
                'original' => [
                    'overlayFilepath' => $watermarkFilepath,
                ],
            ])
        );

        $firstAfter = $this->getImageContent($firstFilepath);
        $secondAfter = $this->getImageContent($secondFilepath);

        self::assertNotSame($firstBefore, $firstAfter);
        self::assertNotSame($secondBefore, $secondAfter);
    }

    #[Test]
    public function itIgnoresNonImageFiles(): void
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . 'document.txt';

        file_put_contents($filepath, 'test file');

        $file = $this->createUploadedFile($filepath, MimeType::TEXT_PLAIN);

        $before = file_get_contents($filepath);

        ImageWatermarker::create(
            new UploadedFileCollection('file', [$file]),
            $this->createConfig([
                'original' => [
                    'overlayFilepath' => $this->createWatermarkImage('watermark.png'),
                ],
            ])
        );

        $after = file_get_contents($filepath);

        self::assertSame($before, $after);
    }

    private function createConfig(array $watermarks): ImageWatermarkConfig
    {
        return ImageWatermarkConfig::create([
            'watermarks' => $watermarks,
        ]);
    }

    private function createUploadedFile(
        string $filepath,
        MimeType $mimeType = MimeType::JPEG,
    ): UploadedFile {
        $file = File::create($filepath, $mimeType);

        return UploadedFile::create(
            $file->resource(),
            $mimeType,
            basename($filepath),
            new DateTimeImmutable(),
        );
    }

    private function createSourceImage(string $filename, int $width = 200, int $height = 200): string
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate($image, 255, 255, 255);

        imagefill($image, 0, 0, $background);

        imagejpeg($image, $filepath, 100);

        imagedestroy($image);

        return $filepath;
    }

    private function createWatermarkImage(string $filename, int $width = 100, int $height = 100): string
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        $image = imagecreatetruecolor($width, $height);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha(
            $image,
            255,
            255,
            255,
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

    private function getPixel(string $filepath, int $x, int $y): int
    {
        $image = imagecreatefromjpeg($filepath);

        try {
            return imagecolorat($image, $x, $y);
        } finally {
            imagedestroy($image);
        }
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

            $filepath = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($filepath) === true) {
                $this->removeDirectory($filepath);
                continue;
            }

            unlink($filepath);
        }

        rmdir($directory);
    }
}
