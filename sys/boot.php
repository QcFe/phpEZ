<?php

/**
 * @package PHPEz
 */

/** 
 * 
 * PHPEz Framework Bootstrap and Autoloader.
 * 
 * This file is the entry point for the framework. It sets up all essential
 * components in the correct dependency order and configures the autoloader.
 * 
 * Initialization sequence:
 * 1. Registers PSR-4 autoloader for model classes (~/claz/ directory)
 * 2. Loads exception system (error handlers)
 * 3. Loads type system (Obj, Parsable, attributes)
 * 4. Loads HTTP routing (App, Api, HTTP enums)
 * 5. Loads ORM persistence (Database, Model)
 * 6. Loads session management (Sex)
 * 7. Loads application configuration
 * 
 * Usage (in index.php):
 * ```php
 * require_once('./sys/boot.php');
 * 
 * $APP = new App(__DIR__ . '/root/');
 * $APP->startup($_GET['__p'] ?? '');
 * ```
 * 
 * Directory structure:
 * ```
 * api/
 *   sys/           ← This directory
 *     boot.php     ← Executed first
 *     exceptions.php
 *     iface.php
 *     http.php
 *     db.php
 *     sex.php
 *   claz/          ← Model classes (auto-loaded)
 *   root/          ← Route handlers
 *   index.php      ← Entry point
 *   config.php     ← Configuration
 *   .htaccess      ← URL rewriting
 * ```
 * 
 * @see App
 * @see Database
 */

/**
 * Register PSR-4 autoloader for model classes.
 * 
 * Maps class names to files in the ~/claz/ directory using PSR-4 conventions.
 * Namespaces are converted to directory paths (e.g., User\Profile → User/Profile.php).
 * 
 * Throws HTTPException (500) if a class file cannot be found, preventing silent
 * failures and providing clear feedback for development.
 * 
 * @example
 * ```php
 * // File: api/claz/User.php
 * class User extends Model { ... }
 * 
 * // Usage anywhere:
 * $user = new User(['name' => 'Alice']);  // Auto-loaded from claz/User.php
 * 
 * // Namespaced classes:
 * // File: api/claz/Auth/Token.php
 * namespace Auth;
 * class Token { ... }
 * 
 * // Usage:
 * $token = new Auth\Token();  // Auto-loaded from claz/Auth/Token.php
 * ```
 * 
 * @internal Registered via spl_autoload_register().
 */
spl_autoload_register(function ($class) {
  global $_PHPEZ_BUNDLED_;
  $fpx = isset($_PHPEZ_BUNDLED_) ? '' : '/..';
  $file = __DIR__  . $fpx . '/claz/' . str_replace('\\', '/', $class) . '.php';
  if (file_exists($file)) {
    require_once($file);
  } else {
    HttpException::throw(500, 'invalid_claz', more: ['cls' => $class, 'fi' => rmbasepath($file)]);
  }
});

/**
 * Load the exception handling system.
 * 
 * Sets up error handlers, exception handlers, and shutdown handlers.
 * Must be loaded first to catch errors during bootstrap.
 * 
 * Defines:
 * - HTTPException, NotFoundException (exception classes)
 * - glb(), rmbasepath(), excClean() (utility functions)
 * - Global error/exception handlers (set_error_handler, etc)
 * - BASE_PATH constant
 * 
 * @see HTTPException
 */
require_once('exceptions.php');

/**
 * Load the type system and serialization layer.
 * 
 * Provides the foundation for data objects:
 * - Obj (base class for all data objects)
 * - Parsable (custom type interface)
 * - ObjAttribute, OmitEmpty, DoNotSerialize, DoNotDeserialize (metadata attributes)
 * - SerializableDateTime (date/time serialization)
 * 
 * @see Obj
 */
require_once('iface.php');

/**
 * Load the HTTP routing and API framework.
 * 
 * Provides request routing and API orchestration:
 * - HTTP enum (GET, POST, PUT, DELETE, REPORT)
 * - HTTPCode enum (200, 404, 500, etc)
 * - Get, BoolGet (query parameter accessors)
 * - Api (individual endpoint handlers)
 * - App (central router)
 * - final_json() (JSON response function)
 * 
 * @see App
 * @see Api
 */
require_once('http.php');

/**
 * Load the ORM persistence layer.
 * 
 * Provides database abstraction and model persistence:
 * - Database (connection manager, singleton PDO)
 * - Model (ORM base class)
 * - CachableModel (instance caching trait)
 * - Attributes: Unique, Index, CustomType, NotNull, DbDefault, OnUpdate, Foreign
 * - DBDateTime (MySQL DATETIME serializer)
 * - Exception types: DataException, DuplicateException
 * 
 * @see Model
 * @see Database
 */
require_once('db.php');

/**
 * Load the session management system.
 * 
 * Provides lazy-initialized, namespaced session access:
 * - Sex (session wrapper with fluent API)
 * - $SEX (global instance)
 * 
 * @see Sex
 */
require_once('sex.php');
