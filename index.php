<?php

/**
 * Script for generating an animated countdown GIF
 * 
 * Creates an animated GIF that shows a countdown up to a specified date/time.
 * Each frame represents one second, with a maximum of 60 frames.
 * 
 * Required GET parameters:
 * - time: timestamp or date string recognizable by strtotime()
 * Optional GET parameters:
 * - bg : background image file name, without extension (must be a .png)
 * - font : font file name, without extension (must be a .ttf)
 * 
 * @author: Ivan Preziosi - forked and updated from https://github.com/goors/php-gif-countdown
 * @version: 2.2
 */

// Load class for generating animated GIFs
require_once 'AnimatedGif.php';
require_once 'CacheManager.php';

use EmailTimer\CacheManager;

// ============================================================================
// COUNTDOWN CONFIGURATION
// ============================================================================

/**
 * Visual configuration for the countdown
 */
const BASE_IMAGE_FOLDER = __DIR__ . '/backgrounds/';
const BASE_FONT_FOLDER = __DIR__ . '/fonts/';
const DEFAULT_BACKGROUND_NAME = 'base'; // omit extension (.png)
const DEFAULT_FONT_NAME = 'font';       // omit extension (.ttf)
const FONT_SIZE = 60;
const FONT_COLOR_RGB = ["r" => 255, "g" => 255, "b" => 255];
const FONT_X_OFFSET = 60;
const FONT_Y_OFFSET = 95;
const FRAME_DELAY = 100; // Delay between frames in centiseconds (100 = 1 second)
const MAX_FRAMES = 60;   // Maximum number of frames to generate
const TIME_ZONE = 'Europe/Rome';   // Time zone

// Set timezone for correct date/time calculations
date_default_timezone_set(TIME_ZONE);

// ============================================================================
// QUERY STRING PARSING
// ============================================================================

// Load "time" parameter from query string
$time = $_GET['time'] ?? null;
// Load background image file name
$bg = $_GET['bg'] ?? DEFAULT_BACKGROUND_NAME;
$bg = $bg . ".png";
// Load font name
$font = $_GET['font'] ?? DEFAULT_FONT_NAME;
$font = $font . ".ttf";

// ============================================================================
// CACHE
// ============================================================================
// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

const CACHE_DIR = __DIR__ . '/cache';
const CACHE_FILENAME = 'countdown';
const CACHE_TIMETOLIVE = 60; // TTL in seconds

$cacheFilename = md5($time . $bg . $font) . ".gif";

$cache = new CacheManager(
	CACHE_DIR,         // cache directory
	$cacheFilename,
	CACHE_TIMETOLIVE   // TTL in seconds
);

// If cached GIF is valid, return it immediately
$cached = $cache->getCachedFilePath();
if ($cached) {
	header('Content-Type: image/gif');
	readfile($cached);
	exit; // end execution
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

/**
 * Validate "time" parameter from query string
 */

// Validate time parameter
if (!$time) {
	http_response_code(403);
	die("Error: Invalid request.");
}

// Verify requested files exist
if (!file_exists(BASE_IMAGE_FOLDER . $bg)) {
	http_response_code(500);
	die("Error: Base image not found");
}

if (!file_exists(BASE_FONT_FOLDER . $font)) {
	http_response_code(500);
	die("Error: Font not found");
}

// ============================================================================
// DATE CALCULATION
// ============================================================================

try {
	// Convert time parameter into DateTime object
	$future_date = new DateTime(date('r', strtotime($time)));
	$now = new DateTime(date('r', time()));
} catch (Exception $e) {
	http_response_code(400);
	die("Error: Invalid date format");
}

// ============================================================================
// FRAME GENERATION
// ============================================================================

$frames = [];
$delays = [];

/**
 * Generate countdown frames
 * Each frame represents one second starting from the current moment
 */
for ($i = 0; $i < MAX_FRAMES; $i++) {
	// Calculate remaining time interval
	$interval = date_diff($future_date, $now);

	// Load base image for this frame
	$image = imagecreatefrompng(BASE_IMAGE_FOLDER . $bg);

	if ($image === false) {
		http_response_code(500);
		die("Error: Unable to load base image");
	}

	// Enable quality rendering
	imagealphablending($image, true);  // Enable blending for antialias
	imagesavealpha($image, true);      // Preserve alpha channel

	// Allocate text color for this specific image
	$fontColor = imagecolorallocate($image, FONT_COLOR_RGB["r"], FONT_COLOR_RGB["g"], FONT_COLOR_RGB["b"]);

	// ========================================================================
	// COUNTDOWN TEXT FORMATTING
	// ========================================================================

	if ($future_date <= $now) {
		// Countdown finished: show zeros
		$text = '00:00:00:00';

		// Render text onto the image
		imagettftext(
			$image,
			FONT_SIZE,
			0,              // Rotation angle
			FONT_X_OFFSET,
			FONT_Y_OFFSET,
			$fontColor,
			BASE_FONT_FOLDER . $font,
			$text
		);

		// Capture GIF output into memory
		ob_start();
		imagegif($image);
		$frames[] = ob_get_contents();
		$delays[] = FRAME_DELAY;
		ob_end_clean();

		// Countdown finished, exit loop
		break;
	} else {
		// Countdown running: format as days:hours:minutes:seconds
		$text = $interval->format('%a:%H:%I:%S');

		// Add leading zero if days < 10
		if (preg_match('/^[0-9]\:/', $text)) {
			$text = '0' . $text;
		}

		// Render text onto the image
		imagettftext(
			$image,
			FONT_SIZE,
			0,
			FONT_X_OFFSET,
			FONT_Y_OFFSET,
			$fontColor,
			BASE_FONT_FOLDER . $font,
			$text
		);

		// Capture GIF output into memory
		ob_start();
		imagegif($image);
		$frames[] = ob_get_contents();
		$delays[] = FRAME_DELAY;
		ob_end_clean();
	}

	// Move forward one second for the next frame
	$now->modify('+1 second');
}

// ============================================================================
// HTTP HEADERS TO PREVENT CACHING
// ============================================================================

/**
 * Set headers to force the browser not to cache the image
 * This ensures the countdown is always updated
 */
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// ============================================================================
// GIF GENERATION, CACHING AND OUTPUT
// ============================================================================

try {
	// Generate GIF but capture output instead of sending immediately
	ob_start();
	$gif = new AnimatedGif($frames, $delays, 0);
	$gif->display();  // writes to output buffer
	$gifData = ob_get_clean();

	// Save GIF to cache
	$cache->store($gifData);

	// Send to browser
	header('Content-Type: image/gif');
	echo $gifData;
} catch (Exception $e) {
	http_response_code(500);
	die("Error generating GIF: " . $e->getMessage());
}

