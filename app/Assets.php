<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;

/**
 * Manages and serves static asset files.
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
	 * Creates a new Assets instance.
	 *
	 * @param string $assetsDirectory The directory where assets are stored. Default is "/public".
	 * @param string $assetsRoute The route prefix for asset requests. Default is "/assets".
	 * @throws InvalidArgumentException If the assets directory or route is invalid.
	 */
	public function __construct(string $assetsDirectory = "/public", string $assetsRoute = "/assets")
	{
		$this->assetsDirectory = $assetsDirectory;
		$this->assetsRoute = ltrim($assetsRoute, "/");

		$realDir = realpath($this->assetsDirectory);
		if ($realDir === false || !is_dir($realDir) || !is_readable($realDir)) {
			throw new InvalidArgumentException("Invalid or inaccessible assets directory: $assetsDirectory");
		}

		if (!preg_match('/^\/?[a-zA-Z0-9_-]+(\/[a-zA-Z0-9_-]+)*\/?$/', $this->assetsRoute)) {
			throw new InvalidArgumentException("Invalid assets route: $assetsRoute");
		}
	}

	/**
	 * Returns the assets directory path.
	 *
	 * @return string The assets directory.
	 */
	public function getAssetsDirectory(): string
	{
		return $this->assetsDirectory;
	}

	/**
	 * Returns the route prefix used for assets.
	 *
	 * @return string The assets route.
	 */
	public function getAssetsRoute(): string
	{
		return '/' . $this->assetsRoute;
	}

	/**
	 * Determines whether the request targets an asset route.
	 *
	 * @param Request $req The current request instance.
	 * @return bool True if the request is for an asset, false otherwise.
	 */
	public function isAssetRequest(Request $req): bool
	{
		$assetsRoute = $this->getAssetsRoute();
		$requestUri = $req->getUri();

		return str_starts_with($requestUri, $assetsRoute);
	}

	/**
	 * Serves the requested asset if it exists and is accessible.
	 *
	 * @param Request $req The current request instance.
	 * @return bool True if the asset was successfully served, false otherwise.
	 */
	public function serveAsset(Request $req): bool
	{
		$requestedPath = parse_url($req->getUri() ?? '', PHP_URL_PATH);

		if (!$requestedPath || $requestedPath === '') {
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
	 * Validates that the specified path points to a file.
	 *
	 * @param string $path The path to validate.
	 * @return bool True if the path is a valid file, false otherwise.
	 */
	private static function isValidAsset(string $path): bool
	{
		return $path && is_file($path);
	}

	/**
	 * Sends the given file to the client with appropriate headers.
	 *
	 * This method sets standard headers such as Content-Type, Content-Length,
	 * Cache-Control, ETag, and Last-Modified. It also handles conditional requests
	 * (If-None-Match and If-Modified-Since) to support browser caching and may
	 * return a 304 Not Modified response when applicable.
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

		if (($ifNoneMatch && $ifNoneMatch === $etag) ||
			($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified)
		) {
			http_response_code(304);
			return;
		}

		ob_clean();
		readfile($filePath);
	}

	/**
	 * Detects the MIME type of a file based on its extension.
	 *
	 * @param string $filePath The file path to check.
	 * @return string The corresponding MIME type.
	 */
	public static function detectMimeType(string $filePath): string
	{
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		return match ($extension) {
			// Text and code files
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

			// Audio and video
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

			default => 'application/octet-stream',
		};
	}
}
