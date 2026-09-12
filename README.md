# Moudarir File Manager

A lightweight, dependency-free PHP file manager with image processing capabilities.

## Requirements

* PHP 8.4 or higher
* `GD` extension for image cropping, resizing and watermarking
* `ImageMagick` for image conversion and resizing

The package automatically detects the available `ImageMagick` executable, using `convert` when available and falling back to `magick`.

## Installation

```bash
composer require moudarir/file-manager
```

## Configuration

The `FileManager` accepts a configuration array.

Each module only uses the configuration parameters it needs. This allows the modules to be used independently.

### Upload

| Parameter          | Required? | Default | Description                                                                                                                 |
|:-------------------|:----------|:--------|:----------------------------------------------------------------------------------------------------------------------------|
| `field`            | Yes       | `''`    | Name of the uploaded file field.                                                                                            |
| `uploadPath`       | Yes       | `''`    | Base directory where uploaded files are stored.                                                                             |
| `dateFormat`       | No        | `null`  | Date format used to create a date-based subdirectory.                                                                       |
| `customDate`       | No        | `null`  | Date used instead of the current date when building date-based paths. Must be an instance of `DateTimeInterface` or `null`. |
| `maxFilesize`      | No        | `0`     | Maximum allowed file size. `0` disables the limit.                                                                          |
| `maxImageWidth`    | No        | `0`     | Maximum allowed image width. `0` disables the limit.                                                                        |
| `maxImageHeight`   | No        | `0`     | Maximum allowed image height. `0` disables the limit.                                                                       |
| `minImageWidth`    | No        | `0`     | Minimum allowed image width. `0` disables the limit.                                                                        |
| `minImageHeight`   | No        | `0`     | Minimum allowed image height. `0` disables the limit.                                                                       |
| `maxUploadedFiles` | No        | `null`  | Maximum number of files that can be uploaded in a single operation.                                                         |
| `allowedMimeTypes` | No        | `[]`    | List of allowed MIME types. An empty array does not restrict files by MIME type.                                            |
| `overwrite`        | No        | `false` | Allows an existing destination file to be overwritten.                                                                      |
| `encryptName`      | No        | `true`  | Generates a random filename instead of using the original filename.                                                         |

Example:

```php
$config = [
    'field' => 'file',
    'uploadPath' => __DIR__ . '/uploads/',
    'dateFormat' => 'Y/m',
    'maxFilesize' => 5 * 1024 * 1024,
    'allowedMimeTypes' => [
        MimeType::JPEG,
        MimeType::PNG,
        MimeType::WEBP,
    ],
];
```

### Crop

| Parameter         | Required? | Default | Description                           |
|:------------------|:----------|:--------|:--------------------------------------|
| `cropRatioWidth`  | No        | `400`   | Width used to define the crop ratio.  |
| `cropRatioHeight` | No        | `400`   | Height used to define the crop ratio. |

The crop ratio is defined by `cropRatioWidth / cropRatioHeight`.

### Resize

| Parameter           | Required? | Default | Description                                                                                                              |
|:--------------------|:----------|:--------|:-------------------------------------------------------------------------------------------------------------------------|
| `resizePath`        | Yes       | `''`    | Base directory where generated thumbnails are stored.                                                                    |
| `thumbs`            | Yes       | `[]`    | Defines the thumbnails to generate. Each key is freely configurable and each thumbnail must define `width` and `height`. |
| `resizeQuality`     | No        | `85`    | Image quality used during resizing.                                                                                      |
| `removeAfterResize` | No        | `false` | Removes the source file after all requested thumbnails have been successfully generated.                                 |

The `thumbs` configuration uses the thumbnail name as its key.

> The thumbnail name is completely configurable. No predefined name such as `large`, `medium` or `small` is required.

Each thumbnail configuration must contain:

| Key      | Required? | Description              |
|:---------|:----------|:-------------------------|
| `width`  | Yes       | Target thumbnail width.  |
| `height` | Yes       | Target thumbnail height. |

For example:

```php
'thumbs' => [
    'big' => [
        'width' => 1200,
        'height' => 630,
    ],
    'small' => [
        'width' => 84,
        'height' => 44,
    ],
]
```

The thumbnail names can also describe their dimensions directly:

```php
'thumbs' => [
    '1200x630' => [
        'width' => 1200,
        'height' => 630,
    ],
    '400x210' => [
        'width' => 400,
        'height' => 210,
    ],
]
```

### Watermark

| Parameter    | Required? | Default | Description                                                                                                                            |
|:-------------|:----------|:--------|:---------------------------------------------------------------------------------------------------------------------------------------|
| `watermarks` | Yes       | `[]`    | Defines the watermark configuration for the thumbnails to which a watermark must be applied. Each key corresponds to a thumbnail name. |

Each watermark configuration can contain the following keys:

| Key                   | Required? | Default                        | Description                                                    |
|:----------------------|:----------|:-------------------------------|:---------------------------------------------------------------|
| `overlayFilepath`     | Yes       | —                              | Path to the watermark overlay image.                           |
| `horizontalAlignment` | No        | `WatermarkAlignment::H_CENTER` | Horizontal position of the watermark.                          |
| `verticalAlignment`   | No        | `WatermarkAlignment::V_MIDDLE` | Vertical position of the watermark.                            |
| `opacity`             | No        | `19`                           | Watermark opacity. Must be between `1` and `100`.              |
| `xTransparency`       | No        | `4`                            | Horizontal transparency offset. Must be between `0` and `127`. |
| `yTransparency`       | No        | `4`                            | Vertical transparency offset. Must be between `0` and `127`.   |

The keys of each watermark configuration are all optional except for `overlayFilepath`. Their values may use the defaults shown above.

For example:

```php
'watermarks' => [
    'big' => [
        'overlayFilepath' => '/path/to/big-overlay.png',
        'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
        'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
        'opacity' => 15,
        'xTransparency' => 10,
        'yTransparency' => 10,
    ],
    'medium' => [
        'overlayFilepath' => '/path/to/medium-overlay.png',
        'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
        'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
        'opacity' => 15,
        'xTransparency' => 10,
        'yTransparency' => 10,
    ],
]
```

`horizontalAlignment` accepts the horizontal values provided by `WatermarkAlignment`:

* `WatermarkAlignment::H_LEFT`
* `WatermarkAlignment::H_CENTER`
* `WatermarkAlignment::H_RIGHT`

`verticalAlignment` accepts the vertical values provided by `WatermarkAlignment`:

* `WatermarkAlignment::V_TOP`
* `WatermarkAlignment::V_MIDDLE`
* `WatermarkAlignment::V_BOTTOM`

### Conversion

| Parameter            | Required? | Default | Description                                                                  |
|:---------------------|:----------|:--------|:-----------------------------------------------------------------------------|
| `removeAfterConvert` | No        | `false` | Removes source files after their WebP conversion has completed successfully. |

Conversion produces WebP files from the source image or from generated thumbnails.

When thumbnails are available, conversion can operate on those thumbnails. Otherwise, conversion can operate directly on the uploaded file.

### Configuration example

```php
use Moudarir\File\Enum\MimeType;
use Moudarir\FileManager\Enums\WatermarkAlignment;
use Moudarir\FileManager\FileManager;
use Moudarir\FileManager\FileManagerConfig;

$config = FileManagerConfig::create([
    // Upload
    'field' => 'file',
    'uploadPath' => __DIR__ . '/temp/',
    'dateFormat' => 'Y/m',
    'maxFilesize' => 5 * 1024 * 1024,
    'allowedMimeTypes' => [
        MimeType::JPEG,
        MimeType::PNG,
        MimeType::WEBP,
    ],
    'encryptName' => false,

    // Crop
    'cropRatioWidth' => 400,
    'cropRatioHeight' => 400,

    // Resize
    'resizePath' => __DIR__ . '/contents/',
    'thumbs' => [
        'big' => [
            'width' => 1200,
            'height' => 630,
        ],
        'medium' => [
            'width' => 400,
            'height' => 210,
        ],
        'small' => [
            'width' => 84,
            'height' => 44,
        ],
    ],
    'resizeQuality' => 85,
    'removeAfterResize' => true,

    // Watermark
    'watermarks' => [
        'big' => [
            'overlayFilepath' => '/path/to/big-overlay.png',
            'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
            'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
            'opacity' => 15,
            'xTransparency' => 10,
            'yTransparency' => 10,
        ],
        'medium' => [
            'overlayFilepath' => '/path/to/medium-overlay.png',
            'horizontalAlignment' => WatermarkAlignment::H_RIGHT,
            'verticalAlignment' => WatermarkAlignment::V_BOTTOM,
            'opacity' => 15,
            'xTransparency' => 10,
            'yTransparency' => 10,
        ],
    ],

    // Conversion
    'removeAfterConvert' => false,
]);

$manager = new FileManager($config);
```

### Configuration provided by the user

`FileManagerConfig` exposes two configuration representations:

```php
$config->getConfig();
$config->getProvidedConfig();
```

`getConfig()` returns the normalized configuration, including default values.

`getProvidedConfig()` returns only the configuration parameters explicitly provided by the user.

This distinction allows the library to work with normalized configuration internally while retaining the original user-provided configuration.

### Dates and generated paths

The `customDate` parameter can be used when generated files must use a specific date instead of the current date.

For example:

```php
'dateFormat' => 'Y/m',
'customDate' => new DateTimeImmutable('2026-01-15'),
```

can produce a path based on:

```text
2026/01/
```

When `customDate` is not provided, the current date is used.

> If `dateFormat` is not provided or is empty, no date-based subdirectory is generated, even if `customDate` is provided.

### File removal

The `removeAfterResize` and `removeAfterConvert` options are intended to reduce disk usage when the source files are no longer needed.

#### `removeAfterResize`

When enabled, the source image is removed after the requested thumbnails have been generated successfully.

For example:

```text
original.jpg
    │
    └── resize
         ├── big.jpg
         └── small.jpg
```

with:

```php
'removeAfterResize' => true
```

results in the source `original.jpg` being removed after successful processing.

#### `removeAfterConvert`

When enabled, the source files are removed after their WebP versions have been generated successfully.

For a direct conversion:

```text
original.jpg
    │
    └── convert
         └── original.webp
```

the original image can be removed.

When conversion operates on thumbnails, the source thumbnails are the files affected by this option.

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
