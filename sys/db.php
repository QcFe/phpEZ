<?php

/**
 * @package PHPEz
 */

/**
 * Attribute to mark a database field as UNIQUE.
 * 
 * Used during DDL generation to add UNIQUE constraint to the column.
 * 
 * @subpackage db
 * @see Model::ddl()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique extends ObjAttribute {
}

/**
 * Attribute to create a composite (multi-column) UNIQUE constraint.
 * 
 * Multiple fields sharing the same $indexName are combined into a single
 * UNIQUE index during DDL generation.
 * 
 * Usage:
 * ```php
 * #[UniqueMulti('idx_user_role')]
 * public int $user_id;
 * #[UniqueMulti('idx_user_role')]
 * public string $role;
 * ```
 * 
 * @subpackage db
 * @see Model::ddl()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class UniqueMulti extends ObjAttribute {
  /**
   * Initialize the composite unique index attribute.
   * 
   * @param string $indexName The name of the shared unique index. Must not be empty.
   * @throws InvalidArgumentException If indexName is empty.
   */
  public function __construct(public string $indexName) {
    if (!$indexName) {
      throw new InvalidArgumentException('unique_multi_index_name_empty');
    }
  }
}

/**
 * Attribute to create a database index on a field.
 * 
 * Specifies the name of the index to be created during DDL generation.
 * Multiple fields can share the same index name to create composite indexes.
 * 
 * @subpackage db
 * @see Model::ddl()
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Index extends ObjAttribute {
  /**
   * Initialize the index attribute.
   * 
   * @param string $name The name of the index to create. Must not be empty.
   * @throws InvalidArgumentException If name is empty.
   */
  public function __construct(public string $name) {
    if (!$name) {
      throw new InvalidArgumentException('index_name_empty');
    }
  }
}

/**
 * Attribute to override the default database column type.
 * 
 * Allows specifying custom SQL types for fields that don't map to standard types.
 * 
 * Usage:
 * ```php
 * #[CustomType('ENUM("pending", "active", "archived")')]
 * public string $status;
 * ```
 * 
 * @see Model::ddl()
 * 
 * @subpackage sex
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CustomType extends ObjAttribute {
  /**
   * Initialize the custom type attribute.
   * 
   * @param string $dbType The SQL type specification (e.g., 'ENUM(...)', 'DECIMAL(10,2)').
   */
  public function __construct(public string $dbType) {
  }
}

/**
 * Attribute to specify ON UPDATE behavior for a database column.
 * 
 * Typically used with timestamp columns that auto-update on row modification.
 * 
 * Usage:
 * ```php
 * #[OnUpdate('CURRENT_TIMESTAMP')]
 * public DBDateTime $updated_at;
 * ```
 * 
 * @see Model::ddl()
 * @subpackage db
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OnUpdate extends ObjAttribute {
  /**
   * Initialize the ON UPDATE attribute.
   * 
   * @param string $action The SQL action to perform on update (e.g., 'CURRENT_TIMESTAMP').
   */
  public function __construct(public string $action) {
  }
}

/**
 * Attribute to specify a default value for a database column.
 * 
 * The value is inserted as-is into the DDL, so use raw SQL expressions
 * (e.g., 'CURRENT_TIMESTAMP', 'NULL', '0', "'default_string'").
 * 
 * Usage:
 * ```php
 * #[DbDefault('CURRENT_TIMESTAMP')]
 * public DBDateTime $created_at;
 * 
 * #[DbDefault('0')]
 * public int $count;
 * ```
 * 
 * @see Model::ddl()
 * @subpackage db
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class DbDefault extends ObjAttribute {
  /**
   * Initialize the default value attribute.
   * 
   * @param string $value The SQL default value expression.
   */
  public function __construct(public string $value) {
  }
}

/**
 * Attribute to mark a field as NOT NULL in the database.
 * 
 * Useful when you want to enforce NOT NULL regardless of PHP type nullability,
 * or to make the requirement explicit in code.
 * 
 * @see Model::ddl()
 * @subpackage db
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NotNull extends ObjAttribute {
}

/**
 * Foreign key constraint actions enum.
 * 
 * Defines behavior when a referenced row is deleted or updated.
 * 
 * @see Foreign
 * @subpackage db
 */
enum DbThen: string {
  case CASCADE = 'CASCADE';
  case SET_NULL = 'SET NULL';
  case RESTRICT = 'RESTRICT';
  case NO_ACTION = 'NO ACTION';
  case SET_DEFAULT = 'SET DEFAULT';
}

/**
 * Attribute to create a foreign key constraint on a field.
 * 
 * Specifies referential integrity constraints linking this field to another table's id.
 * 
 * Usage:
 * ```php
 * #[Foreign(User::class, DbThen::CASCADE, DbThen::CASCADE)]
 * public int $user_id;
 * ```
 * 
 * @see Model::ddl()
 * @see Model::ddlDeps()
 * @subpackage db
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Foreign extends ObjAttribute {
  /**
   * Initialize the foreign key attribute.
   * 
   * @param string $target The target Model class that this field references.
   * @param DbThen|null $onDelete Action on referenced row delete. Defaults to $onWhatever.
   * @param DbThen|null $onUpdate Action on referenced row update. Defaults to $onWhatever.
   * @param DbThen $onWhatever Default action for both if not specified separately. Default: NO_ACTION.
   */
  public function __construct(public string $target, public ?DbThen $onDelete = null, public ?DbThen $onUpdate = null, DbThen $onWhatever = DbThen::NO_ACTION) {
    if (!$this->onDelete) $this->onDelete = $onWhatever;
    if (!$this->onUpdate) $this->onUpdate = $onWhatever;
  }
}

/**
 * Base exception for database operation failures.
 * 
 * Used for general data integrity and operation errors. Extends HTTPException
 * with a 500 status code by default (indicating server error).
 * 
 * @see DuplicateException For constraint violations.
 * @subpackage db
 */
class DataException extends HTTPException {
  /**
   * Initialize a database exception.
   * 
   * @param string|null $msg Error message. Defaults to 'duplicate'.
   * @param int|null $code HTTP status code. Defaults to 500.
   * @param Exception|null $parent Previous exception for chaining.
   * @param mixed $more Additional context data for debugging.
   */
  public function __construct(string | null $msg = null, ?int $code = null, \Exception | null $parent = null, mixed $more = null) {
    parent::__construct($msg ?? 'duplicate', $code ?? 500, $parent, $more);
  }
};

/**
 * Exception for database constraint violations (e.g., unique, foreign key).
 * 
 * Thrown for duplicate key or similar constraint violations. Returns HTTP 400
 * (bad request) instead of 500 since it's typically a client input issue.
 * 
 * @subpackage db
 */
class DuplicateException extends DataException {
  /**
   * Initialize a duplicate constraint exception.
   * 
   * @param string|null $msg Error message. Defaults to 'duplicate'.
   * @param int|null $code HTTP status code. Defaults to 400.
   * @param Exception|null $parent Previous exception for chaining.
   * @param mixed $more Additional context data for debugging.
   */
  public function __construct(string | null $msg = null, ?int $code = null, \Exception | null $parent = null, mixed $more = null) {
    parent::__construct($msg ?? 'duplicate', $code ?? 400, $parent, $more);
  }
}

/**
 * Central database connection manager for PHPEz.
 * 
 * Provides a static, singleton PDO connection with lazy initialization.
 * Configuration is set once via cfg(), then get() retrieves the connection.
 * All database operations use this connection.
 * 
 * Features:
 * - Lazy connection initialization (connects on first use)
 * - Persistent connections (PDO::ATTR_PERSISTENT)
 * - Automatic exception mode for errors
 * - Associative array fetch mode
 * - Table name prefixing support
 * 
 * Usage:
 * ```php
 * Database::cfg(
 *   'mysql:host=localhost;dbname=myapp',
 *   'user',
 *   'password',
 *   'app_'  // optional prefix
 * );
 * 
 * $pdo = Database::get();
 * ```
 * 
 * @subpackage db
 */
class Database {
  /**
   * The singleton PDO connection instance.
   * 
   * @var PDO|null
   */
  private static ?PDO $connection = null;

  /**
   * Configured DSN (Data Source Name).
   * 
   * @var string|null
   */
  private static ?string $dsn = null;

  /**
   * Configured database username.
   * 
   * @var string|null
   */
  private static ?string $user = null;

  /**
   * Configured database password.
   * 
   * @var string|null
   */
  private static ?string $pass = null;

  /**
   * Table name prefix for all models.
   * 
   * @var string
   */
  private static string $pfx = '';

  /**
   * Configure the database connection parameters.
   * 
   * Must be called before any database operations. Configuration is stored
   * statically and reused for all future connections.
   * 
   * @param string $dsn PDO Data Source Name (e.g., 'mysql:host=localhost;dbname=mydb').
   * @param string $user Database username.
   * @param string $pass Database password.
   * @param string $pfx Optional table name prefix (appended to all table names).
   * @return void
   */
  public static function cfg(string $dsn, string $user, string $pass, string $pfx = '') {
    static::$dsn = $dsn;
    static::$user = $user;
    static::$pass = $pass;
    static::$pfx = $pfx;
  }

  /**
   * Establish a PDO connection if not already connected.
   * 
   * Called automatically by get(). Throws DataException if cfg() was not called first.
   * 
   * @return void
   * @throws DataException If database configuration was not set.
   */
  public static function connect(): void {
    if (!static::$dsn) {
      DataException::throw(msg: 'db_did_not_initCfg');
    }
    if (static::$connection === null) {
      static::$connection = new PDO(static::$dsn, static::$user, static::$pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      ]);
    }
  }

  /**
   * Get the singleton PDO connection instance.
   * 
   * Lazily initializes the connection on first call. Subsequent calls return
   * the same connection instance.
   * 
   * @return PDO The database connection.
   */
  public static function get(): PDO {
    if (static::$connection === null) {
      static::connect();
    }
    return static::$connection;
  }

  /**
   * Get the configured table name prefix.
   * 
   * @return string The prefix set during cfg(), or empty string if not set.
   */
  public static function pfx(): string {
    return static::$pfx;
  }
}

/**
 * Trait for models supporting instance caching by ID.
 * 
 * Implements a simple identity map pattern: once a model instance is loaded,
 * subsequent queries for the same ID return the cached instance.
 * 
 * Models must implement find() method; this trait wraps it with caching.
 * 
 * Usage:
 * ```php
 * class User extends Model {
 *   use CachableModel;
 *   
 *   public static function find(string $id_or_val, string $field = 'id'): ?static {
 *     // your find implementation
 *   }
 * }
 * 
 * $user1 = User::findById(5);      // Hits database
 * $user2 = User::findById(5);      // Returns cached instance
 * $user3 = User::findById(5, false); // Skips cache, hits database
 * ```
 * @subpackage db
 */
trait CachableModel {
  /**
   * Instance cache indexed by ID.
   * 
   * @var array<int, static>
   */
  protected static array $cache = [];

  /**
   * Find a model instance by field value. Must be implemented by using class.
   * 
   * @param string $id_or_val The value to search for.
   * @param string $field The field name to search on. Defaults to 'id'.
   * @return static|null The model instance if found, null otherwise.
   */
  abstract public static function find(string $id_or_val, string $field = 'id'): ?static;

  /**
   * Find a model by ID with optional caching.
   * 
   * @param int|null $id The ID to find. Returns null if ID is null.
   * @param bool $cachable Whether to use/populate the instance cache. Defaults to true.
   * @return static|null The model instance if found, null otherwise.
   */
  public static function findById(?int $id, bool $cachable = true): ?static {
    if (!$id) return null;
    if ($cachable) {
      return static::$cache[$id] ??= static::find($id);
    }
    return static::find($id, 'id');
  }
}

/**
 * Filter option for querying soft-deleted rows.
 * 
 * @see SoftDelete::findMany()
 * @see SoftDelete::find()
 * @subpackage db
 */
enum SoftDeleteFilter {
  /** Only rows where deleted_at IS NULL (default). */
  case EXCLUDE;
  /** All rows, regardless of deleted_at. */
  case INCLUDE;
  /** Only rows where deleted_at IS NOT NULL. */
  case ONLY;
}

/**
 * Trait adding soft-delete support to a Model.
 * 
 * Instead of removing rows, delete() (by default) sets a deleted_at timestamp.
 * find()/findMany() transparently exclude soft-deleted rows unless a
 * SoftDeleteFilter is given.
 * 
 * Usage:
 * ```php
 * class Post extends Model {
 *   use SoftDelete;
 * }
 * 
 * $post->delete();                       // soft delete
 * $post->delete(soft: false);            // hard delete
 * $post->restore();                      // undo soft delete
 * Post::find($id, softDeleteFilter: SoftDeleteFilter::ONLY);
 * ```
 * 
 * @subpackage db
 */
trait SoftDelete {
  abstract public function save($forCreate = false, ?int $idForUpdate = null): static;
  abstract public function id(): ?int;

  #[Index('idx_deleted_at')]
  protected ?DBDateTime $deleted_at = null;

  /**
   * Check whether this instance is soft-deleted.
   * 
   * @return bool True if deleted_at is set.
   */
  public function isDeleted(): bool {
    return isset($this->deleted_at);
  }

  /**
   * Undo a soft delete by clearing deleted_at and saving.
   * 
   * @return static Returns $this for fluent interface.
   * @throws DataException If the instance has no ID or is not currently deleted.
   */
  public function restore(): static {
    if (!$this->id()) {
      DataException::throw(msg: 'restore_no_id', more: [
        'claz' => static::class,
      ]);
    }
    if (!isset($this->deleted_at)) {
      DataException::throw(msg: 'restore_not_deleted', more: [
        'claz' => static::class,
        'id' => $this->id(),
      ]);
    }
    $this->deleted_at = null;
    return $this->save();
  }

  /**
   * Delete this model instance (soft delete by default).
   *
   * @param bool $soft If true, performs a soft delete (default). If false, performs a hard delete.
   * @return void
   */
  public function delete($soft = true): void {
    if (!$this->id()) {
      DataException::throw(msg: 'delete_no_id', more: [
        'claz' => static::class,
      ]);
    }
    if ($soft) {
      $this->deleted_at = new DBDateTime();
      $this->save();
    } else {
      parent::delete();
    }
  }

  /**
   * Build the default soft-delete filter condition.
   *
   * @param SoftDeleteFilter $filter The filter to apply.
   * @return string|null SQL condition to append, or null when no filter is needed.
   */
  protected static function softDeleteFilter(SoftDeleteFilter $filter): ?string {
    if ($filter === SoftDeleteFilter::INCLUDE) {
      return null;
    }
    $tbl = parent::tbl();
    $cnd = $filter === SoftDeleteFilter::ONLY ? 'NOT' : '';
    return "$tbl.deleted_at IS $cnd NULL";
  }

  /**
   * Find multiple models with a given condition.
   *
   * @param string $cond The condition to apply.
   * @param array $fieldSet The fields to include in the query.
   * @param string $joins The joins to include in the query.
   * @param SoftDeleteFilter $filter The soft delete filter to apply.
   * @return array The found models.
   */
  public static function findMany(string $cond = '1=1', array $fieldSet = [], string $joins = '', SoftDeleteFilter $filter = SoftDeleteFilter::EXCLUDE): array {
    $softDeleteFilter = static::softDeleteFilter($filter);
    if ($softDeleteFilter) {
      $cond = "($cond) AND $softDeleteFilter";
    }
    return parent::findMany($cond, $fieldSet, $joins);
  }

  /**
   * Find a single model with a given condition.
   *
   * @param string $id_or_val The value to search for.
   * @param string $field The field to search in.
   * @param SoftDeleteFilter $softDeleteFilter The soft delete filter to apply.
   * @return static|null The found model, or null if not found.
   */
  public static function find(string $id_or_val, string $field = 'id', SoftDeleteFilter $softDeleteFilter = SoftDeleteFilter::EXCLUDE): ?static {
    $out = static::findMany("$field = :val", ['val' => $id_or_val], '', $softDeleteFilter);
    if (count($out) > 1) DataException::throw(msg: 'unexpected_multi', more: [
      'claz' => static::class,
      'field' => $field,
      'val' => $id_or_val,
    ]);
    return $out[0] ?? null;
  }
}

/**
 * Base ORM model class for database-backed entities.
 * 
 * Extends Obj with persistence layer: DDL generation, CRUD operations, and
 * automatic timestamp tracking. Each Model subclass corresponds to a database
 * table with automatic schema generation.
 * 
 * Key features:
 * - **Automatic timestamps**: created_at and updated_at fields auto-managed
 * - **CRUD methods**: find(), findMany(), save(), delete()
 * - **DDL generation**: Reflect properties to generate CREATE TABLE statements
 * - **Foreign keys**: Support for foreign key constraints with cascade behavior
 * - **Dirty tracking**: Detect if model has been modified since load
 * - **Session persistence**: Store/retrieve models from session (Sex)
 * - **Type-mapped columns**: Auto-detect SQL types from PHP property types
 * 
 * Naming conventions:
 * - Table name = Database prefix + Model class name
 * - Primary key = 'id' (auto-increment INT)
 * - Timestamps = created_at, updated_at (auto-managed)
 * 
 * Example:
 * ```php
 * class User extends Model {
 *   public string $email;
 *   #[Unique]
 *   public string $username;
 *   #[DoNotSerialize]
 *   public string $password_hash;
 * }
 * 
 * // Create table
 * User::createTable();
 * 
 * // Create and persist
 * $user = new User(['email' => 'test@example.com', 'username' => 'alice']);
 * $user->save(forCreate: true);  // Now has ID
 * 
 * // Find and update
 * $user = User::find('alice', 'username');
 * $user->email = 'newemail@example.com';
 * $user->save();  // Updates existing row
 * 
 * // Query multiple
 * $admins = User::findMany('role = :role', ['role' => 'admin']);
 * ```
 * 
 * @see Database
 * @see CachableModel
 * @subpackage db
 */
abstract class Model extends Obj {
  /**
   * Internal hash of serialized state for dirty tracking.
   * 
   * Stored after load/save to detect modifications.
   * 
   * @var string|null
   */
  #[DoNotSerialize]
  #[DoNotDeserialize]
  private ?string $_hash = null;

  /**
   * Timestamp when the record was created.
   * 
   * Auto-set by database to CURRENT_TIMESTAMP on insert.
   * Not included in serialization (internal database field).
   * 
   * @var DBDateTime|null
   */
  #[DoNotSerialize]
  #[NotNull]
  #[DbDefault('CURRENT_TIMESTAMP')]
  public protected(set) ?DBDateTime $created_at = null;

  /**
   * Timestamp when the record was last modified.
   * 
   * Auto-updated by database to CURRENT_TIMESTAMP on any row modification.
   * Not included in serialization (internal database field).
   * 
   * @var DBDateTime|null
   */
  #[DoNotSerialize]
  #[NotNull]
  #[DbDefault('CURRENT_TIMESTAMP')]
  #[OnUpdate('CURRENT_TIMESTAMP')]
  public protected(set) ?DBDateTime $updated_at = null;

  /**
   * Primary key of this model instance.
   * 
   * Null for unsaved (new) instances. Auto-set by database on insert.
   * 
   * @var int|null
   */
  protected ?int $id = null;

  /**
   * Initialize a Model instance.
   * 
   * @param mixed $data Optional data to deserialize. If provided, resets the hash
   *                    for dirty tracking.
   */
  public function __construct(mixed $data = null) {
    parent::__construct($data);
    if ($data) {
      $this->_hash = static::makeHash();
    }
  }

  /**
   * Get the primary key of this model.
   * 
   * @return int|null The ID if persisted, null for new instances.
   */
  public function id(): ?int {
    return $this->id;
  }

  /**
   * Get the full table name including prefix.
   * 
   * @return string The prefixed table name derived from class name.
   */
  public static function tbl() {
    return Database::pfx() . static::class;
  }

  // substitute {Class} with table name in joins
  protected static function processTabTpls(string $joins): string {
    return preg_replace_callback('/{\s*(\w+)\s*}/', function ($m) {
      $cls = $m[1];
      if (!class_exists($cls)) {
        DataException::throw(msg: 'join_class_not_found', more: [
          'claz' => $cls,
        ]);
      }
      return ($cls)::tbl();
    }, $joins);
  }

  /**
   * Find multiple model instances matching a condition.
   * 
   * Executes a SELECT query with optional WHERE conditions and parameter binding.
   * Uses prepared statements to prevent SQL injection.
   * 
   * @param string $cond SQL WHERE clause condition. Defaults to '1=1' (all rows).
   *                     Use parameter placeholders like ':fieldname' for binding.
   * @param array $fieldSet Named parameters to bind to the query.
   *                         Keys must match placeholders in $cond.
   * @return static[] Array of model instances, empty if no matches.
   * @throws DataException For query execution failures.
   * 
   * @example
   * ```php
   * $admins = User::findMany('role = :role AND active = :active', 
   *   ['role' => 'admin', 'active' => 1]);
   * ```
   */
  public static function findMany(string $cond = '1=1', array $fieldSet = [], string $joins = ''): array {
    $pdo = Database::get();
    $tbl = static::tbl();
    if ($joins) {
      $joins = static::processTabTpls($joins);
      $cond = static::processTabTpls($cond);
    }
    $stmt = $pdo->prepare("SELECT $tbl.* FROM $tbl $joins WHERE $cond");

    foreach ($fieldSet as $k => $v) {
      if (!is_scalar($v) && is_a($v, Parsable::class, true)) {
        $fieldSet[$k] = $v->marshall();
      }
    }

    $stmt->execute($fieldSet);
    $data = $stmt->fetchAll();

    if ($data === null) DataException::throw(msg: 'fetch_failure', more: [
      'claz' => static::class,
      'pdoErr' => $pdo->errorInfo(),
      'query' => $stmt->queryString,
      'params' => $fieldSet,
    ]);

    return array_map(fn($d) => new static($d), $data);
  }

  /**
   * Find a single model instance by field value.
   * 
   * Queries for a specific value in a field and returns the first match.
   * Throws DataException if multiple matches found (expected unique).
   * 
   * @param string $id_or_val The value to search for.
   * @param string $field The field name to query on. Defaults to 'id'.
   * @return static|null The model instance if found, null if no match.
   * @throws DataException If multiple records match (field should be unique).
   * 
   * @example
   * ```php
   * $user = User::find('alice@example.com', 'email');
   * $post = Post::find(42);  // Implicitly searches by 'id'
   * ```
   */
  public static function find(string $id_or_val, string $field = 'id'): ?static {
    $out = static::findMany("$field = :val", ['val' => $id_or_val]);
    if (count($out) > 1) DataException::throw(msg: 'unexpected_multi', more: [
      'claz' => static::class,
      'field' => $field,
      'val' => $id_or_val,
    ]);
    return $out[0] ?? null;
  }

  /**
   * Lifecycle hook called before saving to the database.
   * 
   * Override in subclasses to implement custom validation, normalization,
   * or calculated fields before persistence.
   * 
   * Normally does nothing; can be customized for advanced logic.
   * 
   * Called by save() before INSERT or UPDATE.
   */
  public function beforeSave() {
  }

  /**
   * Persist this model to the database as INSERT or UPDATE.
   * 
   * Intelligently chooses INSERT for new instances (no ID) or UPDATE for
   * existing ones (has ID). Handles duplicate key errors gracefully.
   * 
   * Flow:
   * 1. Calls beforeSave() hook
   * 2. Serializes model to get column values
   * 3. Generates and executes INSERT or UPDATE query
   * 4. For new records: sets ID from lastInsertId()
   * 5. Updates hash for dirty tracking
   * 
   * @param bool $forCreate Flag to enforce INSERT-only (fails if ID exists).
   * @param int|null $idForUpdate ID to use as WHERE clause for UPDATE.
   *                              Must match $this->id if both provided.
   *                              Fails if provided without existing $this->id.
   * @return static Returns $this for fluent interface.
   * @throws DataException For operation failures or validation errors.
   * @throws DuplicateException For unique constraint violations (HTTP 400).
   * 
   * @example
   * ```php
   * $user = new User(['name' => 'Alice']);
   * $user->save(forCreate: true);  // INSERT, sets $user->id
   * 
   * $user->name = 'Alicia';
   * $user->save();  // UPDATE using existing ID
   * ```
   */
  public function save($forCreate = false, ?int $idForUpdate = null): static {
    $pdo = Database::get();
    $this->beforeSave();
    $fields = $this->__serialize();
    $columns = array_keys($fields);
    foreach ($fields as $k => $v) {
      if (is_bool($v) || is_numeric($v)) {
        $fields[$k] += 0; // enforce scalar
      }
    }

    $hasId = isset($this->id);
    if ($hasId) {
      if ($forCreate) {
        DataException::throw(msg: 'id_on_create', more: [
          'claz' => static::class,
        ]);
      }
      if ($idForUpdate && $idForUpdate !== $this->id) {
        DataException::throw(msg: 'id_mismatch', more: [
          'claz' => static::class,
          'id' => $this->id,
          'idForUpdate' => $idForUpdate,
        ]);
      }
      $query = 'UPDATE ' . static::tbl() . ' SET ' . implode(', ', array_map(fn($col) => "$col = :$col", $columns)) . ' WHERE id = :id';
    } else {
      if ($idForUpdate) {
        DataException::throw(msg: 'id_for_update_on_create', more: [
          'claz' => static::class,
          'idForUpdate' => $idForUpdate,
        ]);
      }
      $query = 'INSERT INTO ' . static::tbl() . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_map(fn($col) => ":$col", $columns)) . ')';
    }

    $stmt = $pdo->prepare($query);
    $dbg_more = [
      'claz' => static::class,
      'op' => $this->id ? 'update' : 'insert',
      'query' => $query,
      'fields' => $fields,
    ];
    try {
      if (!$stmt->execute($fields)) {
        DataException::throw(msg: 'save_fail', more: $dbg_more);
      }
    } catch (\PDOException $e) {
      if (($e->errorInfo[1] ?? -1) === 1062) {
        DuplicateException::throw(parent: $e, more: $dbg_more);
      }
      DataException::throw(msg: 'save_fail', parent: $e, more: $dbg_more);
    }
    if (!$hasId) {
      $this->id = $pdo->lastInsertId();
    }
    $this->_hash = static::makeHash();
    return $this;
  }

  /**
   * Delete this model from the database.
   * 
   * Removes the row with matching ID. Fails if model is not persisted (no ID).
   * 
   * @return void
   * @throws DataException If model has no ID or deletion fails.
   * 
   * @example
   * ```php
   * $user = User::find(42);
   * $user->delete();  // Deletes the row
   * ```
   */
  public function delete(): void {
    if (!isset($this->id)) {
      DataException::throw(msg: 'delete_no_id', more: [
        'claz' => static::class,
      ]);
    }
    $pdo = Database::get();
    $stmt = $pdo->prepare('DELETE FROM ' . static::tbl() . ' WHERE id = :id');
    if (!$stmt->execute(['id' => $this->id])) {
      DataException::throw(msg: 'delete_fail', more: [
        'claz' => static::class,
        'id' => $this->id,
      ]);
    }
    $this->id = null;
  }

  /**
   * Generate the CREATE TABLE DDL statement.
   * 
   * Reflects on model properties and generates SQL to create the corresponding table.
   * Automatically handles:
   * - Property type mapping to SQL types (int → INT, string → VARCHAR, etc)
   * - Attributes: #[Unique], #[Index], #[NotNull], #[DbDefault], #[CustomType]
   * - Primary key (auto-increment id field)
   * - Composite indexes
   * - ON UPDATE actions (e.g., timestamp auto-update)
   * 
   * Skips:
   * - Static properties
   * - Private properties (starting with _)
   * - Properties with hooks (PHP 8.4 hooks)
   * 
   * @param bool $force If true, includes DELETE before CREATE (no IF NOT EXISTS).
   *                    If false, uses CREATE TABLE IF NOT EXISTS.
   * @return string The complete CREATE TABLE statement.
   * @throws DataException For unsupported field types.
   * 
   * @example
   * ```php
   * echo User::ddl();
   * // CREATE TABLE IF NOT EXISTS app_User (
   * //   `id` INT AUTO_INCREMENT PRIMARY KEY,
   * //   `email` VARCHAR(255) UNIQUE NOT NULL,
   * //   `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
   * //   ...
   * // )
   * ```
   */
  public static function ddl($force = false): string {
    /* 7 = ReflectionProperty::{IS_PUBLIC|IS_PROTECTED|IS_PRIVATE} */
    $fields = (new ReflectionClass(static::class))->getProperties(7);
    $columns = [];
    $indexes = [];
    $uniqueMulti = [];
    foreach ($fields as $field) {
      if ($field->getHooks()) continue;

      $nam = $field->getName();
      if ($field->isStatic() || str_starts_with($nam, '_')) continue;
      $typ = $field->getType()?->getName();
      $entry = "`$nam`";
      $isPrimary = false;
      if ($atr = CustomType::of($field)) {
        $entry .= " {$atr->dbType}";
      } else if ($nam === 'id') {
        $entry .= ' INT AUTO_INCREMENT PRIMARY KEY';
        $isPrimary = true;
      } elseif ($typ === 'int') {
        $entry .= ' INT';
      } elseif ($typ === 'string') {
        $entry .= ' VARCHAR(255)';
      } elseif ($typ === 'float') {
        $entry .= ' FLOAT';
      } elseif ($typ === 'bool') {
        $entry .= ' TINYINT(1)';
      } elseif ($typ === DBDateTime::class) {
        $entry .= ' DATETIME';
      } elseif (is_subclass_of($typ, Obj::class) || method_exists($typ, 'marshall')) {
        $entry .= ' TEXT';
      } else {
        DataException::throw(msg: 'unsupported_field_type', more: [
          'claz' => static::class,
          'field' => $nam,
          'type' => $typ,
        ]);
      }

      if (Unique::of($field)) {
        $entry .= ' UNIQUE';
      }

      if ($atr = DBDefault::of($field)) {
        $entry .= " DEFAULT {$atr->value}";
      }

      if ($atr = OnUpdate::of($field)) {
        $entry .= " ON UPDATE {$atr->action}";
      }

      if (!($field->getType()?->allowsNull()) || NotNull::of($field) || $isPrimary) {
        $entry .= ' NOT NULL';
      } else {
        $entry .= ' DEFAULT NULL';
      }
      if ($atr = Index::of($field)) {
        $indexes[$atr->name][] = $nam;
      }
      if ($atr = UniqueMulti::of($field)) {
        $uniqueMulti[$atr->indexName][] = $nam;
      }
      $columns[] = $entry;
    }
    $columns = array_reverse($columns, true);
    foreach ($uniqueMulti as $iname => $cols) {
      $columns[] = "UNIQUE `$iname` (" . implode(', ', $cols) . ')';
    }
    foreach ($indexes as $iname => $cols) {
      $columns[] = "INDEX `$iname` (" . implode(', ', $cols) . ')';
    }
    $qry = ['CREATE TABLE'];
    if (!$force) $qry[] = ' IF NOT EXISTS';
    $qry[] = static::tbl();
    $qry[] = " (\n";
    $qry[] = implode(",\n", $columns);
    $qry[] = ')';
    return implode(' ', $qry);
  }

  /**
   * Generate foreign key constraint ALTER statements.
   * 
   * Finds all properties with #[Foreign] attributes and generates
   * ALTER TABLE statements to create the constraints.
   * 
   * Each foreign key:
   * - References the id column of the target Model's table
   * - Specifies ON DELETE and ON UPDATE actions
   * - Uses a hashed name to avoid conflicts
   * 
   * @param bool $force If true, includes DROP FOREIGN KEY statements first.
   * @return array<string> Array of ALTER TABLE statements.
   * 
   * @example
   * ```php
   * class Post extends Model {
   *   #[Foreign(User::class, DbThen::CASCADE, DbThen::CASCADE)]
   *   public int $user_id;
   * }
   * 
   * $ddl = Post::ddlDeps();
   * // Returns: [
   * //   "ALTER TABLE app_Post
   * //    ADD CONSTRAINT app_Post_ibfk_abc123
   * //    FOREIGN KEY (user_id) REFERENCES app_User(id)
   * //    ON DELETE CASCADE ON UPDATE CASCADE"
   * // ]
   * ```
   */
  public static function ddlDeps(bool $force = false): array {
    $deps = [];
    /* 7 = ReflectionProperty::{IS_PUBLIC|IS_PROTECTED|IS_PRIVATE} */
    $fields = (new ReflectionClass(static::class))->getProperties(7);
    foreach ($fields as $field) {
      if ($field->getHooks()) continue;
      $nam = $field->getName();
      if ($atr = Foreign::of($field)) {
        /** @var Foreign $atr */
        $deps[$nam] = $atr;
      }
    }
    // build mysql foreign key constraints
    $out = [];
    $tab_name = static::tbl();
    foreach ($deps as $dep => $atr) {
      $fk_nam = static::tbl() . '_ibfk_' . substr(sha1($dep . '-' . $atr->target), 0, 6);
      if ($force) {
        $out[] = "ALTER TABLE $tab_name DROP FOREIGN KEY IF EXISTS $fk_nam";
      }
      $targ_tab = ($atr->target)::tbl();
      $out[] = "ALTER TABLE $tab_name
        ADD CONSTRAINT $fk_nam
        FOREIGN KEY ($dep) REFERENCES $targ_tab(id)
        ON DELETE {$atr->onDelete->value}
        ON UPDATE {$atr->onUpdate->value}";
    }
    return $out;
  }

  /**
   * Create the database table for this model.
   * 
   * Executes the DDL statement to create the table. Optionally drops
   * the table first if it exists (force mode).
   * 
   * @param bool $force If true, drops table first (useful for schema resets).
   * @return void
   * @throws DataException For table creation failures.
   * 
   * @example
   * ```php
   * User::createTable();        // Creates table if not exists
   * User::createTable(true);    // Drops and recreates table
   * ```
   */
  public static function createTable($force = false): void {
    $pdo = Database::get();
    if ($force) {
      $pdo->query('DROP TABLE IF EXISTS ' . static::tbl());
    }
    $stmt = $pdo->prepare(static::ddl($force));
    if (!$stmt->execute()) {
      DataException::throw(msg: 'create_table_fail', more: [
        'claz' => static::class,
        'pdoErr' => $pdo->errorInfo(),
        'query' => $stmt->queryString,
      ]);
    }
  }

  /**
   * Create foreign key constraints for this model.
   * 
   * Executes ALTER TABLE statements to establish foreign key relationships
   * defined by #[Foreign] attributes. Optionally drops existing constraints first.
   * 
   * Must be called after createTable() if dependencies don't exist yet.
   * 
   * @param bool $force If true, drops constraints first.
   * @return void
   * @throws DataException For constraint creation failures.
   * 
   * @example
   * ```php
   * User::createTable();
   * Post::createTable();
   * Post::createDeps();  // Links posts to users
   * ```
   */
  public static function createDeps(bool $force = false): void {
    $pdo = Database::get();
    $deps = static::ddlDeps($force);
    foreach ($deps as $dep) {
      $stmt = $pdo->prepare($dep);
      if (!$stmt->execute()) {
        DataException::throw(msg: 'create_deps_fail', more: [
          'claz' => static::class,
          'pdoErr' => $pdo->errorInfo(),
          'query' => $stmt->queryString,
        ]);
      }
    }
  }

  /**
   * Generate a SHA256 hash of the current serialized state.
   * 
   * Used internally for dirty tracking: compare the hash at load time
   * with the hash after modifications to detect changes.
   * 
   * @return string SHA256 hash of sorted JSON representation.
   */
  protected function makeHash(): string {
    $fields = $this->__serialize();
    ksort($fields);
    return hash('sha256', json_encode($fields));
  }

  /**
   * Store this model instance in the session (Sex).
   * 
   * Persists the model to $_SESSION for retrieval across requests.
   * Model must be persisted to database first (have an ID and no pending changes).
   * 
   * Uses the model's class name as the session key (optionally suffixed with $key).
   * 
   * @param string|null $key Optional suffix for session key (useful for storing
   *                          multiple instances of the same model class).
   * @return static Returns $this for fluent interface.
   * @throws HTTPException If model is not persisted or has unsaved changes.
   * 
   * @example
   * ```php
   * $user = User::find(42);
   * $user->toSex();  // Stored in $_SESSION['User']
   * 
   * // Retrieve from another request:
   * $user = User::fromSex();
   * ```
   */
  public function toSex($key = null): static {
    if (!$this->id() || $this->isDirty()) {
      throw new HTTPException('model_must_be_persisted', more: [
        'id' => $this->id(),
        'dirty' => $this->isDirty(),
        '_hash' => $this->_hash,
        'myhash' => $this->makeHash(),
      ]);
    }
    global $SEX;
    $SEX->ensure()->put(static::class . ($key ?? ''), $this);
    return $this;
  }

  /**
   * Check if this model has been modified since it was loaded/saved.
   * 
   * Compares the current state hash with the hash saved at load time.
   * Returns false for new unsaved instances (no baseline hash).
   * 
   * @return bool True if model has changes, false if unchanged or new.
   */
  public function isDirty(): bool {
    if (!$this->_hash) return false;
    return $this->_hash !== $this->makeHash();
  }

  /**
   * Retrieve a model instance from the session (Sex).
   * 
   * Restores a model previously stored via toSex() from $_SESSION.
   * 
   * @param string|null $key Optional suffix for session key (must match toSex() key).
   * @return static The restored model instance.
   * @throws DataException If model not found in session.
   * 
   * @example
   * ```php
   * $user = User::find(42);
   * $user->toSex('current');
   * 
   * // Later, in another request:
   * $user = User::fromSex('current');
   * ```
   */
  public static function fromSex($key = null): static {
    global $SEX;
    $out = $SEX->ensure()->get(static::class . ($key ?? ''));
    if (!$out) {
      DataException::throw(msg: 'sex_model_not_found', more: [
        'claz' => static::class,
        'key' => $key,
      ]);
    }
    return $out;
  }
}

/**
 * DateTime serializer for database operations.
 * 
 * Formats DateTime as 'Y-m-d H:i:s' (MySQL DATETIME format) for seamless
 * serialization and deserialization with database timestamp columns.
 * 
 * Used for created_at and updated_at fields in Model instances.
 * 
 * @see SerializableDateTime
 * @see Model
 * @subpackage db
 */
class DBDateTime extends SerializableDateTime {
  /**
   * Get the DateTime format for database serialization.
   * 
   * @return string MySQL DATETIME format 'Y-m-d H:i:s'.
   */
  protected static function getFormat(): string {
    return 'Y-m-d H:i:s';
  }
}
