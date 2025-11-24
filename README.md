# PHP EMAIL TIMER

![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-777bb4)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/version-2.1-blue)
![GD Extension](https://img.shields.io/badge/GD-required-orange) <img src="hourglass.jpg" height="200" align="right"/>
<br>
This library generates an animated GIF that visualizes a live countdown to a target date/time.
Each frame represents one second, up to a configurable maximum. <br><br>
It is based on (and updated from) the original project by goors/php-gif-countdown, extended with improved rendering, validation, and configuration options. <br><br>

## Features

* Generates a second-by-second animated GIF countdown

* Customizable **background image per request** via `bg=...`

* Customizable **font per request** via `font=...`

* Anti-aliased text rendering with alpha preservation

* Fully timezone-aware countdown calculation

* Zero-padding and formatting for multi-day countdowns

* Optional filesystem-based caching to reduce server load

* Generates a second-by-second animated GIF countdown

* Customizable background image, font, size, and offsets

* Anti-aliased text rendering with alpha preservation

* Fully timezone-aware countdown calculation

* Zero-padding and formatting for multi-day countdowns

* Optional filesystem-based caching to reduce server load (1 GIF per minute per unique timestamp)

---

## Requirements

* **PHP 7.4+**

* **GD Extension** with TrueType font support

* At least one PNG background in the `backgrounds/` folder

* At least one TrueType font in the `fonts/` folder

* **PHP 7.4+**

* **GD Extension** with TrueType font support

* A PNG base image (`base.png`)

* A TrueType font file (`font.ttf`)

---

## Installation

Clone the repository:

```bash
git clone https://github.com/<your-username>/<your-repo>.git
cd <your-repo>
```

Make sure your server has the GD extension enabled:

```bash
php -m | grep gd
```

If not present, enable it in your PHP configuration.

---

## Usage

### Basic HTTP Call

The script exposes a GIF endpoint that can be included directly in HTML `<img>` tags.

Example:

```html
<img src="path_to_the_library/index.php?time=2025-12-31%2023:59:59">
```

### GET Parameters

| Parameter         | Description                                                                                        |
| ----------------- | -------------------------------------------------------------------------------------------------- |
| `time`            | Target date/time for the countdown (timestamp or any format supported by `strtotime()`)            |
| `bg` (optional)   | Selects a background PNG file (must exist in the `backgrounds/` directory). Example: `bg=dark`     |
| `font` (optional) | Selects a TrueType font file (must exist in the `fonts/` directory). Example: `font=roboto`        |

Example using multiple parameters:

```html
<img src="path_to_the_library/index.php?time=2025-07-20T18:00:00&bg=dark&font=led">
```

Example With URL Encoding

```html
<img src="path_to_the_library/index.php?time=2025-07-20T18%3A00%3A00">
```

### Countdown Display Format

The countdown text appears as:

```
DD:HH:MM:SS
```

Example with more than 9 days:

```
12:03:45:01
```

If the target date is reached or passed, the GIF displays:

```
00:00:00:00
```

…and stops at that frame.

---

## Configuration

The script now supports *dynamic backgrounds* and *dynamic fonts*, selectable directly through query string parameters.

### Directory Structure

```
backgrounds/
   - default.png
   - dark.png
   - light.png

fonts/
   - default.ttf
   - led.ttf
   - digital.ttf
```

### Runtime Override

You can override background and font per request:

```
?time=2025-12-31%2023:59:59&bg=dark&font=digital
```

If omitted, the script falls back to the defaults *DEFAULT_BACKGROUND_NAME* for the background and *DEFAULT_FONT_NAME* for the fonts.

Editable constants are defined near the top of the script:

```php
const BASE_IMAGE_FOLDER = __DIR__ . '/backgrounds/';
const BASE_FONT_FOLDER = __DIR__ . '/fonts/';
const DEFAULT_BACKGROUND_NAME = 'base'; // omit extension (.png)
const DEFAULT_FONT_NAME = 'font'; // omit extension (.ttf)
const FONT_SIZE = 60;
const FONT_COLOR_RGB = ["r" => 255, "g" => 255, "b" => 255];
const FONT_X_OFFSET = 60;
const FONT_Y_OFFSET = 95;
const FRAME_DELAY = 100; // Ritardo tra frame in centisecondi (100 = 1 secondo)
const MAX_FRAMES = 60;   // Numero massimo di frame da generare
const TIME_ZONE = 'Europe/Rome';   // Numero massimo di frame da generare
```

### What You Can Customize

* Background image
* Font file and size
* Text color (RGB)
* Text positioning (X/Y offsets)
* Frame delay
* Maximum frames
* Timezone

---

## Caching System (Optional)

Starting from version **1.2**, the library includes a lightweight caching layer that prevents excessive regeneration of the GIF countdown.

### Why Caching?

Generating a GIF frame-by-frame is CPU-intensive.
If many clients request the same countdown (e.g., in emails), the server might regenerate identical animations multiple times per second.

The caching system ensures:

* **At most one GIF is generated every 60 seconds** per unique `time` parameter
* Subsequent requests within that minute are served instantly from disk
* Server CPU usage is drastically reduced

### How It Works

Each request is keyed using the `time` parameter:

```
countdown_<md5($time)>.gif
```

The generated GIF is stored in the `cache/` directory.

For the next 60 seconds:

* If the cached GIF exists and is fresh → it is returned immediately
* If the cache is expired or missing → a new GIF is generated and stored

### Enabling the Cache

The caching layer is automatically active if the following file exists:

```
CacheManager.php
```

and the `cache/` directory is writable.

No configuration is required.

### Cache Lifetime

The default TTL is **60 seconds**.
You may change it in the script where `CacheManager` is initialized:

```php
$cache = new CacheManager(__DIR__ . '/cache', $cacheKey, 60);
```

---

## Integration Examples

### 1. Embedding in a Website

```html
<p>Event Countdown:</p>
<img src="/path_to_the_library/index.php?time=2026-01-01%2000:00:00">
```

### 2. Dynamic Email (as long as your server allows external GIFs)

```html
<img src="https://example.com/path_to_the_library/index.php?time={{deadline}}">
```

### 3. Display in a Dashboard or Admin Panel

```php
echo '<img src="path_to_the_library/index.php?time=' . urlencode($deadline) . '">';
```

---

## Error Handling

The script returns meaningful HTTP status codes:

| Code    | Meaning                                                            |
| ------- | ------------------------------------------------------------------ |
| **400** | Invalid date format                                                |
| **403** | Missing `time` parameter                                           |
| **500** | Missing files, corrupted base image or font, GIF generation errors |

---

## Contributing

Pull requests are welcome!
Areas that might benefit from improvement:

* Variable GIF dimensions
* Multiple themes or color schemes
* Support for transparency-based rendering
* Composer packaging

---

## Changelog

### v2.0

* Added support for dynamic backgrounds via `bg` query parameter
* Added support for dynamic fonts via `font` query parameter
* Updated README accordingly

### v1.2

* Added filesystem-based caching layer (1 GIF/minute per timestamp)
* Added CacheManager class
* Updated README with new documentation
* Improved overall formatting and badges

### v1.0

* Major refactor and cleanup
* Improved rendering quality
* Better error handling
* Full timezone support

### v0.1

* Forked from goors/php-gif-countdown

---

## License

Distributed under the MIT License.
See `LICENSE` for details.

---

Forked and updated from [https://github.com/goors/php-gif-countdown](https://github.com/goors/php-gif-countdown)
