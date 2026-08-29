# PHPEz Framework

> A lean, elegant, zero-boilerplate PHP framework for building REST APIs.

📖 [API documentation](https://qcfe.github.io/phpEZ/latest/)

## Why PHPEz?

Tired of massive frameworks with thousands of files, confusing conventions, and tons of boilerplate? PHPEz eliminates all that noise through **smart design patterns** and **automatic reflection-based code generation**.

### The Problem with Existing Frameworks

- **Laravel, Symfony**: 10,000+ files, complex configuration, steep learning curve
- **Manual serialization**: Repetitive mappers and validators
- **Migration hell**: Database schema spread across files
- **Routing confusion**: Decorators, annotations, config arrays

### The PHPEz Solution

PHPEz uses **modern PHP features** (type hints, attributes, enums, readonly properties) to generate everything automatically:

- ✅ **Zero serialization boilerplate** - Reflection generates JSON serialization
- ✅ **Zero mapper boilerplate** - Type hints auto-convert nested objects
- ✅ **Schema in code** - Model properties define database schema
- ✅ **Smart routing** - Closures with type hints = automatic parameter injection
- ✅ **Minimal files** - Just 6 files for the entire framework

## Architecture Overview

### Core Components

```
sys/
├── boot.php              # Framework bootstrap & autoloader
├── exceptions.php        # Error handling system
├── iface.php            # Type system & serialization
├── http.php             # Routing & API layer
├── db.php               # ORM & persistence
└── sex.php              # Session management
```

### Request Flow

```
┌─────────────────┐
│ .htaccess       │ Rewrites /api/path to index.php?__p=path
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ index.php       │ Calls App::startup()
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ App::startup()  │ Traverses directories, smartly pinpoints and loads a single route file
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Api::run()      │ Matches route pattern, injects parameters
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Handler closure │ User code executes
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ final_json()    │ Serializes & returns JSON response
└─────────────────┘
```

## Quick Start

### 1. Create a Model

```php
<?php
// api/claz/User.php

class User extends Model {
  public string $email;

  #[Unique]
  public string $username;

  #[DoNotSerialize]
  public string $password_hash;

  public function verifyPw(?string $pw): bool {
    return some_password_verify($pw ?? '', $this->password_hash);
  }
}
```

### 2. Create Database Table

```php
<?php
// One-time setup
User::createTable();
```

### 3. Create API Endpoint

```php
<?php
// api/root/user.php

class LoginData extends Obj {
  public string $uname;
  #[OmitEmpty]
  public ?string $pass;
}

class UserData extends LoginData {
  public string $email;
  public string $name;
  public string $surn;
  public ?int $id;
}

// GET /user - Get current logged-in user
$APP->get('', function () {
  return User::me()->dto();
});

// POST /user/login - Login user
$APP->post('login', function (LoginData $data) {
  $usr = User::find($data->uname, 'uname');
  if (!$usr) {
    sleep(2);  // Rate limiting
    HTTPException::throw(401, 'invalid_login');
  }
  if (!$usr->verifyPw($data->pass)) {
    sleep(2);
    HTTPException::throw(401, 'invalid_login');
  }
  return $usr->toSex()->dto();
});

// POST /user/logout - Logout user
$APP->post('logout', function () {
  User::require();
  global $SEX;
  $SEX->destroy();
});
```

### 4. Make Requests

```bash
# Login
curl -X POST http://localhost/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"uname":"alice","pass":"password123"}'

# Register
curl -X POST http://localhost/api/user/register \
  -H "Content-Type: application/json" \
  -d '{"uname":"alice","pass":"password123","email":"alice@example.com","name":"Alice","surn":"Smith","id":null}'

# Get current user
curl http://localhost/api/user/

# Logout
curl -X POST http://localhost/api/user/logout
```

## Core Features

### 1. Automatic Serialization (iface.php)

No mappers needed. Just extend `Obj` and use type hints:

```php
class LoginData extends Obj {
  public string $uname;
  #[OmitEmpty]
  public ?string $pass;
}

class UserData extends LoginData {
  public string $email;
  public string $name;
  public string $surn;
  public ?int $id;
}

// Automatically deserializes JSON from request body
$APP->post('login', function(LoginData $data) {
  // $data is already deserialized from JSON
  return $data;  // Automatically serializes back to JSON
});

// Automatically serializes via .dto() method
$user = User::find(1);
return $user->dto();  // Array converted to JSON response
```

**Type support:**

- Primitives: `string`, `int`, `bool`, `float`
- Nested objects: Any `Obj` subclass
- Custom types: Implement `Parsable` interface
- Dates: Use `DBDateTime` or `JSONDateTime`
- DTO conversion: Use `.dto()` to get array, `.fromDto($obj)` to load

### 2. Smart Routing (http.php)

Type hints automatically inject parameters:

```php
// Routes use relative paths from file location
// File: api/root/user.php → Routes become /user/{action}

// GET /user - Get current user
$APP->get('', function() {
  return User::me()->dto();
});

// POST /user/login - Login user (auto-deserialize JSON body)
$APP->post('login', function(LoginData $data) {
  $usr = User::find($data->uname, 'uname');
  if (!$usr->verifyPw($data->pass)) {
    HTTPException::throw(401, 'invalid_login');
  }
  return $usr->setLast()->save()->toSex()->dto();
});

// Query parameters
$APP->get('search', function(Get $q) {
  $query = $q->v('');  // Get $_GET['q'] with default
});

// Boolean query parameters
$APP->get('export', function(BoolGet $csv) {
  if ($csv->trueish()) {  // ?csv=1 or ?csv=yes
    // CSV export
  }
});

// Type-hinted dependencies
$APP->post('verify', function(VerifyData $body, Database $db) {
  // $body is deserialized JSON
  // $db is injected from container
});
```

### 3. ORM Persistence (db.php)

Define schema as model properties:

```php
class User extends Model {
  public string $uname;
  public string $pass;

  #[Unique]
  public string $email;

  public string $name;
  public string $surn;

  #[Index('last_login')]
  public ?DBDateTime $last_login = null;

  #[DbDefault('CURRENT_TIMESTAMP')]
  public DBDateTime $created_at;
}

// Generate and create table
User::createTable();
User::createDeps();  // Foreign keys

// CRUD operations
$user = new User();
$user->uname = 'alice';
$user->email = 'alice@example.com';
$user->save(forCreate: true);  // INSERT

$user->name = 'Alice';
$user->save();  // UPDATE

$user = User::find(42);           // Find by ID
$users = User::findMany('name LIKE :name', ['name' => 'Alice%']);

$user->delete();

// DTO conversion for API responses
$dto = $user->dto();  // Converts to array for JSON

// Load from DTO
$user = (new User())->fromDto($data)->save();

// Method chaining
$user->setLast()->save()->toSex()->dto();
```

**Features:**

- Auto-increment primary key `id`
- Automatic timestamps (`created_at`, `updated_at`)
- Type → SQL mapping (int → INT, string → VARCHAR, etc)
- Indexes and uniqueness constraints
- Foreign keys with cascade/restrict behavior
- Dirty tracking (`isDirty()`)
- Session persistence (`toSex()`, `fromSex()`)
- DTO serialization (`.dto()`, `.fromDto()`)

### 4. Session Management (sex.php)

Lazy-initialized, namespaced sessions:

```php
global $SEX;

// Persist model to session
$user->toSex();

// Store anything
$SEX->put('current_user', $user);
$SEX->put('auth_token', $token);

// Retrieve
$user = $SEX->get('current_user');

// Fluent API
$SEX->ensure()
    ->put('foo', 'bar')
    ->put('baz', 'qux');

// Retrieve user or throw 401
User::require();

// Cleanup
$SEX->destroy();
```

### 5. Error Handling (exceptions.php)

Unified error responses:

```php
// HTTPException with metadata
HTTPException::throw(
  code: 401,
  msg: 'invalid_login',
  more: ['attempt' => 3]
);

// NotFoundException for 404
NotFoundException::throw(msg: 'user_not_found', more: ['id' => $id]);

// DuplicateException for constraint violations (400 status)
DuplicateException::throw(msg: 'email_already_exists');
```

Response format:

```json
{
  "success": false,
  "error": "invalid_login",
  "type": "HTTPException",
  "dbg": {
    "more": {
      "attempt": 3
    },
    "trx": [...]
  }
}
```

## Configuration

Edit `config.php`:

```php
<?php

// Debug mode (shows full traces in error responses)
$debug = $_ENV['APP_DEBUG'] ?? false;

// Database configuration
Database::cfg(
  $_ENV['DB_DSN'],           // e.g., mysql:host=localhost;dbname=myapp
  $_ENV['DB_USER'],          // Database user
  $_ENV['DB_PASS'],          // Database password
  $_ENV['DB_PREFIX'] ?? ''   // Optional table prefix
);
```

## Directory Structure

```
api/
├── index.php              # Entry point
├── .htaccess              # URL rewriting
├── config.php             # Configuration
│
├── sys/                   # Framework core
│   ├── boot.php
│   ├── exceptions.php
│   ├── iface.php
│   ├── http.php
│   ├── db.php
│   └── sex.php
│
├── claz/                  # Model classes (auto-loaded)
│   ├── User.php
│   ├── Post.php
│   ├── Category.php
│   └── ...
│
└── root/                  # Route handlers
    ├── index.php          # Global routes
    ├── users.php          # /users routes
    ├── users/
    │   ├── index.php      # /users/* routes
    │   └── profile.php    # /users/profile routes
    ├── posts.php
    └── ...
```

## File Reference

### boot.php

Bootstraps the framework and sets up autoloading.

**Registers:**

- PSR-4 autoloader for classes in `claz/`
- Exception handlers (error, exception, shutdown)
- All framework components in dependency order
- Application configuration

### exceptions.php

Error handling and HTTP exception system.

**Provides:**

- `HTTPException` - Base API exception
- `NotFoundException` - HTTP 404
- Exception handlers (converts errors to exceptions)
- Error formatting for JSON responses
- Path sanitization (`rmbasepath()`)

### iface.php

Type system and automatic serialization.

**Provides:**

- `Obj` - Base class for all data objects
- `Parsable` - Interface for custom types
- `SerializableDateTime` - Base for DateTime serialization
- Attributes: `OmitEmpty`, `DoNotSerialize`, `DoNotDeserialize`
- Reflection-based serialization/deserialization

### http.php

HTTP routing and API orchestration.

**Provides:**

- `HTTP` enum - HTTP methods (GET, POST, PUT, DELETE, REPORT)
- `HTTPCode` enum - Status codes
- `Get`, `BoolGet` - Query parameter accessors
- `Api` - Individual endpoint handler
- `App` - Central router
- `final_json()` - JSON response function

### db.php

ORM and database persistence.

**Provides:**

- `Database` - Connection manager (singleton PDO)
- `Model` - ORM base class with CRUD
- `CachableModel` - Instance caching trait
- Attributes: `Unique`, `Index`, `NotNull`, `DbDefault`, `OnUpdate`, `Foreign`, `CustomType`
- `DBDateTime` - MySQL datetime serializer
- `DataException`, `DuplicateException`

### sex.php

Session management ("SessioN eXtensions").

**Provides:**

- `Sex` - Lazy-initialized session wrapper
- `$SEX` - Global instance
- Namespaced session storage
- Fluent API for method chaining

## Examples

### Complete User Registration Flow

```php
<?php
// api/claz/User.php

class User extends Model {
  #[Unique]
  public string $email;

  #[Unique]
  public string $username;

  #[DoNotSerialize]
  public string $password_hash;
}

// api/root/users.php

$APP->post('/register', function(RegisterRequest $body) {
  // Validate uniqueness
  if (User::find($body->email, 'email')) {
    DuplicateException::throw(msg: 'email_already_exists');
  }

  // Create and persist
  $user = new User();
  $user->email = $body->email;
  $user->username = $body->username;
  $user->password_hash = password_hash($body->password, PASSWORD_DEFAULT);
  $user->save(forCreate: true);

  // Store in session
  $user->toSex('current_user');

  return ['success' => true, 'user_id' => $user->id()];
});

// api/root/login.php

$APP->post('/login', function(LoginRequest $body) {
  $user = User::find($body->email, 'email');

  if (!$user || !password_verify($body->password, $user->password_hash)) {
    HTTPException::throw(code: 401, msg: 'invalid_credentials');
  }

  $user->toSex('current_user');

  return ['success' => true, 'user_id' => $user->id()];
});

// api/root/me.php

$APP->get('/me', function() {
  global $SEX;
  $user = $SEX->get('current_user');

  if (!$user) {
    HTTPException::throw(code: 401, msg: 'not_authenticated');
  }

  return $user;
});
```

### Complex Query with Relationships

```php
<?php
// api/claz/Post.php

class Post extends Model {
  public string $title;
  public string $content;

  #[Foreign(User::class, DbThen::CASCADE)]
  public int $user_id;

  public User $author {
    get => User::find($this->user_id) ?? throw new DataException('no_author');
  }
}

// api/root/posts.php

$APP->get('/posts', function(Get $status, Get $limit) {
  $cond = 'status = :status';
  $params = ['status' => $status->v('published')];

  $posts = Post::findMany($cond, $params);

  return $posts;
});

$APP->get('/users/{id:i}/posts', function(int $id) {
  $user = User::find($id);

  return Post::findMany('user_id = :uid', ['uid' => $user->id()]);
});
```

## Tips & Best Practices

### 1. Use Readonly Properties for Timestamps

```php
#[DoNotSerialize]
#[NotNull]
#[DbDefault('CURRENT_TIMESTAMP')]
public protected(set) ?DBDateTime $created_at = null;
```

The `protected(set)` prevents accidental modification while allowing database initialization.

### 2. Separate Request/Response DTOs

```php
class CreatePostRequest extends Obj {
  public string $title;
  public string $content;
}

class PostResponse extends Obj {
  public int $id;
  public string $title;
  public string $content;
  public DBDateTime $created_at;
}
```

### 3. Use Attributes for Validation Hints

```php
class BlogPost extends Model {
  #[NotNull]  // Explicitly required
  public string $title;

  #[OmitEmpty]  // Optional, omitted from serialization if unset
  public ?string $excerpt = null;
}
```

### 4. Implement beforeSave() for Business Logic

```php
class User extends Model {
  public function beforeSave() {
    // Normalize email
    $this->email = strtolower(trim($this->email));

    // Generate slug from username
    $this->slug = strtolower(str_replace(' ', '-', $this->username));
  }
}
```

### 5. Use CachableModel for Frequently Fetched Records

```php
class User extends Model {
  use CachableModel;

  public static function find(string $id_or_val, string $field = 'id'): ?static {
    // ... find implementation
  }
}

// Usage:
$user1 = User::findById(42);  // Hits database
$user2 = User::findById(42);  // Returns cached instance
```

## Performance Notes

- **Single database connection**: PDO singleton, persistent connections
- **Instance caching**: CachableModel reduces redundant queries
- **Lazy session initialization**: Sessions only start when accessed
- **Reflection caching**: PHP caches reflection results
- **No ORMs overhead**: Direct parameterized queries for complex logic

## Security Features

- **SQL injection prevention**: Parameterized queries throughout
- **Type validation**: Type hints enforced during deserialization
- **Error sanitization**: File paths hidden in production (`rmbasepath`)
- **Path traversal protection**: Route startup validates path components
- **Session namespacing**: Prevents conflicts with other data

## Troubleshooting

### "Class not found" errors

- Check the class file exists in `api/claz/`
- Verify namespace matches directory structure
- Check for typos in class name

### Routes not matching

- Verify route is registered in correct file
- Check path pattern syntax: `{id:i}` for int, `{slug:s}` for string
- Routes are matched in order, first match wins

### Database connection errors

- Verify `Database::cfg()` called in `config.php`
- Check database credentials in environment variables
- Ensure database exists and user has permissions

### "Headers already sent" errors

- Use `$SEX->ensure()` instead of directly calling `session_start()`
- PHPEz handles lazy session initialization

## Contributing

PHPEz is designed to be minimal and focused. Before adding features, consider:

- Does it increase file count significantly?
- Could the same result be achieved with a simpler approach?
- Is it solving a real problem, or adding theoretical flexibility?

## License

PHPEz by QcFe - Minimal, elegant, PHP framework for REST APIs.
