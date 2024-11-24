# RawCSS

A MediaWiki extension which automatically generates AVIF versions of images and thumbnails

## System administrator guide

|  Configuration option   | Description                                       | Default            |
|:-----------------------:|---------------------------------------------------|--------------------|
| `$wgAVIFExecutablePath` | The path to the `avifenc` executable from libavif | `/usr/bin/avifenc` |

### System requirements

- PHP must be able to execute shell commands
- `libavif` must be installed
- The `imagick` PHP extension must be installed and enabled
- The [job queue](https://www.mediawiki.org/wiki/Manual:Job_queue) system must be set up

### Using `GenerateAvifFromFiles.php`

To generate AVIF versions from all PNG and JPEG files, run the following command in your MediaWiki directory:

```bash
php maintenance/run.php extensions/AVIF/maintenance/GenerateAvifFromFiles.php
```

To generate AVIF versions from specific files, run the following command in your MediaWiki directory (*change the titles*):

```bash
php maintenance/run.php extensions/AVIF/maintenance/GenerateAvifFromFiles.php --titles=A.png,B.jpeg,C.jpg
```

## User guide

No action is required to automatically generate AVIF versions of your images.

Currently, only JPEG and PNG images can be converted.

To find the AVIF version of your file, simply add `.avif` to the file URL (for example, `https://example.com/images/a.png.avif`).
