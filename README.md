# Moudarir File Manager

A lightweight, dependency-free PHP file manager with image processing capabilities.

## Requirements

* PHP 8.4+
* GD extension
* ImageMagick command-line tools

The package automatically detects the available ImageMagick executable, using `convert` when available and falling back to `magick`.

## Installation

```bash
composer require moudarir/file-manager
```

## Usage

Create a `FileManagerConfig` and use the fluent `FileManager` API:

```php
use Moudarir\FileManager\FileManager;
use Moudarir\FileManager\FileManagerConfig;

$config = FileManagerConfig::create([
    'field' => 'file',
    'uploadPath' => '/path/to/uploads',
    'resizePath' => '/path/to/resized',
    'thumbs' => [
        'large' => [
            'width' => 1200,
            'height' => 1200,
        ],
        'medium' => [
            'width' => 600,
            'height' => 600,
        ],
    ],
]);

$files = (new FileManager($config))
    ->upload()
    ->resize()
    ->convert()
    ->files();
```

For a specific file, pass its filepath to `upload()`:

```php
$files = (new FileManager($config))
    ->upload('/path/to/file.jpg')
    ->files();
```

Image operations can be combined in a processing pipeline:

```php
$croppingConfig = '{"rotate":0,"width":400,"height":400,"x":0,"y":0}';
$files = (new FileManager($config))
    ->upload()
    ->crop($croppingConfig)
    ->resize()
    ->watermark()
    ->convert()
    ->files();
```

The processing order is:

```text
upload → crop → resize → watermark → convert
```

## Features

### File upload

* HTTP upload handling
* Direct file copying
* MIME type validation
* File size validation
* Image dimension validation
* Filename sanitization or encryption
* Filename collision handling
* Overwrite support
* Multiple-file uploads
* Date-based upload directories

### Image cropping

* Crop
* Rotation during cropping
* Resize with configurable thumbnails
* Watermarking
* JPEG, PNG, GIF and WebP image processing
* WebP conversion
* Optional removal of source files after processing
* ImageMagick executable detection with `convert` / `magick` fallback

### Image resizing

* Create configurable thumbnails with individual dimensions.
* Configure image quality for generated thumbnails.
* Organize resized images into date-based directories.
* Optionally remove the source image after resizing.
* Automatically detect the available `ImageMagick` executable.

### Image watermarking

* Apply configurable watermark images to thumbnails.
* Target specific thumbnails for watermarking.
* Configure vertical and horizontal watermark alignment.
* Configure watermark opacity.
* Configure horizontal and vertical transparency offsets.
* Apply watermarks after image resizing.

### Image conversion

* Convert images to `WebP` using `ImageMagick`.
* Convert generated thumbnails to `WebP`.
* Skip processing for images that are already in `WebP` format.
* Update uploaded-file metadata after successful conversion.
* Optionally remove source files after conversion.
* Roll back generated files when conversion fails.

## Testing

Run the test suite with:

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
