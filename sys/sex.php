<?php

/**
 * @package PHPEz
 */

/**
 * Session management helper for PHPEz.
 * 
 * "Sex" = "SessioN eXtensions" (not what you're thinking 😄)
 * 
 * Provides a namespaced, type-safe interface to PHP sessions with automatic
 * initialization and lazy session starting. Prevents "headers already sent" errors
 * by deferring session_start() until actually needed.
 * 
 * Key features:
 * - **Lazy initialization**: Session starts only when accessed, not on instantiation
 * - **Namespacing**: All keys are prefixed with a namespace root key
 * - **Fluent API**: All mutation methods return $this for chaining
 * - **Global instance**: $SEX global provides app-wide access
 * - **Type hints**: Works seamlessly with Model::toSex() / Model::fromSex()
 * 
 * Naming convention:
 * - Root key: '_phpez_main_sex_'
 * - Stored keys: '_phpez_main_sex_:keyname'
 * - Enables safe coexistence with other session data
 * 
 * Usage:
 * ```php
 * // Store a value
 * $SEX->put('current_user', $user);
 * 
 * // Retrieve a value
 * $user = $SEX->get('current_user');
 * 
 * // Fluent chaining
 * $SEX->ensure()->put('foo', 'bar')->put('baz', 'qux');
 * 
 * // Clean shutdown
 * $SEX->destroy();
 * ```
 * 
 * @see Model::toSex()
 * @see Model::fromSex()
 * 
 * @subpackage sex
 */
class Sex {
  /**
   * Whether a session was already active when this instance was created.
   * 
   * Used to track if we should start the session or if it was pre-existing.
   * 
   * @var bool
   */
  private bool $present = false;

  /**
   * The session namespace key for this Sex instance.
   * 
   * All keys stored via put() are prefixed with this: key = "$this->key:$subkey"
   * 
   * @var string
   */
  private string $key;

  /**
   * Check if a session is currently active.
   * 
   * @return bool True if session is active (PHP_SESSION_ACTIVE), false otherwise.
   * @see https://www.php.net/manual/en/function.session-status.php
   */
  public static function isActive(): bool {
    return session_status() === PHP_SESSION_ACTIVE;
  }

  /**
   * Initialize a Sex instance with optional namespace key.
   * 
   * If a session is already active, records that fact to avoid redundant
   * session_start() calls. Uses provided key or the default application root key.
   * 
   * Does NOT start the session immediately (lazy initialization).
   * 
   * @param string|null $key Optional namespace key for this instance.
   *                          If null, uses getKey() (global namespace).
   *                          Allows multiple Sex instances with different namespaces.
   */
  public function __construct($key = null) {
    if (self::isActive()) {
      $this->present = true;
      session_start();
    }
    $this->key = $key ?? static::getKey();
  }

  /**
   * Ensure a session is started, starting it if necessary.
   * 
   * Safely starts the session only if it's not already active.
   * Returns $this for fluent method chaining.
   * 
   * Safe to call multiple times; only calls session_start() once.
   * 
   * @return static Returns $this for chaining.
   */
  public function ensure(): static {
    if (!self::isActive()) {
      session_start();
    }
    return $this;
  }

  /**
   * Require an active session, throwing if not available.
   * 
   * Verifies that a session is currently active. Throws LogicException if
   * a session is not available (e.g., headers already sent, session disabled).
   * 
   * Used before reading from the session to catch configuration problems early.
   * 
   * @return static Returns $this for chaining.
   * @throws LogicException If no session is active.
   */
  public function require(): static {
    if (!self::isActive()) {
      throw new LogicException('session_required');
    }
    return $this;
  }

  /**
   * Get the root session namespace key for the application.
   * 
   * All Sex instance keys are prefixed with this to namespace data and prevent
   * conflicts with other session data.
   * 
   * @return string The root session namespace '_phpez_main_sex_'.
   */
  protected static function getKey() {
    return '_phpez_main_sex_';
  }

  /**
   * Store a value in the session.
   * 
   * Ensures a session is started, then stores the value in $_SESSION
   * with the key prefixed by the namespace.
   * 
   * Returns $this for fluent chaining.
   * 
   * @param string $key The session key name (will be namespaced).
   * @param mixed $value The value to store. Can be any serializable type.
   * @return static Returns $this for chaining.
   * 
   * @example
   * ```php
   * $SEX->put('user', $user)
   *     ->put('auth_token', $token)
   *     ->put('preferences', ['theme' => 'dark']);
   * ```
   */
  public function put(string $key, mixed $value): static {
    $this->ensure();
    $_SESSION[$this->key . ":$key"] = $value;
    return $this;
  }

  /**
   * Retrieve a value from the session.
   * 
   * Requires an active session, then retrieves the value using the namespaced key.
   * Returns null if the key is not found.
   * 
   * @param string $key The session key name to retrieve (will be namespaced).
   * @return mixed The stored value, or null if not found.
   * @throws LogicException If no session is active (via require()).
   * 
   * @example
   * ```php
   * $user = $SEX->get('user');
   * $token = $SEX->get('auth_token') ?? null;
   * ```
   */
  public function get(string $key): mixed {
    $this->require();
    return $_SESSION[$this->key . ":$key"] ?? null;
  }

  /**
   * Destroy the session and clear all stored data.
   * 
   * Ensures a session is started (if not already) and then destroys it,
   * clearing all $_SESSION data.
   * 
   * @return void
   * 
   * @example
   * ```php
   * $SEX->destroy();  // Logout: clear all session data
   * ```
   */
  public function destroy(): void {
    $this->ensure();
    session_destroy();
  }
}

/**
 * Global application session instance.
 * 
 * Provides app-wide access to session management. Initialized automatically
 * at module load time.
 * 
 * @global Sex $SEX
 * 
 * @example
 * ```php
 * $SEX->put('current_user', $user);
 * $user = $SEX->get('current_user');
 * $SEX->destroy();
 * ```
 */
$SEX = new Sex();
