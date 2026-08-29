<?php

/**
 * @package PHPEz
 */

/**
 * Global request startup timestamp for performance monitoring.
 * Used to calculate request processing time in milliseconds.
 * 
 * @global float $_startup
 */
$_startup = microtime(true);

/**
 * Sends a final JSON response and terminates the request.
 * 
 * This is the standardized output function for the PHPEz API. It ensures only one
 * JSON response is sent, appends request processing time, and halts execution.
 * 
 * If called multiple times, subsequent calls will dump to output with "FATAL:" prefix
 * to indicate a logic error.
 * 
 * The response format is:
 * ```json
 * {
 *   "success": true,
 *   "data": {...},
 *   "x-time-ms": 42.5
 * }
 * ```
 * 
 * @param array|object $out The response data to encode and send as JSON.
 *                          Will have 'success' and 'x-time-ms' fields added by callers.
 * @return never This function always terminates script execution.
 */
function final_json(array|object $out) {
  static $did_output = false;
  if ($did_output) {
    echo "\nFATAL:" . var_export($out, true);
    exit;
  }
  $did_output = true;
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  $out['x-time-ms'] = floor((microtime(true) - glb('_startup')) * 1000000) / 1000;
  echo json_encode($out, JSON_PRETTY_PRINT);
  exit;
}

/**
 * HTTP request method enum.
 * 
 * Represents standard HTTP verbs supported by the PHPEz API framework.
 * Provides utilities to get the current request method and validate method types.
 * 
 * @subpackage http
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods
 */
enum HTTP: string {
  case GET = 'GET';
  case POST = 'POST';
  case PUT = 'PUT';
  case DELETE = 'DELETE';
  case REPORT = 'REPORT';

  /**
   * Get the HTTP method of the current request.
   * 
   * @return static The HTTP method enum case for the current request.
   * @throws Never Relies on $_SERVER['REQUEST_METHOD'] which is always set.
   */
  public static function method(): static {
    return static::from($_SERVER['REQUEST_METHOD']);
  }

  /**
   * Check if this HTTP method matches a given method or the current request method.
   * 
   * Usage:
   * ```php
   * if (HTTP::POST->is()) { ... }                    // Check if current is POST
   * if ($method->is(HTTP::GET->value)) { ... }      // Check if $method is GET
   * ```
   * 
   * @param string|null $meth Optional HTTP method value to compare against. If null,
   *                           compares against the current request method.
   * @return bool True if this method matches the given method or current request.
   */
  public function is(string | null $meth = null): bool {
    if ($meth) return $this->value === $meth;
    return $this === static::method();
  }
};

/**
 * HTTP server utility class for request context.
 * 
 * Provides methods to retrieve client IP addresses, accounting for reverse proxies.
 * Designed as a static utility class; cannot be instantiated.
 * 
 * @subpackage http
 */
final class HTTPSrv {
  private function __construct() {
    throw new Exception('HTTPSrv is a static class and cannot be instantiated.');
  }

  private static ?string $proxyHeader = 'REMOTE_ADDR';

  /**
   * Set the HTTP header to trust for client IP detection behind reverse proxies.
   * 
   * @param string $header The HTTP header to use for client IP detection, must be in $_SERVER.
   * @return void
   */
  public static function behindRevProxy(string $header): void {
    static::$proxyHeader = $header;
  }

  /**
   * Get the remote client IP address, accounting for proxies.
   * 
   * Checks HTTP headers in order of reliability to determine the actual client IP
   * 
   * @return string The client IP address.
   * @throws HTTPException If no IP address can be determined.
   */
  public static function remote_addr(): string {
    return $_SERVER[static::$proxyHeader]
      ?? HTTPException::throw(msg: 'remote_addr_unkn');
  }
}

/**
 * HTTP response status codes enum.
 * 
 * Defines common HTTP status codes used throughout the PHPEz framework for
 * standardized API responses and error handling.
 * 
 * @subpackage http
 */
enum HTTPCode: int {
  case OK = 200;
  case CREATED = 201;
  case NO_CONTENT = 204;
  case BAD_REQUEST = 400;
  case UNAUTHORIZED = 401;
  case FORBIDDEN = 403;
  case NOT_FOUND = 404;
  case METHOD_NOT_ALLOWED = 405;
  case CONFLICT = 409;
  case INTERNAL_SERVER_ERROR = 500;
}

/**
 * Query parameter accessor for GET requests.
 * 
 * Provides type-safe access to $_GET parameters with optional default values.
 * Encapsulates parameter names to prevent direct $_GET access and enable
 * consistent parameter handling.
 * 
 * Usage:
 * ```php
 * $name = new Get('name');
 * $value = $name->v('John');  // Returns $_GET['name'] or 'John'
 * ```
 * 
 * @subpackage http
 */
class Get {
  /**
   * Initialize a GET parameter accessor.
   * 
   * @param string $key The parameter name/key to access from $_GET.
   */
  public function __construct(
    protected readonly string $key,
  ) {
  }

  /**
   * Retrieve a GET parameter value with optional default.
   * 
   * @param string|null $default Default value if the parameter is not present.
   * @return string|null The parameter value or default value.
   */
  public function v(string | null $default = null): string|null {
    return $_GET[$this->key] ?? $default;
  }
}

/**
 * Body parameter accessor for POST requests.
 * 
 * Same interface as Get, but reads values from $_POST instead of $_GET.
 * 
 * @subpackage http
 */
class Post extends Get {
  /**
   * Retrieve a POST parameter value with optional default.
   * 
   * @param string|null $default  Default value if the parameter is not present.
   * @return string|null The parameter value or default value.
   */
  public function v(string | null $default = null): string|null {
    return $_POST[$this->key] ?? $default;
  }
}

/**
 * Boolean query parameter accessor for GET requests.
 * 
 * Specialized Get class that interprets parameter values as truthy/falsy.
 * Parameter absence or explicit false-like values ('0', 'false', 'no') are false.
 * All other values are true.
 * 
 * Usage:
 * ```php
 * $debug = new BoolGet('debug');
 * if ($debug->trueish()) { ... }  // true if ?debug=1 or ?debug=yes, false otherwise
 * ```
 * 
 * @subpackage http
 */
class BoolGet extends Get {
  /**
   * Check if the GET parameter is present and truthy.
   * 
   * False-like values: missing parameter, '0', 'false', 'no' (case-insensitive).
   * All other values (including parameter present but without value): true.
   * 
   * @return bool True if the parameter is truthy, false otherwise.
   */
  public function trueish(): bool {
    $v = parent::v('__nil__');
    if ($v === '__nil__') return false;
    $v = strtolower($v);
    return !in_array($v, ['0', 'false', 'no']);
  }
}

/**
 * Represents a single API endpoint with smart request handling.
 * 
 * The Api class encapsulates routing logic and automatic parameter injection.
 * It intelligently maps request data to handler parameters based on their type hints:
 * 
 * - `int` / `string`: Extracted from URL path pattern matches (e.g., `{id:i}`, `{slug:s}`)
 * - `Obj` subclasses: Deserialized from request body (JSON)
 * - `BoolGet` / `Get`: Automatically instantiated for GET parameter access
 * - `DOMDocument`: Parsed from raw XML request body
 * - `string` (body): Raw request body as string
 * - `array` (body): JSON decoded request body as array
 * 
 * Response types are automatically serialized:
 * - `null` / `array`: Returned as JSON data
 * - Objects with `__serialize()`: Method is called for serialization
 * - `HTTPCode` enum: HTTP status code is set, no body is sent
 * 
 * Path pattern syntax:
 * - `{id:i}` - Named integer parameter
 * - `{slug:s}` - Named string parameter (word characters)
 * - `/static/path` - Literal path segment
 * 
 * Example:
 * ```php
 * $api = new Api(
 *   HTTP::POST,
 *   '/users/{id:i}/posts',
 *   function(int $id, CreatePostRequest $body) {
 *     return new PostResponse(...);
 *   }
 * );
 * ```
 * 
 * @subpackage http
 */
class Api {
  /**
   * Create a new API endpoint handler.
   * 
   * @param HTTP $method The HTTP method this endpoint responds to.
   * @param string $path The URL path pattern (may include {name:type} parameters).
   * @param Closure $handler The callback to execute. Return value is automatically serialized.
   *                         Parameters are auto-injected based on type hints.
   * @param bool $regx Unused flag for future regex support. Default false.
   */
  public function __construct(
    public readonly HTTP $method,
    public readonly string $path,
    public readonly Closure $handler,
    public readonly bool $regx = false,
  ) {
  }

  /**
   * Execute the handler with automatic parameter injection.
   * 
   * Uses reflection to inspect handler parameters and automatically injects:
   * - URL path parameters (matched by name and converted by type)
   * - Request body (JSON, XML, or raw)
   * - Query parameters (Get/BoolGet instances)
   * 
   * Serializes the response based on its type and sends it via final_json().
   * 
   * @param array|null $matchArgs Extracted URL parameters from path pattern matching.
   *                              Keys are parameter names, values are strings from URL.
   * @return never This function always terminates execution via final_json().
   * @throws HTTPException For missing type hints, unsupported parameter types, or unsupported output types.
   */
  public function run(?array $matchArgs): never {
    $refl = new ReflectionFunction($this->handler);
    $args = [];
    foreach ($refl->getParameters() as $param) {
      $typ = $param->getType()?->getName() ?? HTTPException::throw(500, 'missing param type');
      $nam = $param->getName();
      if (isset($matchArgs[$nam])) {
        $val = $matchArgs[$nam];
        if ($typ === 'int') $val = (int)$val;
        $args[$nam] = $val;
      } else if (is_subclass_of($typ, Obj::class)) {
        $inp = new $typ();
        $inp->unserializeRaw(file_get_contents('php://input'));
        $args[$nam] = $inp;
      } else if (is_a($typ, Get::class, true)) {
        $args[$nam] = new $typ($nam);
      } else if ($typ === DOMDocument::class) {
        $raw = file_get_contents('php://input');
        if ($raw) {
          $dom = new DOMDocument();
          $dom->loadXML($raw);
          $args[$nam] = $dom;
        } else {
          HTTPException::throw(400, 'missing_dom_input', more: [
            'param' => $nam,
          ]);
        }
      } else if ($typ === 'string') {
        $args[$nam] = file_get_contents('php://input');
      } else if ($typ === 'array') {
        $args[$nam] = json_decode(file_get_contents('php://input'), true);
      } else {
        HTTPException::throw(500, 'unsupported handler argument', more: [
          'param' => "$nam: $typ",
          'matches' => $matchArgs ? array_keys($matchArgs) : null,
        ]);
      }
    }

    $out =  ($this->handler)(...$args) ?? null;

    if (!$out || is_array($out) || method_exists($out, '__serialize')) {
      final_json(['success' => true, 'data' => $out]);
    } else if ($out instanceof HTTPCode) {
      http_response_code($out->value); // No Content
      exit;
    } else if ($out instanceof CustomResponse) {
      http_response_code($out->statusCode);
      foreach ($out->headers ?? [] as $k => $v) {
        header("$k: $v");
      }
      $out->stream();
      exit;
    } else {
      HTTPException::throw(500, 'unsupported_output_type', more: [
        'type' => gettype($out),
      ]);
    }
    exit;
  }

  /**
   * Test if a request path matches this endpoint's path pattern.
   * 
   * Converts path patterns with parameters to regex and tests against the given path.
   * Extracts named parameters into the $matches array for handler injection.
   * 
   * Pattern conversion:
   * - `{name:i}` → `(?<name>\d+)` (integer)
   * - `{name:s}` → `(?<name>\w+)` (word characters)
   * - Literal segments are escaped for regex
   * 
   * @param string $path The incoming request path to test.
   * @param array|null $matches Output array that will contain named parameter matches.
   *                            Only populated if the path matches.
   * @return bool True if the path matches this endpoint's pattern.
   */
  public function match(string $path, array | null &$matches = null): bool {
    $cnt = 0;
    $pro = preg_replace(
      ['/\{([a-z]+):i}/', '/\{([a-z]+):s}/'],
      ['(?<$1>\d+)', '(?<$1>\w+)'],
      $this->path,
      count: $cnt
    );
    if ($cnt) {
      $pro = '/^' . str_replace('/', '\/', $pro) . '$/';
      return preg_match($pro, $path, $matches) === 1;
    }
    return $this->path === $path;
  }
}

/**
 * DateTime serializer for JSON responses.
 * 
 * Formats DateTime objects as ISO 8601 strings with microsecond precision
 * and timezone information, suitable for JSON API responses.
 * 
 * Format: `2025-12-07T14:30:45.123456+00:00`
 * 
 * @subpackage http
 */
class JSONDateTime extends SerializableDateTime {
  /**
   * Get the DateTime format string for JSON serialization.
   * 
   * @return string ISO 8601 format with microseconds and timezone.
   */
  protected static function getFormat(): string {
    return 'Y-m-d\TH:i:s.uP';
  }
}

/**
 * Load a PHP file in a clean context with access to the App instance.
 * 
 * Used internally to load route definition files. Each loaded file receives
 * the App instance as $APP, allowing route registration via closures.
 * 
 * This pattern avoids polluting the global namespace while keeping route
 * definitions in separate files for organization.
 * 
 * @param string $path Absolute path to the PHP file to include.
 * @param App $APP The application instance for route registration.
 * @return void
 * 
 * @example
 * ```php
 * // In api/root/user.php:
 * $APP->get('/user/{id:i}', function(int $id) { ... });
 * ```
 */
function require_blank_ctx(string $path, App $APP) {
  require_once($path);
}

/**
 * Base class for custom response types with specific Content-Type handling.
 * 
 * Subclasses must implement the stream() method to output the response body.
 * 
 * @subpackage http
 */
abstract class CustomResponse {
  /**
   * The Content-Type of the request body this class handles.
   */
  public function __construct(public int $statusCode = 200, public ?array $headers = null) {
  }

  abstract public function stream(): void;
}

class StringHTMLContent extends CustomResponse {
  public function __construct(public readonly string $content) {
    parent::__construct(headers: ['Content-Type' => 'text/html']);
  }

  public function stream(): void {
    echo $this->content;
  }
}

/**
 * Helper function to create a StringHTMLContent response.
 * 
 * @param string $html The HTML content to send in the response.
 * @return StringHTMLContent A custom response object with the given HTML.
 */
function html(string $html): StringHTMLContent {
  return new StringHTMLContent($html);
}

class Redirect extends CustomResponse {
  public function __construct(public readonly string $url, int $statusCode = 302) {
    parent::__construct(headers: ['Location' => $url], statusCode: $statusCode);
  }

  public function stream(): void {
  }
}

/**
 * Helper function to create a Redirect response.
 * 
 * @param string $url The URL to redirect to.
 * @param int $statusCode The HTTP status code for the redirect (default 302).
 * @return Redirect A custom response object that performs the redirect.
 */
function redirect(string $url, int $statusCode = 302): Redirect {
  return new Redirect($url, $statusCode);
}

/**
 * Central API application router and orchestrator.
 * 
 * The App class manages all API endpoints, coordinates request routing, and
 * handles the startup sequence. It maintains a registry of endpoints organized
 * by HTTP method, and dynamically routes incoming requests to matching handlers.
 * 
 * Key features:
 * - Method-based route grouping for efficient lookup
 * - Smart path pattern matching with parameter extraction
 * - Automatic route file loading during startup
 * - Clean separation of route definitions and execution
 * 
 * Startup flow:
 * 1. Parse request path into components
 * 2. Traverse directory structure looking for PHP files matching path segments
 * 3. Load a single matching PHP file to register routes
 * 4. Execute first matching route handler
 * 5. Return 404 if no route matches
 * 
 * Example: (requires server to route all requests to startup file,
 * with `__p` parameter containing the request path)
 * ```php
 * require_once('./sys/boot.php');
 * $app = new App('./root');
 * $app->get('/users', fn() => new UserList());
 * $app->post('/users', fn(CreateUserRequest $body) => new UserResponse());
 * $app->startup($_GET['__p']);  // Start routing
 * ```
 * 
 * @subpackage http
 */
class App {
  /**
   * Route registry organized by HTTP method.
   * 
   * Structure: `['GET' => [Api, Api, ...], 'POST' => [Api, ...], ...]`
   * 
   * @var array<string,Api[]>
   */
  protected array $listeners = [];

  /**
   * Initialize the App with an optional root directory.
   * 
   * Remember to call startup() to begin request processing in case of
   * file-based routing, otherwise you can directly call run() after
   * registering routes.
   * 
   * @param string $root The root directory for file-based route loading.
   *                      Routes can be split across multiple PHP files organized
   *                      in a directory hierarchy matching URL paths.
   */
  public function __construct(protected string $root = '') {
  }

  /**
   * Route an incoming request to a matching handler.
   * 
   * Iterates through registered endpoints for the current HTTP method,
   * testing each against the request path. The first match is executed.
   * 
   * The matching process:
   * 1. Get all listeners (endpoint handlers) for the current HTTP method
   * 2. Test each listener's path pattern against the incoming path
   * 3. Extract path parameters if the pattern includes them
   * 4. Execute the first matching handler
   * 5. If no handler matches, throw NotFoundException
   * 
   * @param string $path The request path (typically from URL parsing).
   * @return never Always terminates execution via Api::run() or NotFoundException.
   * @throws NotFoundException If no endpoint matches the request path and method.
   */
  public function run(string $path): never {
    $method = HTTP::method()->value;
    $listeners = $this->listeners[$method] ?? [];
    foreach ($listeners as $lst) {
      $matches = [];
      if ($lst->match($path, $matches)) {
        $lst->run($matches);
      }
    }
    NotFoundException::throw(more: [
      'path' => $path,
      'method' => $method,
    ]);
  }

  /**
   * Register a new endpoint for any HTTP method.
   * 
   * Low-level registration method. Prefer the convenience methods (get, post, etc.)
   * unless you need dynamic method selection at runtime.
   * 
   * @param HTTP $method The HTTP method to respond to.
   * @param string $path The URL path pattern. Use {name:i} for int, {name:s} for string.
   * @param Closure $handler The request handler. Return value is automatically serialized.
   * @return static Fluent interface for chaining.
   */
  public function add(
    HTTP $method,
    string $path,
    Closure $handler,
  ): static {
    $this->listeners[$method->value][] = new Api(
      method: $method,
      path: $path,
      handler: $handler,
    );
    return $this;
  }

  /**
   * Register a POST endpoint.
   * 
   * @param string $path The URL path pattern.
   * @param Closure $handler The request handler.
   * @return static Fluent interface for chaining.
   */
  public function post(string $path, Closure $handler) {
    return $this->add(HTTP::POST, $path, $handler);
  }

  /**
   * Register a GET endpoint.
   * 
   * @param string $path The URL path pattern.
   * @param Closure $handler The request handler.
   * @return static Fluent interface for chaining.
   */
  public function get(string $path, Closure $handler) {
    return $this->add(HTTP::GET, $path, $handler);
  }

  /**
   * Register a PUT endpoint.
   * 
   * @param string $path The URL path pattern.
   * @param Closure $handler The request handler.
   * @return static Fluent interface for chaining.
   */
  public function put(string $path, Closure $handler) {
    return $this->add(HTTP::PUT, $path, $handler);
  }

  /**
   * Register a DELETE endpoint.
   * 
   * @param string $path The URL path pattern.
   * @param Closure $handler The request handler.
   * @return static Fluent interface for chaining.
   */
  public function delete(string $path, Closure $handler) {
    return $this->add(HTTP::DELETE, $path, $handler);
  }

  /**
   * Register a REPORT (CalDAV) endpoint.
   * 
   * @param string $path The URL path pattern.
   * @param Closure $handler The request handler.
   * @return static Fluent interface for chaining.
   */
  public function report(string $path, Closure $handler) {
    return $this->add(HTTP::REPORT, $path, $handler);
  }

  /**
   * Start the application and route the incoming request.
   * 
   * This is the main entry point in case of file-based routing.
   * It handles the complete startup sequence:
   * 
   * 1. **Path Validation**: Ensures the path is safe (no `..`, `.`, `\0`)
   * 2. **Directory Traversal**: Walks the directory structure
   *    following the URL path like a normal file server would
   * 3. **File Loading**: Automagically find and load a single PHP file
   *    along the path to register routes
   * 4. **Route Matching**: Passes remaining path components to route handlers
   * 5. **Error Handling**: Returns 404 if path is invalid or no file matches
   * 
   * Directory structure example:
   * ```
   * root/
   *   index.php          → Loaded first, can register routes
   *   user.php           → Loaded for /user requests
   *   user/
   *     index.php        → Loaded for /user/* requests
   *     profile.php      → Loaded for /user/profile requests
   * ```
   * 
   * Security: Prevents directory traversal attacks by rejecting path components
   * containing `..`, `.`, or null bytes.
   * 
   * @param string $path The request path from URL parsing (typically `$_GET['__p']`).
   * @return never Always terminates execution via run() or NotFoundException.
   * @throws NotFoundException For invalid paths or if no matching file is found.
   */
  public function startup(string $path): never {
    if (!$path) NotFoundException::throw();
    $chain = $path ? explode('/', $path) : [];
    $includer = $this->root;
    $remaining = null;

    foreach ($chain as $piece) {
      // Security: validate path components
      if ($piece === '..' || $piece === '.' || $piece === '' || str_contains($piece, "\0")) {
        NotFoundException::throw(more: ['invalid_path_component' => $piece]);
      }

      if ($remaining !== null) {
        // accumulate remaining path components
        $remaining[] = $piece;
        continue;
      }

      $next = "$includer/$piece";
      if (is_dir($next)) {
        $includer = $next;
        continue;
      }

      if (is_file($next . '.php')) {
        require_blank_ctx($next . '.php', $this);
        $remaining = [];
      } else if (is_file("$includer/index.php")) {
        require_blank_ctx("$includer/index.php", $this);
        $remaining = [$piece];
      } else {
        NotFoundException::throw(more: ['path' => $next]);
      }
    }

    if ($remaining === null) {
      NotFoundException::throw(more: [
        'path' => $_GET['__p'] ?? '',
        'includer' => $includer,
      ]);
    }

    $this->run(join('/', $remaining));
  }
}
