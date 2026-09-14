<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Collections;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Collections\CollectionBuilder;
use Moudarir\FileManager\Config\CollectionBuilderConfig;
use Moudarir\FileManager\Exceptions\FileManagerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'moudarir-file-manager-collection-builder-'.uniqid();

        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function itBuildsAnUploadedFileCollectionFromFiles(): void
    {
        $firstFilepath = $this->createJpeg('picture.jpg');
        $secondFilepath = $this->createJpeg('picture2.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                ['filepath' => $firstFilepath],
                ['filepath' => $secondFilepath],
            ],
            $config
        );

        self::assertSame('file', $collection->field());
        self::assertCount(2, $collection);

        $files = $collection->all();

        self::assertSame($firstFilepath, $files[0]->filepath());
        self::assertSame($secondFilepath, $files[1]->filepath());

        self::assertSame('picture.jpg', $files[0]->basename());
        self::assertSame('picture2.jpg', $files[1]->basename());

        self::assertSame('picture', $files[0]->filename());
        self::assertSame('picture2', $files[1]->filename());

        self::assertSame('jpg', $files[0]->extension());
        self::assertSame('jpg', $files[1]->extension());

        self::assertSame(MimeType::JPEG, $files[0]->mimeType());
        self::assertSame(MimeType::JPEG, $files[1]->mimeType());

        self::assertSame(800, $files[0]->imageWidth());
        self::assertSame(600, $files[0]->imageHeight());

        self::assertSame(800, $files[1]->imageWidth());
        self::assertSame(600, $files[1]->imageHeight());
    }

    #[Test]
    public function itUsesCustomDateFromConfiguration(): void
    {
        $filepath = $this->createJpeg('picture.jpg');
        $customDate = new DateTimeImmutable('2026-01-15 10:30:00');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
            'customDate' => $customDate,
        ]);

        $collection = CollectionBuilder::create(
            [['filepath' => $filepath]],
            $config
        );

        self::assertSame(
            $customDate,
            $collection->first()?->createdAt()
        );
    }

    #[Test]
    public function itUsesCreatedAtProvidedForTheFile(): void
    {
        $filepath = $this->createJpeg('picture.jpg');
        $createdAt = new DateTimeImmutable('2025-12-20 08:15:00');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                [
                    'filepath' => $filepath,
                    'createdAt' => $createdAt,
                ],
            ],
            $config
        );

        self::assertSame(
            $createdAt,
            $collection->first()?->createdAt()
        );
    }

    #[Test]
    public function itUsesDefaultDateForEachFileWhenCreatedAtIsNotProvided(): void
    {
        $firstFilepath = $this->createJpeg('picture.jpg');
        $secondFilepath = $this->createJpeg('picture2.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
            'customDate' => new DateTimeImmutable('2026-01-01 10:00:00'),
        ]);

        $collection = CollectionBuilder::create(
            [
                [
                    'filepath' => $firstFilepath,
                    'createdAt' => new DateTimeImmutable('2025-01-01 10:00:00'),
                ],
                [
                    'filepath' => $secondFilepath,
                ],
            ],
            $config
        );

        $files = $collection->all();

        self::assertEquals(
            new DateTimeImmutable('2025-01-01 10:00:00'),
            $files[0]->createdAt()
        );

        self::assertEquals(
            new DateTimeImmutable('2026-01-01 10:00:00'),
            $files[1]->createdAt()
        );
    }

    #[Test]
    public function itUsesProvidedDimensions(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                [
                    'filepath' => $filepath,
                    'dimensions' => [
                        'width' => 400,
                        'height' => 300,
                    ],
                ],
            ],
            $config
        );

        $file = $collection->first();

        self::assertNotNull($file);
        self::assertSame(400, $file->imageWidth());
        self::assertSame(300, $file->imageHeight());
        self::assertSame(
            'width="400" height="300"',
            $file->imageDimensions()['htmlAttributes']
        );
    }

    #[Test]
    public function itDetectsImageDimensionsWhenTheyAreNotProvided(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [['filepath' => $filepath]],
            $config
        );

        $file = $collection->first();

        self::assertNotNull($file);
        self::assertSame(800, $file->imageWidth());
        self::assertSame(600, $file->imageHeight());
        self::assertSame(
            'width="800" height="600"',
            $file->imageDimensions()['htmlAttributes']
        );
    }

    #[Test]
    public function itUsesProvidedMimeTypeWhenItIsValid(): void
    {
        $filepath = $this->createJpeg('picture.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                [
                    'filepath' => $filepath,
                    'mimeType' => MimeType::JPEG->value,
                ],
            ],
            $config
        );

        self::assertSame(
            MimeType::JPEG,
            $collection->first()?->mimeType()
        );
    }

    #[Test]
    public function itReturnsAnEmptyCollectionWhenFilesIsEmpty(): void
    {
        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create([], $config);

        self::assertTrue($collection->isEmpty());
        self::assertSame([], $collection->all());
    }

    #[Test]
    public function itThrowsWhenFilepathKeyIsMissing(): void
    {
        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        self::expectException(FileManagerException::class);
        self::expectExceptionMessageIsOrContains('filepath');

        CollectionBuilder::create(
            [
                [],
            ],
            $config
        );
    }

    #[Test]
    public function itThrowsWhenFilepathIsEmpty(): void
    {
        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        self::expectException(FileManagerException::class);
        self::expectExceptionMessageIsOrContains('filepath');

        CollectionBuilder::create(
            [
                ['filepath' => ''],
            ],
            $config
        );
    }

    #[Test]
    public function itThrowsWhenFileDoesNotExist(): void
    {
        $filepath = $this->directory
            .DIRECTORY_SEPARATOR
            .'missing.jpg';

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        self::expectException(FileManagerException::class);

        CollectionBuilder::create(
            [['filepath' => $filepath]],
            $config
        );
    }

    #[Test]
    public function itSilentlyIgnoresInvalidFilepath(): void
    {
        $validFilepath = $this->createJpeg('picture.jpg');

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                [],
                ['filepath' => $validFilepath],
            ],
            $config,
            true
        );

        self::assertCount(1, $collection);
        self::assertSame(
            $validFilepath,
            $collection->first()?->filepath()
        );
    }

    #[Test]
    public function itSilentlyIgnoresMissingFile(): void
    {
        $validFilepath = $this->createJpeg('picture.jpg');
        $missingFilepath = $this->directory
            .DIRECTORY_SEPARATOR
            .'missing.jpg';

        $config = CollectionBuilderConfig::create([
            'field' => 'file',
        ]);

        $collection = CollectionBuilder::create(
            [
                ['filepath' => $missingFilepath],
                ['filepath' => $validFilepath],
            ],
            $config,
            true
        );

        self::assertCount(1, $collection);
        self::assertSame($validFilepath, $collection->first()?->filepath());
    }

    private function createJpeg(string $filename): string
    {
        $filepath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        $image = imagecreatetruecolor(800, 600);

        imagejpeg($image, $filepath, 90);

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
