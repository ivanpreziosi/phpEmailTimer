<?php
namespace EmailTimer;

/**
 * Class CacheManager
 *
 * Handles file-based caching for generated GIFs or other binary assets.
 * The cache is identified by a filename and lives in a specified directory.
 * It also supports automatic expiration through a TTL (time to live).
 *
 * Responsibilities of this class:
 * - Ensure that the cache directory exists.
 * - Generate full paths to the cached file.
 * - Verify whether a cached file is still valid based on its age.
 * - Save new cache entries (binary data) to disk.
 * - Retrieve cached file paths only if they are not expired.
 * 
 * @author: Ivan Preziosi
 * @version: 2.2
 */
class CacheManager
{
	/**
	 * @var string Directory where cache files are stored.
	 * The directory is automatically created if it does not exist.
	 */
	private string $cacheDir;

	/**
	 * @var string The filename of the cached file.
	 * Usually a hashed name (e.g., MD5 of input parameters).
	 */
	private string $cacheFilename;

	/**
	 * @var int Time-to-live for cached files, in seconds.
	 * If a file is older than TTL, it is considered expired.
	 */
	private int $ttl;

	/**
	 * Constructor
	 *
	 * @param string $cacheDir       Directory where cache will be saved
	 * @param string $cacheFilename  Name of the cached file to manage
	 * @param int    $ttl            Validity duration for cache entries (in seconds)
	 *
	 * The constructor:
	 * - Stores configuration values
	 * - Ensures that the cache directory exists (creates it if needed)
	 */
	public function __construct(
		string $cacheDir,
		string $cacheFilename,
		int $ttl = 60
	) {
		// Normalize directory path (remove trailing slashes)
		$this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
		$this->cacheFilename = $cacheFilename;
		$this->ttl = $ttl;

		// Automatically create cache directory if missing
		if (!is_dir($this->cacheDir)) {
			mkdir($this->cacheDir, 0755, true);
		}
	}

	/**
	 * Returns the path to the cached file if it exists and is still valid.
	 * If the file is expired or missing, returns null.
	 *
	 * @return string|null Full path to the cached file, or null if not reusable.
	 *
	 * Logic:
	 * - Build full path
	 * - Check file existence
	 * - Compute file age from modification time
	 * - Compare age with TTL to determine if the file is fresh enough
	 */
	public function getCachedFilePath(): ?string
	{
		$path = $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;

		// File does not exist → no cache available
		if (!file_exists($path)) return null;

		// File age in seconds
		$age = time() - filemtime($path);

		// If younger than TTL → use it, otherwise return null
		return ($age <= $this->ttl) ? $path : null;
	}

	/**
	 * Stores binary data (GIF contents) in the cache file.
	 * Overwrites any existing file with the same name.
	 *
	 * @param string $binaryGifData Raw GIF data to be written to disk
	 * @return string Returns full path of the written cache file
	 *
	 * This method:
	 * - Computes the full path
	 * - Writes the binary data to the file
	 * - Returns the path for convenience
	 */
	public function store(string $binaryGifData): string
	{
		$path = $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;
		file_put_contents($path, $binaryGifData);
		return $path;
	}

	/**
	 * Returns the full path to the cache file, regardless of its age.
	 * Does NOT check if the file exists or is valid.
	 *
	 * @return string Full path including directory and filename.
	 *
	 * Useful when:
	 * - You need the path for logging or debugging
	 * - You want to inspect or manually manage the cache file
	 */
	public function getCachePath(): string
	{
		return $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;
	}
}
