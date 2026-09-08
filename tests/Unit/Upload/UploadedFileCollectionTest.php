<?php

declare(strict_types=1);

namespace Moudarir\FileManager\Tests\Unit\Upload;

use Moudarir\File\Enum\MimeType;
use Moudarir\File\File;
use Moudarir\FileManager\Upload\UploadedFile;
use Moudarir\FileManager\Upload\UploadedFileCollection;
use PHPUnit\Framework\TestCase;

final class UploadedFileCollectionTest extends TestCase
{

    public function testCollectionExposesFieldAndFiles(): void
    {
        $file1 = UploadedFile::create(
            File::create(__FILE__)->resource(),
            MimeType::PHP,
            'file1.php',
            '2026-09-08 00:00:00',
        );

        $file2 = UploadedFile::create(
            File::create(__FILE__)->resource(),
            MimeType::PHP,
            'file2.php',
            '2026-09-08 00:00:00',
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
        $file1 = UploadedFile::create(
            File::create(__FILE__)->resource(),
            MimeType::PHP,
            'file1.php',
            '2026-09-08 00:00:00',
        );

        $file2 = UploadedFile::create(
            File::create(__FILE__)->resource(),
            MimeType::PHP,
            'file2.php',
            '2026-09-08 00:00:00',
        );

        $collection = new UploadedFileCollection('documents', [$file1, $file2]);

        $files = [];

        foreach ($collection as $file) {
            $files[] = $file;
        }

        self::assertSame([$file1, $file2], $files);
    }
}
