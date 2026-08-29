<?php

/**
 * PHPEz Application Entry Point.
 * 
 * This is the main index.php file for the API. All requests are routed here
 * via .htaccess URL rewriting. The file bootstraps the framework, initializes
 * the router, and handles the incoming request.
 * 
 * Request flow:
 * 1. .htaccess rewrites /path/to/resource to index.php?__p=path/to/resource
 * 2. This file loads the framework via boot.php
 * 3. Creates an App router instance
 * 4. Calls startup() with the request path
 * 5. startup() finds and loads route handlers
 * 6. Matching handler is executed
 * 7. Response is JSON-encoded and sent
 * 
 * Example requests:
 * ```
 * GET /api/users                    → handled by api/root/users.php
 * GET /api/users/42                 → handled by api/root/users/index.php
 * POST /api/users                   → handled by api/root/users.php (POST method)
 * GET /api/categories/tree          → handled by api/root/categories.php
 * ```
 * 
 * Directory structure:
 * ```
 * api/
 *   index.php           ← This file
 *   .htaccess           ← URL rewriting rules
 *   config.php          ← Configuration (DB, debug mode, etc)
 *   sys/
 *     boot.php          ← Framework bootstrap
 *     exceptions.php
 *     iface.php
 *     http.php
 *     db.php
 *     sex.php
 *   claz/               ← Model classes (auto-loaded)
 *     User.php
 *     Post.php
 *     Category.php
 *   root/               ← Route handlers
 *     index.php
 *     users.php
 *     users/
 *       index.php
 *       profile.php
 *     posts.php
 * ```
 * 
 * @see App::startup()
 * @see App::run()
 */

/**
 * Bootstrap the PHPEz framework.
 * 
 * Loads all framework components in dependency order:
 * - Exception system
 * - Type system (Obj, serialization)
 * - HTTP routing
 * - ORM (Database, Model)
 * - Session management
 * - Application configuration
 */
#require_once('./sys/boot.php'); // phpEZ development mode
require_once('phpez.php');

/**
 * Create the application router.
 * 
 * The App instance manages all API endpoints and coordinates request routing.
 * The root directory (__DIR__ . '/root/') is where route definition files are stored.
 */
$APP = new App(__DIR__ . '/root/');

/**
 * Start request processing.
 * 
 * Parses the request path from $_GET['__p'] (set by .htaccess rewrite),
 * traverses the route directory structure, loads matching route files,
 * and executes the appropriate handler.
 * 
 * The path is passed through directory traversal logic:
 * 1. For each path component, check if a corresponding .php file exists
 * 2. If yes, load that file (which registers routes via $APP)
 * 3. Continue traversing deeper or accumulate remaining path components
 * 4. Pass accumulated path to route matching
 * 5. Execute first matching route handler
 * 
 * Example traversal for GET /api/events/42/comments:
 * - Load api/root/index.php (registers global routes)
 * - Load api/root/events.php (registers /events routes)
 * - Load api/root/events/index.php (registers /events/* routes)
 * - Remaining path: [42, comments]
 * - Match against registered routes and execute handler
 * 
 * @throws NotFoundException If no matching route is found.
 * @throws HTTPException For other routing errors or handler exceptions.
 * 
 * @see App::startup()
 */
$APP->startup($_GET['__p'] ?? '');
