<?php

/**
 * @package phpEZ
 */

/**
 * Absolute path to the framework base directory.
 * 
 * Used for relative path rendering in debug output (converted to ~/).
 * Prevents exposing full server paths in error responses.
 * 
 * @const string
 */
define('BASE_PATH', realpath(__DIR__) . DIRECTORY_SEPARATOR);

/**
 * Safely retrieve a global variable by name.
 * 
 * Avoids PHP notices for undefined globals and provides a consistent interface
 * for accessing framework globals like $debug.
 * 
 * Usage:
 * ```php
 * $debug = glb('debug', false);      // Get $debug or default to false
 * $startup = glb('_startup');         // Get $_startup or null
 * ```
 * 
 * @param string $vname The variable name (without $).
 * @param mixed $default Default value if the global is not set. Defaults to null.
 * @return mixed The global variable value or $default.
 */
function glb(string $vname, mixed $default = null) {
  global $$vname;
  return $$vname ?? $default;
}

/**
 * Base exception class for HTTP API errors.
 * 
 * Extends Exception with HTTP response code handling and structured error metadata.
 * All API errors should extend or use this class to ensure proper HTTP responses
 * and consistent error JSON formatting.
 * 
 * Key features:
 * - **HTTP status codes**: Automatically sets response code via http_response_code()
 * - **Exception chaining**: Supports previous exception for debugging
 * - **Structured metadata**: The $more field carries contextual data (field errors, etc)
 * - **Smart constructor**: Uses named arguments to avoid position confusion
 * - **Fluent throwing**: Static throw() method for elegant error creation
 * 
 * Usage:
 * ```php
 * // Direct throw
 * throw new HTTPException('invalid_token', 401);
 * 
 * // With metadata
 * throw new HTTPException('validation_error', 400, null, [
 *   'fields' => ['email' => 'Invalid format']
 * ]);
 * 
 * // Via static method (fluent)
 * HTTPException::throw(code: 403, msg: 'forbidden', more: ['reason' => 'insufficient_permissions']);
 * 
 * // Exception chaining
 * HTTPException::throw(code: 500, msg: 'database_error', parent: $pdoException);
 * ```
 * 
 * Response format (via final_json):
 * ```json
 * {
 *   "success": false,
 *   "error": "invalid_token",
 *   "type": "HTTPException",
 *   "dbg": {
 *     "more": { "details": "..." },
 *     "trx": [ ... ]
 *   }
 * }
 * ```
 * 
 * 
 * @see NotFoundException
 * @see DataException (in db.php)
 * @subpackage exceptions
 */
class HTTPException extends Exception {
  /**
   * Initialize an HTTP exception with response code and metadata.
   * 
   * @param string $msg The error message. Defaults to 'server_error'.
   * @param int $code HTTP status code. Defaults to 500.
   * @param Throwable|null $parent Previous exception for chaining.
   * @param mixed $more Structured metadata about the error (array, object, etc).
   *                    Exposed in debug responses for client insight.
   */
  public function __construct(string $msg = 'server_error', int $code = 500, ?Throwable $parent = null, public readonly mixed $more = null) {
    parent::__construct($msg, $code, $parent);
    http_response_code($code);
  }

  /**
   * Throw an exception with flexible named arguments.
   * 
   * Static factory method for elegant exception creation. Only non-null arguments
   * are included in the constructor call, allowing clean fluent syntax without
   * positional confusion or needing to pass null for skipped parameters.
   * 
   * @param int|null $code HTTP status code.
   * @param string|null $msg Error message.
   * @param Throwable|null $parent Previous exception for chaining.
   * @param mixed $more Structured error metadata.
   * @return never Always throws the constructed exception.
   * 
   * @example
   * ```php
   * // Minimal
   * HTTPException::throw(code: 400, msg: 'bad_request');
   * 
   * // With metadata
   * HTTPException::throw(
   *   code: 400,
   *   msg: 'validation_error',
   *   more: ['field' => 'email', 'reason' => 'already_exists']
   * );
   * 
   * // With exception chaining
   * try {
   *   $pdo->query($sql);
   * } catch (PDOException $e) {
   *   HTTPException::throw(code: 500, msg: 'database_error', parent: $e);
   * }
   * ```
   */
  public static function throw(?int $code = null, ?string $msg = null, ?Throwable $parent = null, mixed $more = null): never {
    // Build named arguments array, only including non-null values
    $args = [];
    if ($msg !== null) $args['msg'] = $msg;
    if ($code !== null) $args['code'] = $code;
    if ($parent !== null) $args['parent'] = $parent;
    if ($more !== null) $args['more'] = $more;

    throw new static(...$args);
  }
}

/**
 * Exception for missing resources (HTTP 404).
 * 
 * Thrown when an API endpoint or resource cannot be found.
 * Always uses HTTP 404 status code regardless of constructor parameters.
 * 
 * Usage:
 * ```php
 * if (!$user) {
 *   NotFoundException::throw(msg: 'user_not_found', more: ['id' => $id]);
 * }
 * 
 * // Or raise directly:
 * throw new NotFoundException('post_not_found');
 * ```
 * 
 * @subpackage exceptions
 */
class NotFoundException extends HTTPException {
  /**
   * Initialize a 404 exception.
   * 
   * @param string $msg Error message. Defaults to 'not_found'.
   * @param int $_code Ignored; always uses 404. Present for API compatibility.
   * @param Throwable|null $parent Previous exception for chaining.
   * @param mixed $more Structured error metadata.
   */
  public function __construct(string $msg = 'not_found', int $_code = 404, ?Throwable $parent = null, mixed $more = null) {
    parent::__construct($msg, 404, $parent, $more);
  }
}

/**
 * Convert a file path to relative, human-readable form.
 * 
 * Replaces BASE_PATH with '~/' to hide absolute paths in error output and logs.
 * Non-string values are returned unchanged.
 * 
 * Usage (internal):
 * ```php
 * rmbasepath('/var/www/html/calendula/api/sys/db.php')
 * // Returns: ~/api/sys/db.php
 * ```
 * 
 * @param mixed $r The path or value to process.
 * @return mixed The path with BASE_PATH replaced by '~/', or original value if not a string.
 */
function rmbasepath(mixed $r) {
  if (!is_string($r)) return $r;
  return str_replace(BASE_PATH, '~/', $r);
}

/**
 * Clean and format a stack trace entry for JSON output.
 * 
 * Extracts the most relevant information from a trace entry and obscures
 * absolute paths for security. Produces a compact, debuggable format.
 * 
 * Processing:
 * 1. Returns original entry if no file information (incomplete trace)
 * 2. Formats as: "~/path/to/file.php:123"
 * 3. Includes function/class information
 * 4. Sanitizes argument paths
 * 
 * Output format:
 * ```php
 * [
 *   "~/api/sys/db.php:156" => [
 *     "fun" => "Model::save",
 *     "args" => [ "~/api/claz/User.php" ]
 *   ]
 * ]
 * ```
 * 
 * @param mixed $e A trace entry from Exception::getTrace().
 * @return mixed The cleaned trace entry, or original if no file info.
 */
function excClean(mixed $e) {
  if (!($e['file'] ?? null)) return $e;
  $k = rmbasepath($e['file']) . ':'
    . ($e['line'] ?? '?');
  $out = [
    'fun' => ($e['class'] ?? '') . ($e['type'] ?? '')
      . ($e['function'] ?? '?'),
  ];
  if (isset($e['args'])) {
    $out['args'] = array_map('rmbasepath', $e['args']);
  }
  return [$k => $out];
}

/**
 * Global PHP error handler for phpEZ.
 * 
 * Converts PHP errors (warnings, notices, etc) into throwable ErrorExceptions.
 * This ensures all errors are caught by the exception handler for consistent
 * error responses and logging.
 * 
 * Registered via set_error_handler().
 * 
 * @internal Set up automatically during bootstrap.
 */
set_error_handler(
  fn($sv, $msg, $file, $line) =>
  throw new \ErrorException($msg, 0, $sv, $file, $line)
);

/**
 * Global PHP exception handler for phpEZ.
 * 
 * Catches all uncaught exceptions and fatal errors. Sends a standardized
 * JSON error response with HTTP status code. In debug mode, includes full
 * stack traces and error metadata for development troubleshooting.
 * 
 * Response format:
 * ```json
 * {
 *   "success": false,
 *   "error": "error_message",
 *   "type": "ExceptionClass",
 *   "dbg": {
 *     "trx": [stack trace entries],
 *     "more": {...},
 *     "parent": {...}
 *   }
 * }
 * ```
 * 
 * Features:
 * - Sets appropriate HTTP status code (from HTTPException or 500)
 * - Only sets status if headers not yet sent
 * - Debug mode shows full trace and metadata
 * - Non-debug shows minimal error info (security)
 * - Cleans file paths for security
 * 
 * Registered via set_exception_handler().
 * 
 * @internal Set up automatically during bootstrap.
 */
set_exception_handler(function (Throwable $e) {
  if (!headers_sent()) {
    if (is_a($e, HTTPException::class)) {
      http_response_code($e->getCode());
    } else {
      http_response_code(500);
    }
  }
  $out = [
    'success' => false,
    'error' => $e->getMessage(),
    'type' => get_class($e),
  ];
  if (glb('debug')) {
    $out['dbg'] = [
      'trx' => array_map('excClean', $e->getTrace())
    ];
    if (is_a($e, HTTPException::class) && $e->more) {
      $out['dbg']['more'] = $e->more;
    }
    if ($prev = $e->getPrevious() ?? null) {
      $out['dbg']['parent'] = $prev;
    }
  }
  final_json($out);
});

/**
 * Global PHP shutdown handler for phpEZ.
 * 
 * Catches fatal errors that occur after exception handlers have been triggered
 * but during shutdown (e.g., parse errors, out of memory). Ensures even
 * catastrophic failures result in valid JSON error responses.
 * 
 * Checks for:
 * - E_ERROR: Fatal user-generated errors
 * - E_PARSE: Parse errors
 * - E_CORE_ERROR: Fatal PHP errors
 * - E_COMPILE_ERROR: Compilation errors
 * - E_WARNING: Non-fatal warnings (to be thorough)
 * 
 * Response format: Same as exception handler (JSON with error details).
 * 
 * Registered via register_shutdown_function().
 * 
 * @internal Set up automatically during bootstrap.
 */
register_shutdown_function(function () {
  $error = error_get_last() ?? [];
  if (in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_WARNING])) {
    $out = [
      'success' => false,
      'error' => $error['message'],
    ];
    if (glb('debug')) {
      try {
        $out['dbg'] = excClean($error);
      } catch (Exception $e) {
        var_dump($e);
      }
    }
    final_json($out);
  }
});
