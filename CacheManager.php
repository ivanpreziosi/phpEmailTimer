<?php
namespace EmailTimer;

class CacheManager
{
	private string $cacheDir;
	private string $cacheFilename;
	private int $ttl;

	public function __construct(
		string $cacheDir,
		string $cacheFilename,
		int $ttl = 60
	) {
		$this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
		$this->cacheFilename = $cacheFilename;
		$this->ttl = $ttl;

		if (!is_dir($this->cacheDir)) {
			mkdir($this->cacheDir, 0755, true);
		}
	}

	public function getCachedFilePath(): ?string
	{
		$path = $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;

		if (!file_exists($path)) return null;

		$age = time() - filemtime($path);

		return ($age <= $this->ttl) ? $path : null;
	}

	public function store(string $binaryGifData): string
	{
		$path = $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;
		file_put_contents($path, $binaryGifData);
		return $path;
	}

	public function getCachePath(): string
	{
		return $this->cacheDir . DIRECTORY_SEPARATOR . $this->cacheFilename;
	}
}
