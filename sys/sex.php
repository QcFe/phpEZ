<?php

/**
 * @package phpEZ
 */

/**
 * phpEZ session management helper.
 * 
 * Session => Sess => Sex (not what you're thinking 😄)
 * 
 * Provides a namespaced, type-safe interface to PHP sessions with automatic
 * initialization and lazy session starting. Prevents "headers already sent" errors
 * by deferring session_start() until actually needed.
 * 
 * Key features:
 * - **Lazy initialization**: Session starts only when accessed, not on instantiation
 * - **Namespacing**: All keys are prefixed with a namespace root key
 * - **Fluent API**: All mutation methods return $this for chaining
 * - **Global instance**: Sex::global() or $SEX provides app-wide access
 * - **Type hints**: Works seamlessly with Model::toSex() / Model::fromSex()
 * 
 * Naming convention:
 * - Root key: '_phpez_shared_sex_'
 * - Stored keys: '_phpez_shared_sex_:keyname'
 * - Enables safe coexistence with other session data
 * 
 * Usage:
 * ```php
 * // Initialize in bootstrap (index.php):
 * Sex::initGlobal();
 * 
 * // Instance or static global usage:
 * Sex::put('current_user', $user);
 * $user = Sex::get('current_user');
 * 
 * // Custom namespaced instance:
 * $customSex = new Sex('custom_namespace');
 * $customSex->ensure()->put('foo', 'bar');
 * 
 * // Clean shutdown
 * Sex::destroy();
 * ```
 * 
 * @see Model::toSex()
 * @see Model::fromSex()
 * 
 * --- STATIC GLOBAL API (Sex::method) ---
 * @method static self put(string $key, mixed $value) Store a value in the global shared session.
 * @method static mixed get(string $key) Retrieve a value from the global shared session.
 * @method static self clear() Clear all namespaced session data for the global instance.
 * @method static self ensure() Ensure a session is started on the global instance.
 * @method static self require() Enforce that an active session is running on the global instance.
 * 
 * --- INSTANCE API ($SEX->method) ---
 * @method self put(string $key, mixed $value) Store a value in this instance's namespace.
 * @method mixed get(string $key) Retrieve a value from this instance's namespace.
 * @method self clear() Clear all namespaced session data for this instance.
 * @method self ensure() Ensure a session is started on this instance.
 * @method self require() Enforce that an active session is running on this instance.
 * 
 * @subpackage sex
 */ class Sex {
  private static ?self $instance = null;
  private static array $prefixes = [];

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
   * Initialize a Sex instance with an optional namespace key.
   * 
   * Does NOT start the session immediately (lazy initialization).
   * 
   * @param string $key Namespace key for this instance.
   * @throws LogicException If sessions are disabled or if key is already registered.
   */
  public function __construct(private string $key) {
    if (session_status() === PHP_SESSION_DISABLED) {
      throw new LogicException('session_disabled');
    }
    if (self::$prefixes[$this->key] ?? false) {
      throw new LogicException("Sex instance with key '{$this->key}' already exists.");
    }
    self::$prefixes[$this->key] = true;
  }

  /**
   * Initialize the global Sex instance for application-wide access.
   * 
   * Call once during application bootstrap (e.g., in index.php).
   * 
   * @throws LogicException If the global instance is already initialized.
   */
  public static function initGlobal(): void {
    if (self::$instance) {
      throw new LogicException('Sex global instance already initialized.');
    }
    self::$instance = new self('_phpez_shared_sex_');
  }

  /**
   * Retrieve the global Sex instance.
   * 
   * @return self
   * @throws LogicException If Sex::initGlobal() has not been called.
   */
  public static function global(): self {
    if (!self::$instance) {
      throw new LogicException('Sex global instance not initialized. Call Sex::initGlobal() first.');
    }
    return self::$instance;
  }

  /**
   * Ensure a session is started.
   * 
   * @return static Returns $this for fluent chaining.
   */
  protected function _ensure(): static {
    if (!self::isActive()) {
      session_start();
    }
    return $this;
  }

  /**
   * Require that a session is active.
   * 
   * @return static Returns $this for fluent chaining.
   * @throws LogicException If no session is active.
   */
  protected function _require(): static {
    if (!self::isActive()) {
      throw new LogicException('session_required');
    }
    return $this;
  }

  /**
   * Store a value in the session under the instance namespace.
   * 
   * @param string $key The key name (will be namespaced).
   * @param mixed $value The value to store.
   * @return static Returns $this for chaining.
   */
  protected function _put(string $key, mixed $value): static {
    $this->_ensure();
    $_SESSION["{$this->key}:{$key}"] = $value;
    return $this;
  }

  /**
   * Retrieve a value from the session under the instance namespace.
   * 
   * @param string $key The session key name (will be namespaced).
   * @return mixed The stored value, or null if not found.
   */
  protected function _get(string $key): mixed {
    $this->_ensure();
    return $_SESSION["{$this->key}:{$key}"] ?? null;
  }

  /**
   * Clear all namespaced session data for this instance.
   * 
   * @return static Returns $this for chaining.
   */
  protected function _clear(): static {
    $this->_require();
    foreach ($_SESSION as $k => $v) {
      if (str_starts_with($k, "{$this->key}:")) {
        unset($_SESSION[$k]);
      }
    }
    return $this;
  }

  /**
   * Magic method to proxy dynamic instance method calls to internal protected implementations.
   * 
   * @param string $name Method name without leading underscore.
   * @param array $arguments Method arguments.
   * @return mixed
   * @throws BadMethodCallException If target internal method does not exist.
   */
  public function __call(string $name, array $arguments) {
    $internalMethod = "_{$name}";
    if (!method_exists($this, $internalMethod)) {
      throw new BadMethodCallException("Method '{$name}' does not exist on " . static::class);
    }
    return $this->$internalMethod(...$arguments);
  }

  /**
   * Magic method to proxy static method calls to the global Sex instance.
   * 
   * @param string $name Method name without leading underscore.
   * @param array $arguments Method arguments.
   * @return mixed
   * @throws LogicException If global instance is uninitialized.
   * @throws BadMethodCallException If target internal method does not exist.
   */
  public static function __callStatic(string $name, array $arguments) {
    $instance = self::global();
    $internalMethod = "_{$name}";

    if (!method_exists($instance, $internalMethod)) {
      throw new BadMethodCallException("Static method '{$name}' does not exist on " . static::class);
    }

    return $instance->$internalMethod(...$arguments);
  }

  /**
   * Destroy all PHP session data across all namespaces.
   * 
   * @return void
   * @throws RuntimeException If session_destroy() fails.
   */
  public static function destroy(): void {
    if (!self::isActive()) {
      session_start();
    }
    if (!session_destroy()) {
      throw new RuntimeException('session_destroy_failed');
    }
  }
}

/**
 * Trait Sexable: Provides session persistence helpers for Model/Obj classes via Sex.
 * 
 * Classes utilizing this trait should ensure __serialize() and __unserialize() are implemented
 * to handle state serialization safely.
 */
trait Sexable {
  private static ?Sex $_sex = null;

  protected static function getSex(): Sex {
    if (!static::$_sex) {
      static::$_sex = Sex::global();
    }
    return static::$_sex;
  }

  /**
   * Set a custom Sex session handler for this specific model class.
   * 
   * @param Sex $sex
   * @return void
   */
  public static function setSex(Sex $sex): void {
    static::$_sex = $sex;
  }

  /**
   * Store this model instance in the session.
   * 
   * If used on a model that is not persisted (no ID) or is dirty, an HTTPException will be thrown.
   * 
   * @param string|null $key Optional suffix for the session key.
   * @return static Returns $this for fluent chaining.
   * @throws HTTPException If model is unpersisted or dirty.
   */
  public function toSex(?string $key = null): static {
    if (
      method_exists($this, 'id') &&
      method_exists($this, 'isDirty') &&
      (!$this->id() || $this->isDirty())
    ) {
      throw new HTTPException('model_must_be_persisted', more: [
        'id' => $this->id(),
        'dirty' => $this->isDirty(),
      ]);
    }

    static::getSex()->put(static::class . ($key ?? ''), $this);
    return $this;
  }

  /**
   * Retrieve a model instance from the session.
   * 
   * @param string|null $key Optional suffix for the session key.
   * @return static|null Restored model instance or null if not found.
   */
  public static function fromSex(?string $key = null): ?static {
    return static::getSex()->get(static::class . ($key ?? ''));
  }
}
