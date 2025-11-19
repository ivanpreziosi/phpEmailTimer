# PHP EMAIL TIMER
<img src="hourglass.jpg" height="200" align="right"/>
This library generates an animated GIF that visualizes a live countdown to a target date/time.
Each frame represents one second, up to a configurable maximum.
<br><br>
It is based on (and updated from) the original project by goors/php-gif-countdown, extended with improved rendering, validation, and configuration options.
<br><br>

## Features

- Generates a second-by-second animated GIF countdown
- Customizable background image, font, size, and offsets
- Anti-aliased text rendering with alpha preservation
- Fully timezone-aware countdown calculation
- Zero-padding and formatting for multi-day countdowns
- Outputs directly as a GIF stream with no caching

---

## Requirements

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

Ensure the following files exist in the repository root:

```
base.png
font.ttf
AnimatedGif.php
index.php
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

### Required GET Parameters

| Parameter | Description                                               |
| --------- | --------------------------------------------------------- |
| `time`    | A timestamp or any date string supported by `strtotime()` |

### Example With URL Encoding

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

Editable constants are defined near the top of the script:

```php
const BASE_IMAGE_PATH = __DIR__ . '/base.png';
const FONT_PATH       = __DIR__ . '/font.ttf';
const FONT_SIZE       = 60;
const FONT_COLOR_RGB  = ["r"=>255, "g"=>255, "b"=>255];
const FONT_X_OFFSET   = 60;
const FONT_Y_OFFSET   = 95;
const FRAME_DELAY     = 100; // centiseconds (100 = 1s)
const MAX_FRAMES      = 60;  // total GIF frames
const TIME_ZONE       = 'Europe/Rome';
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

## Security Considerations

* Ensure access to this script is rate-limited if used publicly.

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

* Optional caching layer
* Variable GIF dimensions
* Multiple themes or color schemes
* Support for transparency-based rendering
* Composer packaging

---

## License

Distributed under the MIT License.
See `LICENSE` for details.

---
Forked and updated from https://github.com/goors/php-gif-countdown
