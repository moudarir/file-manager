<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Upload;

use DateTimeImmutable;
use Moudarir\File\Enum\MimeType;
use Moudarir\File\File;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\TestCase;

final class UploadedFileCollectionTest extends TestCase
{

    public function testCollectionExposesFieldAndFiles(): void
    {
        $fileResource1 = File::create(__FILE__)->resource();
        $file1 = UploadedFile::create(
            $fileResource1,
            MimeType::PHP,
            'file1.php',
            new DateTimeImmutable(),
            $fileResource1->filesize(),
        );

        $fileResource2 = File::create(__FILE__)->resource();
        $file2 = UploadedFile::create(
            $fileResource2,
            MimeType::PHP,
            'file2.php',
            new DateTimeImmutable(),
            $fileResource2->filesize(),
        );

        $collection = new UploadedFileCollection('documents', [$file1, $file2]);

        self::assertSame('documents', $collection->field());
        self::assertSame(2, $collection->count());
        self::assertSame([$file1, $file2], $collection->all());
        self::assertSame($file1, $collection->first());
    }

    public function testEmptyCollectionIsEmpty(): void
    {
        $collection = new UploadedFileCollection('documents', []);

        self::assertSame(0, $collection->count());
        self::assertSame([], $collection->all());
        self::assertNull($collection->first());
        self::assertTrue($collection->isEmpty());
    }

    public function testCollectionCanBeIterated(): void
    {
        $fileResource1 = File::create(__FILE__)->resource();
        $file1 = UploadedFile::create(
            $fileResource1,
            MimeType::PHP,
            'file1.php',
            new DateTimeImmutable(),
            $fileResource1->filesize(),
        );

        $fileResource2 = File::create(__FILE__)->resource();
        $file2 = UploadedFile::create(
            $fileResource2,
            MimeType::PHP,
            'file2.php',
            new DateTimeImmutable(),
            $fileResource2->filesize(),
        );

        $collection = new UploadedFileCollection('documents', [$file1, $file2]);

        $files = [];

        foreach ($collection as $file) {
            $files[] = $file;
        }

        self::assertSame([$file1, $file2], $files);
    }
}
