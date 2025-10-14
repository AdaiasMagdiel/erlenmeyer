# 🧪 Erlenmeyer — Changelog

A record of all notable changes to **Erlenmeyer**.

---

## [4.0.2] – 2025-10-13

### 🧩 **Request Header Normalization & Case-Insensitive Access**

This patch ensures that HTTP headers are now parsed, stored, and retrieved in a fully **case-insensitive** manner, resolving inconsistencies across different web servers and environments.

### 🌐 **Request Class**

- **Normalized header handling**

  - All headers are now stored in lowercase form (e.g. `http_authorization` → `authorization`).
  - Methods `getHeader()` and `hasHeader()` were updated to perform **case-insensitive lookups**.
  - Fixes scenarios where accessing headers like `"Authorization"` vs `"authorization"` returned `null` inconsistently.

- Adjusted parsing of special headers (`CONTENT_TYPE`, `CONTENT_LENGTH`, `CONTENT_MD5`) to match the same lowercase normalization rules.

### 🧪 **Testing**

- Added dedicated test case **`request getHeader is case-insensitive`** verifying:

  - Case-insensitive retrieval for headers such as `Authorization` and `Content-Type`.
  - Consistent `hasHeader()` behavior across mixed-case queries.
  - Ensures backward compatibility for all header access patterns.

---

## [4.0.1] – 2025-10-13

### 🧩 **Runtime Stability, Safer Exception Handling & CI Improvements**

This patch release focuses on making the framework’s runtime (`App::run()`) more resilient, ensuring that responses are never sent twice and that exceptions are handled predictably under all conditions.

### ⚙️ **Core (`App`)**

- **Improved `run()` execution flow**

  - Now verifies if a returned `Response` has already been sent before calling `send()` — preventing **duplicate output**.
  - When an **exception handler** returns a `Response`, it too is checked for the `isSent()` state to avoid multiple sends.
  - If no handler is defined, the app logs the unhandled exception and emits a **500 Internal Server Error** message safely.

- **Enhanced safety for unhandled exceptions**

  - Guarantees graceful degradation instead of silent output or partial responses.
  - Handlers for subclassed exceptions (e.g. `RuntimeException` extending `Exception`) are now correctly resolved.

### 🧪 **Testing**

Expanded test coverage to verify the runtime and middleware pipeline:

- **`AppTest`** additions:

  - ✅ Tests `App::run()` for normal routes, exceptions, and 404 fallbacks.
  - ✅ Confirms `send()` is not called twice when a response is already sent.
  - ✅ Verifies correct resolution of subclass exception handlers.
  - ✅ Confirms fallback handler invocation and middleware execution order (`first → second → after second → after first`).
  - ✅ Adds tests for all supported HTTP verbs: `PUT`, `DELETE`, `PATCH`, `OPTIONS`, `HEAD`, and multi-method matching via `match()`.
  - ✅ Validates redirects with both **301** and **302** status codes.

### 🧰 **Configuration & Housekeeping**

- **`.gitignore`**

  - Added `.coverage/` directory to ignore code-coverage reports.

- **`phpunit.xml`**

  - Simplified source inclusion (only `app/` directory) to streamline coverage analysis.

---

## [4.0.0] – 2025-10-13

### 🚀 **Major Framework Refactor & Complete Testing Overhaul**

This release marks **Erlenmeyer’s transformation** into a fully testable, modular PHP framework with cleanly separated components, a new request–response architecture, middleware support, logging improvements, and a brand-new in-framework test client.

### 🧩 **Core Framework Enhancements**

#### **App**

- Introduced a new **`handle()`** method replacing the older `dispatchRoute()` for more consistent request processing.
- Added robust **middleware support** (both global and route-specific).
- Introduced **custom exception and 404 handlers** via:

  ```php
  $app->setExceptionHandler(RuntimeException::class, $handler);
  $app->set404Handler($handler);
  ```

- Implemented **redirect helpers**, trailing-slash normalization, and multi-method route handling via `any()` and `match()`.
- Added `ErlenClient` compatibility for in-process request handling — no web server required.

#### **Routing**

- Consistent matching for paths with or without trailing slashes.
- Complex dynamic route patterns (`/users/[id]/posts/[slug]`) supported.
- Safer and stricter validation of HTTP methods, throwing exceptions for invalid ones.

#### **Middleware**

- Added global and per-route middleware chains with the ability to halt execution early.
- Middleware signatures now standardized:
  `function (Request $req, Response $res, callable $next, $params)`.

### 🌐 **HTTP Layer Improvements**

#### **Request**

- Fully rewritten for strong typing and clean input handling.
- Supports injection of raw JSON bodies for testing (`php://memory`).
- Improved header normalization and detection (e.g., `X-Requested-With` for AJAX).
- Correctly handles `_method` overrides, forwarded IPs, user agents, and secure requests.
- Adds `getJsonError()` for detailed parsing diagnostics.

#### **Response**

- Modernized fluent API with strong validation and chainable methods:

  - `withHtml()`, `withJson()`, `withText()`, `redirect()`, `withFile()`

- Added `setCORS()` helper with full CORS configuration support.
- Guards against double-sending with runtime exception enforcement.
- Built-in cookie, content type, and header management.
- Thread-safe and testable through dependency-injected header functions.

### 🧱 **Logging Subsystem**

#### **ConsoleLogger**

- Added:

  - Configurable excluded log levels.
  - HTML-escaped messages to prevent injection.
  - Strict validation of message content.
  - Consistent timestamp format via constant `TIMESTAMP_FORMAT`.
  - Fallback to `STDERR` if `error_log()` fails.

#### **FileLogger**

- Automatic log directory creation.
- File rotation at 3 MB, with up to 5 archives.
- Atomic writes with file locking (`LOCK_EX`).

#### **LogLevel**

- Enum expanded with `DEBUG` and improved documentation for all levels.

### 🧩 **Session Management**

- Simplified and fully tested:

  - Auto-started session handling.
  - CRUD methods: `get()`, `set()`, `remove()`, `has()`.
  - Flash message support (`flash()`, `getFlash()`, `hasFlash()`).
  - Throws `InvalidArgumentException` for invalid keys.

### 📦 **Assets Management**

- Stronger sanitization and security:

  - Prevents directory traversal attacks.
  - Returns `false` for missing or invalid files.

- Accurate MIME detection for common types (`.css`, `.png`, `.json`, etc.).
- Validates and serves public assets safely with correct headers.
- Provides `isAssetRequest()` and `serveAsset()` for easy integration into `App`.

### 🧪 **Testing Suite**

A complete rewrite of the testing layer using **PestPHP** and **ErlenClient**.

#### **ErlenClient**

- New internal HTTP test client that interacts directly with `App::handle()`.
- Supports:

  - JSON, form, file, and raw body payloads.
  - All HTTP methods (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, etc.).
  - Header injection and middleware testing.

- Removes the old `RequestSimulator` and `MockPhpInputStream`.

#### **Feature Tests**

- **`AppTest`** — validates routes, redirects, exception handlers, middleware, and assets.
- **`RequestTest`** — covers all aspects of `Request`, including sanitization, JSON parsing, headers, files, and AJAX.
- **`ResponseTest`** — ensures correct headers, cookies, status codes, file downloads, and CORS behavior.
- **`AssetsTest`** — tests MIME detection, path traversal protection, and file serving.
- **`SessionTest`** — tests persistent and flash session management.
- **`LoggingTest`** — validates behavior of both `ConsoleLogger` and `FileLogger`.

#### **Unit Tests**

- **`ErlenmeyerTest`** — full-stack integration tests for routing, middleware, assets, and exception handling.

### 📘 **Other Changes**

- **`phpunit.xml`** updated to include both `app/` and `src/` paths.
- **`.gitignore`** expanded for better build hygiene (logs, `.phpunit.cache`, test fixtures).
- **README.md** updated to correct test command:

  ```bash
  ./vendor/bin/pest
  ```

---

## [3.2.1] – 2025-09-27

### 🧱 **Improved Console Logging and Documentation Fix**

### ⚙️ **Enhanced**

- **`ConsoleLogger`** received a major internal upgrade:

  - ✅ **Configurable Log Filtering**

    - Added support for excluding specific log levels using the constructor:

      ```php
      new ConsoleLogger(excludedLogLevels: [LogLevel::INFO, LogLevel::DEBUG]);
      ```

    - Throws an exception if any excluded level is not an instance of `LogLevel`.

  - ✅ **Safer Exception Logging**

    - Exception messages are now HTML-escaped with `htmlspecialchars()` to prevent **log injection**.
    - Includes clearer contextual information:

      - Request method and URI (or “No request context”).
      - Fully formatted stack trace with indentation.

  - ✅ **Validation & Error Handling**

    - Throws `InvalidArgumentException` when attempting to log an empty message.
    - Skips messages for excluded log levels.
    - Fallback output to `STDERR` if `error_log()` fails, ensuring logs are never silently lost.

  - ✅ **Timestamp Consistency**

    - Introduced constant `TIMESTAMP_FORMAT = 'Y-m-d H:i:s'` for standardized log timestamps.

  - ✅ **Cleaner Output**

    - Enhanced readability of stack traces with `formatStackTrace()` helper.

### 🧾 **Documentation**

- **README.md**:

  - Updated test command reference from:

    ```bash
    ./composer/bin/pest
    ```

    to:

    ```bash
    ./vendor/bin/pest
    ```

    ensuring compatibility with modern Composer project structures.

---

## [3.2.0] – 2025-05-16

### 🧪 **Comprehensive `Request` Class Test Suite**

### ✨ **Added**

- **`tests/Feature/RequestTest.php`** — a full-coverage suite validating every aspect of the `AdaiasMagdiel\Erlenmeyer\Request` component.
  Covers more than **220 lines** of precise behavioral tests, including:

  - **Initialization & Defaults**

    - Verifies method, URI, query params, and empty defaults.

  - **Header Handling**

    - Normalization of `HTTP_` headers (e.g., `Accept`, `X-Custom`).
    - Case-insensitive access via `getHeader()`, `hasHeader()`.

  - **HTTP Method Overrides**

    - Confirms `_method` POST override behavior for `PUT` and `DELETE`.

  - **URI Sanitization**

    - Proper query stripping and fallback to `'/'` when missing.

  - **Query & Form Data Sanitization**

    - Ensures HTML entities are escaped to prevent XSS.
    - Tests default values for missing parameters.

  - **JSON Parsing**

    - Validates `getJson()` with combinations of:

      - Valid and invalid JSON.
      - Correct and incorrect `Content-Type`.
      - Strict and lenient (`ignoreContentType`) modes.

    - Confirms proper `RuntimeException` messages.
    - Supports both associative (`assoc = true`) and object (`assoc = false`) decoding.

  - **Error Reporting**

    - Checks `getJsonError()` content for invalid JSON input.

  - **Raw Body Handling**

    - Confirms reading from `php://input` and custom streams.

  - **File Uploads**

    - Validates single and multi-file handling, including indexing and missing keys.

  - **Client Metadata**

    - IP address resolution (including `REMOTE_ADDR` and `X-Forwarded-For`).
    - AJAX detection via `X-Requested-With`.
    - Secure request detection via `HTTPS` flag or port 443.

### 🧩 **Testing Infrastructure**

- Leverages the new **`RequestSimulator`** (introduced in v3.1.0) for accurate HTTP simulation.
- Integrates `data://` stream URIs to emulate raw input for JSON and text bodies.
- Ensures exceptions are thrown and captured cleanly with Pest’s fluent expectations.

### ✅ **Purpose**

This suite provides **complete behavioral assurance** for the request-parsing layer, making Erlenmeyer’s HTTP foundation verifiable, stable, and ready for future middleware or API extensions.

---

## [3.1.0] – 2025-05-16

### 🧠 **Enhanced Request Handling & Testing Utilities**

### ⚙️ **Core Improvements**

- **Request JSON Parsing Upgraded**

  - `Request::getJson()` now supports an optional parameter:

    ```php
    getJson(bool $assoc = true, bool $ignoreContentType = false)
    ```

  - When `$ignoreContentType` is `true`, JSON decoding is attempted **regardless of Content-Type**.
    Invalid JSON returns `null` instead of throwing.
  - When `$ignoreContentType` is `false`, an explicit exception is thrown if `Content-Type` isn’t `application/json`.
  - Added more accurate exception messages:

    - “Invalid Content-Type …”
    - “Failed to decode JSON: …”

  - Declared precise return types (`mixed`) for better IDE and static-analysis support.

### 🧩 **Testing Framework Refactor**

- Introduced a full **`RequestSimulator`** utility replacing the old global helper functions:

  - Centralizes GET/POST/PUT/DELETE simulation for tests.
  - Provides new helpers for JSON scenarios:

    - `postJson()`, `putJson()`, `deleteJson()`

  - Accurately emulates `php://input` via a custom **`MockPhpInputStream`** class — enabling reliable testing of raw request bodies.
  - Automatically manages superglobals (`$_SERVER`, `$_POST`, `$_GET`, etc.) per test.
  - Restores the native `php` stream wrapper after each test to prevent side effects.

- All feature tests updated to use the new class:

  - e.g. `RequestSimulator::get($app, '/route')`
  - Supports passing raw JSON strings, server headers, and uploaded files.

### ✅ **New Test Coverage**

- Added extensive tests for:

  - `OPTIONS` and `PATCH` routes.
  - Middlewares that **halt execution**.
  - JSON body handling across `POST` and `PUT` with:

    - Valid JSON
    - Invalid JSON
    - Empty bodies
    - Missing or wrong `Content-Type`
    - Strict vs lenient decoding modes.

- Verified correct CORS, redirects, sessions, flash messages, and exception handling with new simulation system.

### 🧹 **Minor Enhancements**

- Added missing `use stdClass;` in `Request.php` for proper type references.
- Refined runtime behavior in `getJson()` to handle null or empty `rawBody` gracefully.

---

## [3.0.0] – 2025-05-07

### 🚀 **Major Release – Modular Logging & Distribution Improvements**

### 🧱 **Core Changes**

- **Renamed and modularized the logging system:**

  - `DefaultLogger` has been **renamed to** `FileLogger`.
  - Introduced **`ConsoleLogger`** — a new logger that writes to the console (via `error_log()`), ideal for CLI or debugging environments.
  - `App` now defaults to a `FileLogger` (without a log directory) when no logger is provided.

- Both loggers implement the shared `LoggerInterface` and support the unified `LogLevel` enum.
- The logging subsystem now supports **pluggable strategies**:

  - Developers can inject either `FileLogger`, `ConsoleLogger`, or a custom logger.
  - `FileLogger` still features 3 MB rotation with 5 historical files kept.

### ✨ **Added**

- **`.gitattributes`** file to clean up Composer package exports:

  - Excludes `/tests`, `/.gitignore`, and `/phpunit.xml` from distributed archives.
  - Ensures production builds remain lightweight.

- **New `ConsoleLogger`**:

  - Writes logs directly to the terminal or system error log.
  - No setup required — excellent for debugging, local development, or command-line tools.
  - Fully compatible with `App` via dependency injection:

    ```php
    use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
    $app = new App(logger: new ConsoleLogger());
    ```

### 🧩 **Documentation**

- Expanded the **README.md** logging section:

  - Rewritten as **“Using Loggers”** — covers both `FileLogger` and `ConsoleLogger`.
  - Added examples and practical use cases for each.
  - Updated “Important Note” to clarify that the default logger is now `FileLogger`.
  - Improved formatting and descriptions for clarity and developer onboarding.

### ✅ **Improved**

- `App::getUri()` now safely handles missing or malformed `REQUEST_URI` values using a null-safe fallback.
- Tests updated to use `FileLogger` consistently instead of `DefaultLogger`.
- Codebase cleaned of unused constants (`MAX_LOG_SIZE`, `MAX_LOG_FILES`) and deprecated comments.
- Simplified internal file-writing logic in `FileLogger` for better maintainability.

### 🔧 **Changed**

- **Breaking change:**

  - The class `AdaiasMagdiel\Erlenmeyer\Logging\DefaultLogger` has been **removed** — replaced by `FileLogger`.
    Any reference to `DefaultLogger` must now be updated:

    ```diff
    - use AdaiasMagdiel\Erlenmeyer\Logging\DefaultLogger;
    + use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;
    ```

### 🧪 **Testing**

- All feature tests adjusted to validate `FileLogger` behavior.
- Logging tests confirmed that:

  - Log files are created and rotated correctly.
  - Exceptions are logged with request context.
  - Console logs appear correctly through `error_log`.

---

## [2.3.0] – 2025-04-27

### 🧱 **Core Refactor: Logging System Overhaul**

- Introduced a brand-new **logging architecture** with interface-based design:

  - **`LoggerInterface`** defines the contract for all loggers, including:

    - `log(LogLevel $level, string $message): void`
    - `logException(Exception $e, ?Request $request = null): void`

  - **`LogLevel` enum** provides standardized log levels:

    - `INFO`, `DEBUG`, `WARNING`, `ERROR`, `CRITICAL`

  - **`DefaultLogger`** replaces the old file-based log methods inside `App`:

    - Logs messages to a file (`info.log`) with automatic rotation (max 3 MB, 5 rotated files kept).
    - Creates directories automatically.
    - Supports structured exception logging with request context.

- The `App` class now accepts **any logger** via dependency injection:

  ```php
  $logger = new DefaultLogger('/path/to/logs');
  $app = new App(logger: $logger);
  ```

  If no logger is provided, `App` falls back to a no-op `DefaultLogger` (disabled logging).

### ✨ **Added**

- Comprehensive `README.md` documentation section explaining:

  - How to use the `DefaultLogger`.
  - Supported log levels and when to use them.
  - How to implement a **custom logger** by extending `LoggerInterface`.
  - Example of a database or file-based custom logger.

### 🔧 **Changed**

- Replaced all internal `$this->logMessage()` and `$this->logError()` calls in `App` with `LoggerInterface` usage.
- Removed legacy `logDir`, `logFile`, and manual rotation logic from `App`.
- Every lifecycle stage now produces structured log entries:

  - App initialization, asset validation, route registration, middleware execution, errors, and unhandled exceptions.

- Tests updated:

  - New test cases validate logging behavior using `DefaultLogger`.
  - All old log-directory-based tests migrated to logger-based structure.

### ✅ **Improved**

- Separation of concerns: logging logic is now decoupled from the application core.
- Enables **custom logging strategies** (e.g., database, remote service, or PSR-3 adapters).
- Strengthened testability — log output can now be mocked or intercepted.
- Expanded documentation to include **custom logger creation example** and **log level table**.

---

## [2.2.1] – 2025-04-26

### 📝 Added

- **Completely rewritten `README.md`** to serve as a detailed, structured technical reference.
- Included badges for **license**, **Composer package**, and **GitHub repository**.
- Added **table of contents** and organized sections:

  - Introduction, Features, Requirements, Installation
  - Getting Started & Routing examples
  - Middleware, Error Handling, and Asset Management
  - Session, Request, and Response APIs
  - Logging, Testing, Use Cases, and License details
  - Full **Reference Section** for `App`, `Assets`, `Session`, `Request`, and `Response` classes.

### ✨ Improved

- Examples rewritten for clarity and modern PHP style (using typed class imports).
- Added detailed code samples for:

  - Routing (`GET`, `POST`, `PUT`, etc.)
  - Session and Flash usage
  - Redirects, CORS, and Middleware chaining
  - File uploads and JSON responses

- Reference tables describing all public methods and their parameters.

---

## [2.2.0] – 2025-04-25

### 🚀 Added

- **Expanded HTTP Method Support**

  - Introduced native helpers in `App` for every HTTP method:

    - `put()`, `delete()`, `patch()`, `options()`, `head()`

  - Added:

    - `any()` — registers a route for **all** HTTP methods.
    - `match([...])` — registers a route for multiple specific methods.

- **Redirect Handling**

  - Simplified redirect logic: now uses `Response::redirect()` internally with proper 301/302 status codes.

- **Improved `Response` class**

  - Added `updateFunctions()` static method to override PHP internals such as `header()`.
    Useful for dependency injection and testing.
  - Introduced strict typing for all properties (`int`, `array`, `string|null`, etc.).

- **Enhanced Test Suite**

  - Expanded functional tests to cover:

    - Static routes, dynamic parameters, and middleware behavior.
    - All new HTTP methods and the new `any()` / `match()` helpers.
    - File upload handling (`Request::getFile()`).
    - Session and flash message flows.
    - Exception handling and custom 404s.
    - CORS headers and redirects.

  - Upgraded `tests/Pest.php` utilities to simulate:

    - PUT, DELETE, PATCH requests.
    - File uploads and richer server environment variables.
    - Custom header interception via the new `Response::updateFunctions()`.

### 🧱 Changed

- Removed redundant double-execution protection from `App::run()` (simplified control flow).
- `Response::send()` now uses the injected header function instead of PHP’s native `header()` directly.
- Redirects now return a proper `Response` object instead of calling `exit`.

### ✅ Improved

- Increased test coverage significantly; framework behavior now validated across dozens of realistic request scenarios.
- Better developer ergonomics for registering routes across multiple methods.

---

## [2.1.0] – 2025-04-24

### ✨ Added

- **Session Management System**

  - Introduced a new `Session` class for safe, static session handling.
  - Automatically starts the PHP session when the `App` instance is created.
  - Provides a clean API for working with session data:

    - `Session::get($key, $default)` – retrieve a value.
    - `Session::set($key, $value)` – store a value.
    - `Session::has($key)` / `Session::remove($key)` – check or delete keys.

  - **Flash messages support**:

    - `Session::flash($key, $value)` – store data for one request cycle.
    - `Session::getFlash($key)` – retrieve and remove flash data automatically.
    - `Session::hasFlash($key)` – check if a flash message exists.

### 🧱 Changed

- The `App` constructor now **starts the session automatically** if one isn’t active.

### ⚙️ Technical Notes

- Adds lightweight abstraction over PHP’s native `$_SESSION` to standardize session and flash data access.
- Helps developers manage authentication and temporary messages without manually starting sessions.

---

## [2.0.1] – 2025-04-24

### 🧪 Added

- **Initial automated test suite**

  - Introduced `phpunit.xml` configuration for PHPUnit.
  - Added integration with **PestPHP** as a higher-level testing interface.
  - Created `tests/Feature/AppTest.php` containing basic smoke tests for:

    - Application instantiation.
    - Log directory creation and write verification.

  - Added `tests/Pest.php` utilities providing helper functions for simulating HTTP requests:

    - `get()`, `post()`, `put()`, `delete()`, and a core `simulateRequest()` function.

  - Added a base `tests/TestCase.php` extending PHPUnit’s `TestCase` class.

### 🧱 Changed

- Project now includes testing dependencies (`phpunit`, `pestphp/pest`) in development setup.

---

## [2.0.0] – 2025-04-24

### 🚀 Major Release — Architecture Refactor

Version 2.0.0 introduces a cleaner, more modular architecture for the `App` core and static asset management system.

### ✨ Added

- **Improved `Assets` class**

  - Now validates both the asset directory and route upon construction, throwing `InvalidArgumentException` when misconfigured.
  - Added:

    - `getAssetsDirectory()` – safely exposes the absolute path.
    - `getAssetsRoute()` – returns a normalized route string with a leading `/`.

- **Flexible dependency injection**

  - The `App` constructor now accepts an optional pre-built `Assets` instance.

    - Pass `null` to disable static asset handling entirely.

  - The constructor also accepts an optional `$logDir` to customize or disable logging.

### 🧱 Changed

- **App initialization**

  - Replaced `($assetsDir, $assetsRoute, $autoServeAssets)` parameters with a single optional `Assets` object.
  - Assets validation is now delegated to the `Assets` class itself.
  - Updated default log setup to create the directory only when `$logDir` is defined.

- **Type safety**

  - `$assets` is now declared as `?Assets` (nullable).
  - Improved error messages and centralized route validation.

- **Fallback behavior**

  - The fallback route now checks `$this->assets` before attempting to serve static files, enabling full operation even when assets are disabled.

### 💥 Breaking Changes

- The `App` constructor signature has changed:

  ```php
  // Before
  new App(string $assetsDir = '/public', string $assetsRoute = '/assets', bool $autoServeAssets = true);

  // Now
  new App(?Assets $assets = null, ?string $logDir = null);
  ```

  Any code constructing `App` directly must update accordingly.

### 🧹 Internal

- Simplified internal route setup and asset checks.
- Removed redundant asset directory logic from `App`.
- Improved logging startup messages to indicate whether assets are enabled or disabled.

---

## [1.0.3] – 2025-04-24

### 🧹 Improved

- **Optional logging directory**

  - `$logDir` can now be set to `null` to **disable logging entirely**.
  - The constructor now accepts a nullable `$logDir` (`?string`) instead of a required string.
  - When `$logDir` is `null`, all log-writing operations (`logMessage`, `logError`, etc.) gracefully return without writing files.

- **Safer initialization**

  - Directory creation (`mkdir`) now runs only if `$logDir` is defined.
  - `$logFile` is initialized conditionally—only if a valid directory path exists.

### 🧱 Changed

- Replaced fixed default `__DIR__/logs` with lazy initialization.
- Simplified startup logic by removing redundant directory existence checks and braces.

---

## [1.0.2] – 2025-04-21

### 🧱 Changed

- **Refined logging configuration**

  - Introduced a dedicated `$logDir` property for flexible log directory management.
  - Default log directory moved from a hard-coded path (`__DIR__/logs/error.log`) to a dynamic structure:

    - `$logDir` → base logs directory
    - `$logFile` → points to `$logDir/info.log`

  - The constructor now accepts an optional `$logDir` parameter for custom log storage paths.
  - Automatically creates the configured log directory if it doesn’t exist.

### 🧹 Internal

- Simplified initialization flow:

  - Removed redundant fixed paths and unified log path assignment logic.
  - Adjusted internal logging initialization to work seamlessly with `logMessage()` from v1.0.1.

---

## [1.0.1] – 2025-04-21

### 🧠 Overview

This release adds an advanced logging subsystem with rotation, size control, contextual information, and runtime tracing to improve debugging and maintainability.

### ✨ Added

- **Structured logging system**

  - Introduced `logMessage()` for unified log output with timestamps and levels (`INFO`, `ERROR`, `WARNING`).
  - Added automatic **log rotation** via `rotateLogFile()`:

    - Maximum file size — 3 MB (`MAX_LOG_SIZE`).
    - Keeps up to 5 rotated log files (`MAX_LOG_FILES`).

  - Ensures that a `/logs` directory exists on initialization.

- **Enhanced exception logging**

  - `logError()` now accepts an optional `Request` context and logs HTTP method + URI along with stack traces.
  - Logged output includes timestamps, log level, and formatted trace information.

- **Application lifecycle logging**

  - Logs startup, route registration, middleware application, redirects, 404 handling, and shutdown events.
  - Captures PHP errors through the error handler and records severity, file, and line.

- **Informational events**

  - Added `INFO`-level messages for route registration, redirects, fallback handling, and middleware execution.
  - Added `WARNING` and `ERROR`-level messages for invalid configurations or runtime issues.

### 🧱 Changed

- `logError()` rewritten to use the new `logMessage()` API and to enforce rotation.
- Added safety checks and error messages before throwing exceptions for:

  - Invalid assets directories or routes.
  - Invalid exception handler classes.
  - Repeated application executions.

- Default exception handler now calls `logError($e, $req)` to preserve request context.

### 🧹 Internal

- New class constants:

  - `MAX_LOG_SIZE = 3 MB`
  - `MAX_LOG_FILES = 5`

- Added utility methods:

  - `rotateLogFile()` – rotates and renames old logs.
  - `logMessage()` – central logging function used throughout the framework.

- Added detailed `INFO` logs in:

  - `__construct()`, `route()`, `redirect()`, `set404Handler()`, `addMiddleware()`,
    `run()`, `parseRoute()`, `dispatchRoute()`, and `applyMiddlewares()`.

---

## [1.0.0] – 2025-04-21

### 🚀 Major Release

A complete refactor of the core `App` class, transforming Erlenmeyer from a minimal routing wrapper into a fully self-contained microframework.

### ✨ Added

- **Exception handling system**:

  - Introduced `setExceptionHandler()` and `getExceptionHandler()` for registering custom exception types and handlers.
  - Added default handler for uncaught exceptions that logs errors and returns a 500 response.

- **Error logging**:

  - Added internal `logError()` method to write detailed exception traces to `logs/error.log`.

- **Custom routing engine** (removed dependency on `Hermes\Router`):

  - The `App` class now includes its own routing system with pattern matching for dynamic parameters (`/users/[id]`).
  - New private methods:

    - `parseRoute()` – Converts route patterns into regex.
    - `dispatchRoute()` – Handles request routing internally.
    - `handleFallbackOrNotFound()` – Manages fallback and 404 responses.

- **Redirect system**:

  - Added `redirect()` method to define permanent (`301`) or temporary (`302`) redirects between routes.

- **Internal helpers**:

  - Added `getMethod()` and `getUri()` methods for cleaner request dispatching.

- **Better 404 handling**:

  - Default 404 handler now supports `$params` to stay consistent with other route callbacks.

### 🧱 Changed

- **Router dependency removed**:

  - `use AdaiasMagdiel\Hermes\Router;` and all references to it have been eliminated.
  - Routing and dispatching are now handled internally.

- **Middleware system enhanced**:

  - Middleware wrapping logic improved with cleaner closure composition.
  - Global and route-specific middlewares now apply uniformly to 404 and fallback handlers.

- **Assets initialization**:

  - Asset directory and route validation now occur **only if** `autoServeAssets` is enabled.

- **Default exception handling**:

  - Unhandled PHP errors are now converted into `ErrorException`s.
  - Generic fallback for unhandled exceptions now logs the error and returns a minimal HTML error message.

- **Code clarity**:

  - Added detailed docblocks for every public and private method.
  - Standardized naming conventions and inline comments for maintainability.

### 🧹 Minor Fixes

- **Request class**:

  - Fixed potential `null` handling for `User-Agent` header — now sanitized only if not null.

- **404 Handling consistency**:

  - 404 and fallback handlers now properly create a `$params` (`stdClass`) object before execution.

### ⚙️ Internal

- Added new private class properties for better structure:

  - `$routes`, `$exceptionHandlers`, `$globalMiddlewares`, `$routePattern`, `$paramPattern`, `$logFile`.

- Improved internal safety and validation in multiple areas:

  - Stricter method normalization (`GET`, `POST`, etc.).
  - Safer file path resolution for assets.
  - Defensive programming around route parsing and parameter extraction.

---

## [0.2.1] – 2025-04-19

### 🧱 Changed

- Improved constructor logic in `App` to validate the assets directory **only when `autoServeAssets` is enabled**, avoiding unnecessary checks for manual setups.
- Relaxed the assets-route validation regex to allow nested sub-paths (e.g., `/assets/images/`).
- Updated the default `404` handler signature to include the `$params` object, aligning it with route and middleware callbacks.
- Adjusted the internal 404-handling routine to initialize an empty `stdClass` for `$params`, ensuring consistent argument signatures across all handlers.
- Minor internal refactor for clearer error handling and type consistency.

---

## [0.2.0] - 2025-04-18

### ✨ Added

- **Dynamic route parameters** support (e.g., `/user/[id]`, `/blog/[category]/[slug]`).
- New `autoServeAssets` option in the `App` constructor to control automatic asset serving.
- Comprehensive and restructured `README.md` documentation:

  - New sections: _Why Erlenmeyer_, _Dynamic Routes_, _Using Templates_, _Advanced Usage_, _Error Handling_, and _Contributing_.
  - Complete examples for Apache/Nginx configuration, middlewares, and static asset management.

- Added `detectMimeType()` method to the `Assets` class.
- Enhanced `Assets` service with better error handling and security checks.
- Improved static file serving with caching headers (`ETag`, `Last-Modified`) and safer path validation.

### 🧱 Changed

- `composer.json`: changed `"type"` from `"project"` to `"library"`.
- Refactored `Router` fallback to respect the `autoServeAssets` flag.
- `App::set404Handler()` now uses `Closure::fromCallable()` for stricter type safety.
- The `Router::route()` method now injects a `$params` (`stdClass`) object automatically containing dynamic route parameters.
- Middleware functions (global and per-route) now receive `$params` as a fourth argument.
- Improved error handling in `Assets::serveAsset()` (checks base directory, returns proper 400/500 HTTP codes).
- Rewritten and reorganized `README.md` with clearer structure and examples (_Features_, _Get Started_, _Advanced Usage_).

---

## [0.1.1] - 2025-04-14

### 🧱 Changed

- `App.php`: replaced `callable` type with `Closure` for the 404 handler.
- `App::__construct()` now uses `set404Handler()` instead of assigning the closure directly.
- Used `Closure::fromCallable()` to enforce closure type safety.
- Updated docblocks and inline comments for better consistency.

---

## [0.1.0] - 2025-04-13

### 🧩 Initial Release

- Initial structure of the Erlenmeyer framework.
- Basic routing system implemented using `Router`.
- Core classes introduced: `App`, `Request`, `Response`, and `Assets`.
- Middleware and HTTP response handling implemented.
- Initial release published on Packagist.
