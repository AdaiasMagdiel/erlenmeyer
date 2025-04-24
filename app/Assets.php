<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;

/**
 * Class for managing and serving static assets.
 */
class Assets
{
	/**
	 * @var string The directory where assets are stored.
	 */
	private string $assetsDirectory;

	/**
	 * @var string The route prefix for asset requests.
	 */
	private string $assetsRoute;

	/**
	 * Constructs an Assets instance.
	 *
	 * @param string $assetsDirectory The directory where assets are stored. Default is "/public".
	 * @param string $assetsRoute The route prefix for asset requests. Default is "/assets".
	 * @throws InvalidArgumentException If the assets directory or route is invalid.
	 */
	public function __construct(string $assetsDirectory = "/public", string $assetsRoute = "/assets")
	{
		$this->assetsDirectory = $assetsDirectory;
		$this->assetsRoute = ltrim($assetsRoute, "/");

		// Validate assets directory
		$realDir = realpath($this->assetsDirectory);
		if ($realDir === false || !is_dir($realDir) || !is_readable($realDir)) {
			throw new InvalidArgumentException("Invalid or inaccessible assets directory: $assetsDirectory");
		}

		// Validate assets route
		if (!preg_match('/^\/?[a-zA-Z0-9_-]+(\/[a-zA-Z0-9_-]+)*\/?$/', $this->assetsRoute)) {
			throw new InvalidArgumentException("Invalid assets route: $assetsRoute");
		}
	}

	/**
	 * Gets the assets directory.
	 *
	 * @return string The assets directory.
	 */
	public function getAssetsDirectory(): string
	{
		return $this->assetsDirectory;
	}

	/**
	 * Gets the assets route.
	 *
	 * @return string The assets route.
	 */
	public function getAssetsRoute(): string
	{
		return '/' . $this->assetsRoute; // Ensure leading slash for consistency
	}

	/**
	 * Checks if the current request is for an asset.
	 *
	 * @return bool True if the request URI starts with the assets route, false otherwise.
	 */
	public function isAssetRequest(): bool
	{
		$requestPath = ltrim($_SERVER["REQUEST_URI"], "/");
		return str_starts_with($requestPath, $this->assetsRoute);
	}

	/**
	 * Serves the requested asset if it exists and is accessible.
	 *
	 * @return bool True if the asset was served successfully, false otherwise.
	 */
	public function serveAsset(): bool
	{
		$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
		if (!$requestedPath) {
			http_response_code(400);
			echo "Invalid request";
			return false;
		}

		$requestedPath = str_replace($this->assetsRoute, '', ltrim($requestedPath, '/'));
		$requestedPath = ltrim($requestedPath, '/');

		$baseDir = realpath($this->assetsDirectory);
		if ($baseDir === false) {
			http_response_code(500);
			echo "Internal server error";
			return false;
		}

		$fullPath = realpath($baseDir . '/' . $requestedPath);
		if ($fullPath === false || !$this->isValidAsset($fullPath)) {
			http_response_code(404);
			echo "File not found";
			return false;
		}

		if (strpos($fullPath, $baseDir) !== 0) {
			http_response_code(404);
			echo "File not found";
			return false;
		}

		$this->sendFileToClient($fullPath);
		return true;
	}

	/**
	 * Checks if the given path is a valid asset file.
	 *
	 * @param string $path The full path to the file.
	 * @return bool True if the path is a file and exists, false otherwise.
	 */
	private static function isValidAsset(string $path): bool
	{
		return $path && is_file($path);
	}

	/**
	 * Sends the file to the client with appropriate headers.
	 *
	 * This method sets the Content-Type, Content-Length, and caching headers
	 * (Cache-Control, ETag, Last-Modified). It also checks if the client already
	 * has the latest version using If-None-Match and If-Modified-Since headers,
	 * and sends a 304 Not Modified response if appropriate.
	 *
	 * @param string $filePath The full path to the file to send.
	 */
	private static function sendFileToClient(string $filePath): void
	{
		$mimeType = self::detectMimeType($filePath);
		$etag = md5_file($filePath);
		$lastModified = filemtime($filePath);

		header('Content-Type: ' . $mimeType);
		header('Content-Length: ' . filesize($filePath));
		header('Cache-Control: public, max-age=86400, must-revalidate');
		header('ETag: "' . $etag . '"');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

		$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
		$ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

		if (($ifNoneMatch && $ifNoneMatch === $etag) || ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified)) {
			http_response_code(304);
			return;
		}

		ob_clean();
		readfile($filePath);
	}

	/**
	 * Detects the MIME type of a file based on its extension.
	 *
	 * @param string $filePath The path to the file.
	 * @return string The MIME type of the file.
	 */
	public static function detectMimeType(string $filePath): string
	{
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		return match ($extension) {
			// Texts and Codes
			'css'   => 'text/css',
			'js'    => 'application/javascript',
			'txt'   => 'text/plain',
			'html', 'htm' => 'text/html',
			'xml'   => 'application/xml',
			'csv'   => 'text/csv',

			// Images
			'png'   => 'image/png',
			'jpg', 'jpeg', 'jpe' => 'image/jpeg',
			'webp'  => 'image/webp',
			'svg'   => 'image/svg+xml',
			'gif'   => 'image/gif',
			'ico'   => 'image/x-icon',
			'bmp'   => 'image/bmp',
			'tiff'  => 'image/tiff',
			'psd'   => 'image/vnd.adobe.photoshop',

			// Fonts
			'woff'  => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',

			// Archives
			'pdf'   => 'application/pdf',
			'zip'   => 'application/zip',
			'rar'   => 'application/x-rar-compressed',
			'tar'   => 'application/x-tar',
			'gz'    => 'application/gzip',
			'7z'    => 'application/x-7z-compressed',

			// Documents
			'doc'   => 'application/msword',
			'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'   => 'application/vnd.ms-excel',
			'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'   => 'application/vnd.ms-powerpoint',
			'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'odt'   => 'application/vnd.oasis.opendocument.text',

			// Audio/Video
			'mp3'   => 'audio/mpeg',
			'wav'   => 'audio/wav',
			'ogg'   => 'audio/ogg',
			'mp4'   => 'video/mp4',
			'mov'   => 'video/quicktime',
			'avi'   => 'video/x-msvideo',
			'webm'  => 'video/webm',

			// Data
			'json'  => 'application/json',
			'yml', 'yaml' => 'application/x-yaml',

			default => 'application/octet-stream'
		};
	}
}
