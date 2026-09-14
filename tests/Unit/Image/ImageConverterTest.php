<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Image;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\FileResource;
use Moudarir\FileManager\Collections\ThumbCollection;
use Moudarir\FileManager\Collections\UploadedFileCollection;
use Moudarir\FileManager\Config\ImageConvertConfig;
use Moudarir\FileManager\Exceptions\FileManagerException;
use Moudarir\FileManager\Image\ImageConverter;
use Moudarir\FileManager\Upload\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageConverterTest extends TestCase
{

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'moudarir-file-manager-converter-'.uniqid();

        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function itConvertsImage(): void
    {
        $source = $this->createJpeg('picture.jpg');
        $uploadedFile = $this->createUploadedFile($source);

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => false])
        );

        $destination = $this->directory.DIRECTORY_SEPARATOR.'picture.webp';

        self::assertFileExists($source);
        self::assertFileExists($destination);

        self::assertTrue($uploadedFile->converted());
        self::assertSame($destination, $uploadedFile->filepath());
        self::assertSame('picture.webp', $uploadedFile->basename());
        self::assertSame('picture', $uploadedFile->filename());
        self::assertSame('webp', $uploadedFile->extension());
        self::assertSame(MimeType::WEBP, $uploadedFile->mimeType());
        self::assertSame(MimeType::WEBP->value, $uploadedFile->mimeTypeValue());
        self::assertSame(filesize($destination), $uploadedFile->filesize());
    }

    #[Test]
    public function itConvertsConfiguredThumbs(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-01 12:00:00');

        $largeDirectory = $this->directory.DIRECTORY_SEPARATOR.'large';
        $mediumDirectory = $this->directory.DIRECTORY_SEPARATOR.'medium';
        $smallDirectory = $this->directory.DIRECTORY_SEPARATOR.'small';

        mkdir($largeDirectory, 0777, true);
        mkdir($mediumDirectory, 0777, true);
        mkdir($smallDirectory, 0777, true);

        $originalSource = $this->createJpeg('picture.jpg');

        $largeSource = $this->createJpeg('picture.jpg', $largeDirectory);
        $mediumSource = $this->createJpeg('picture.jpg', $mediumDirectory);
        $smallSource = $this->createJpeg('picture.jpg', $smallDirectory);

        $large = $this->createUploadedFile($largeSource, $createdAt);
        $medium = $this->createUploadedFile($mediumSource, $createdAt);
        $small = $this->createUploadedFile($smallSource, $createdAt);

        $uploadedFile = $this->createUploadedFile($originalSource, $createdAt);

        $uploadedFile->setThumbCollection(
            new ThumbCollection([
                'large' => $large,
                'medium' => $medium,
                'small' => $small,
            ])
        );

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => false])
        );

        $largeDestination = $largeDirectory.DIRECTORY_SEPARATOR.'picture.webp';
        $mediumDestination = $mediumDirectory.DIRECTORY_SEPARATOR.'picture.webp';
        $smallDestination = $smallDirectory.DIRECTORY_SEPARATOR.'picture.webp';

        self::assertFileExists($originalSource);

        self::assertFileExists($largeDestination);
        self::assertFileExists($mediumDestination);
        self::assertFileExists($smallDestination);

        self::assertFileExists($largeSource);
        self::assertFileExists($mediumSource);
        self::assertFileExists($smallSource);

        self::assertFalse($uploadedFile->converted());
        self::assertSame($originalSource, $uploadedFile->filepath());
        self::assertSame('picture.jpg', $uploadedFile->basename());
        self::assertSame('jpg', $uploadedFile->extension());
        self::assertSame(MimeType::JPEG, $uploadedFile->mimeType());

        self::assertTrue($large->converted());
        self::assertSame($largeDestination, $large->filepath());
        self::assertSame('picture.webp', $large->basename());
        self::assertSame('picture', $large->filename());
        self::assertSame('webp', $large->extension());
        self::assertSame(MimeType::WEBP, $large->mimeType());
        self::assertSame(filesize($largeDestination), $large->filesize());

        self::assertTrue($medium->converted());
        self::assertSame($mediumDestination, $medium->filepath());
        self::assertSame('picture.webp', $medium->basename());
        self::assertSame('picture', $medium->filename());
        self::assertSame('webp', $medium->extension());
        self::assertSame(MimeType::WEBP, $medium->mimeType());
        self::assertSame(filesize($mediumDestination), $medium->filesize());

        self::assertTrue($small->converted());
        self::assertSame($smallDestination, $small->filepath());
        self::assertSame('picture.webp', $small->basename());
        self::assertSame('picture', $small->filename());
        self::assertSame('webp', $small->extension());
        self::assertSame(MimeType::WEBP, $small->mimeType());
        self::assertSame(filesize($smallDestination), $small->filesize());
    }

    #[Test]
    public function itMarksAsConvertedWhenImageIsAlreadyWebp(): void
    {
        $source = $this->createWebp('picture.webp');
        $uploadedFile = $this->createUploadedFile($source, mimeType: MimeType::WEBP);

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => false])
        );

        self::assertFileExists($source);
        self::assertTrue($uploadedFile->converted());
        self::assertSame($source, $uploadedFile->filepath());
        self::assertSame('picture.webp', $uploadedFile->basename());
        self::assertSame('picture', $uploadedFile->filename());
        self::assertSame('webp', $uploadedFile->extension());
        self::assertSame(MimeType::WEBP, $uploadedFile->mimeType());
    }

    #[Test]
    public function itDoesNothingForNonImageFile(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'document.txt';

        file_put_contents($source, 'Lorem ipsum');

        $uploadedFile = $this->createUploadedFile($source, mimeType: MimeType::TEXT_PLAIN);

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => false])
        );

        self::assertFileExists($source);
        self::assertFalse($uploadedFile->converted());
        self::assertSame($source, $uploadedFile->filepath());
        self::assertSame(MimeType::TEXT_PLAIN, $uploadedFile->mimeType());
    }

    #[Test]
    public function itRemovesSourceAfterSuccessfulConversion(): void
    {
        $source = $this->createJpeg('picture.jpg');
        $uploadedFile = $this->createUploadedFile($source);

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => true])
        );

        $destination = $this->directory.DIRECTORY_SEPARATOR.'picture.webp';

        self::assertFileDoesNotExist($source);
        self::assertFileExists($destination);

        self::assertTrue($uploadedFile->converted());
        self::assertSame($destination, $uploadedFile->filepath());
        self::assertSame('picture.webp', $uploadedFile->basename());
        self::assertSame(MimeType::WEBP, $uploadedFile->mimeType());
    }

    #[Test]
    public function itRemovesAllSourcesAfterSuccessfulThumbConversions(): void
    {
        $originalSource = $this->createJpeg('picture.jpg');

        $largeDirectory = $this->directory.DIRECTORY_SEPARATOR.'large';
        $mediumDirectory = $this->directory.DIRECTORY_SEPARATOR.'medium';
        $smallDirectory = $this->directory.DIRECTORY_SEPARATOR.'small';

        mkdir($largeDirectory, 0777, true);
        mkdir($mediumDirectory, 0777, true);
        mkdir($smallDirectory, 0777, true);

        $largeSource = $this->createJpeg('picture.jpg', $largeDirectory);
        $mediumSource = $this->createJpeg('picture.jpg', $mediumDirectory);
        $smallSource = $this->createJpeg('picture.jpg', $smallDirectory);

        $uploadedFile = $this->createUploadedFile($originalSource);

        $large = $this->createUploadedFile($largeSource);
        $medium = $this->createUploadedFile($mediumSource);
        $small = $this->createUploadedFile($smallSource);

        $uploadedFile->setThumbCollection(
            new ThumbCollection([
                'large' => $large,
                'medium' => $medium,
                'small' => $small,
            ])
        );

        ImageConverter::create(
            new UploadedFileCollection('file', [$uploadedFile]),
            ImageConvertConfig::create(['removeAfterConvert' => true])
        );

        self::assertFileExists($originalSource);

        self::assertFileDoesNotExist($largeSource);
        self::assertFileDoesNotExist($mediumSource);
        self::assertFileDoesNotExist($smallSource);

        self::assertFileExists($largeDirectory.DIRECTORY_SEPARATOR.'picture.webp');
        self::assertFileExists($mediumDirectory.DIRECTORY_SEPARATOR.'picture.webp');
        self::assertFileExists($smallDirectory.DIRECTORY_SEPARATOR.'picture.webp');

        self::assertFalse($uploadedFile->converted());

        self::assertTrue($large->converted());
        self::assertTrue($medium->converted());
        self::assertTrue($small->converted());
    }

    #[Test]
    public function itRollsBackConvertedFilesWhenAThumbConversionFails(): void
    {
        $largeDirectory = $this->directory.DIRECTORY_SEPARATOR.'large';
        $mediumDirectory = $this->directory.DIRECTORY_SEPARATOR.'medium';

        mkdir($largeDirectory, 0777, true);
        mkdir($mediumDirectory, 0777, true);

        $originalSource = $this->createJpeg('picture.jpg');
        $largeSource = $this->createJpeg('picture.jpg', $largeDirectory);
        $mediumSource = $this->createJpeg('picture.jpg', $mediumDirectory);

        $uploadedFile = $this->createUploadedFile($originalSource);
        $large = $this->createUploadedFile($largeSource);
        $medium = $this->createUploadedFile($mediumSource);

        $uploadedFile->setThumbCollection(
            new ThumbCollection([
                'large' => $large,
                'medium' => $medium,
            ])
        );

        unlink($mediumSource);

        $collection = new UploadedFileCollection('file', [$uploadedFile]);

        $config = ImageConvertConfig::create(['removeAfterConvert' => false]);

        self::expectException(FileManagerException::class);

        try {
            ImageConverter::create($collection, $config);
        } finally {
            self::assertFileDoesNotExist($largeDirectory.DIRECTORY_SEPARATOR.'picture.webp');

            self::assertFileExists($originalSource);
            self::assertFileExists($largeSource);
            self::assertFileDoesNotExist($mediumSource);

            self::assertFalse($uploadedFile->converted());
            self::assertSame($originalSource, $uploadedFile->filepath());
            self::assertSame('picture.jpg', $uploadedFile->basename());
            self::assertSame('jpg', $uploadedFile->extension());
            self::assertSame(MimeType::JPEG, $uploadedFile->mimeType());

            self::assertFalse($large->converted());
            self::assertSame($largeSource, $large->filepath());
            self::assertSame('picture.jpg', $large->basename());
            self::assertSame('jpg', $large->extension());
            self::assertSame(MimeType::JPEG, $large->mimeType());
        }
    }

    #[Test]
    public function itThrowsWhenImageConversionFails(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'picture.jpg';

        file_put_contents($source, 'invalid image');

        $uploadedFile = $this->createUploadedFile($source, mimeType: MimeType::JPEG);

        $collection = new UploadedFileCollection('file', [$uploadedFile]);

        $config = ImageConvertConfig::create(['removeAfterConvert' => false,]);

        self::expectException(FileManagerException::class);

        ImageConverter::create($collection, $config);
    }

    private function createUploadedFile(
        string $filepath,
        ?DateTimeImmutable $createdAt = null,
        ?MimeType $mimeType = null,
    ): UploadedFile {
        $mimeType ??= MimeType::JPEG;
        $createdAt ??= new DateTimeImmutable('2026-09-01 12:00:00');

        return UploadedFile::create(
            FileResource::create($filepath),
            $mimeType,
            basename($filepath),
            $createdAt,
        );
    }

    private function createJpeg(string $filename, ?string $directory = null): string
    {
        $directory ??= $this->directory;

        $filepath = $directory.DIRECTORY_SEPARATOR.$filename;

        $image = imagecreatetruecolor(800, 600);

        imagejpeg($image, $filepath, 90);

        imagedestroy($image);

        return $filepath;
    }

    private function createWebp(string $filename, ?string $directory = null): string
    {
        $directory ??= $this->directory;

        $filepath = $directory.DIRECTORY_SEPARATOR.$filename;

        $image = imagecreatetruecolor(800, 600);

        imagewebp($image, $filepath, 90);

        imagedestroy($image);

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
