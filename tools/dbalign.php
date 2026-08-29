<?php

/**
 * Dev tool: compares Model classes declared in claz/ against the live
 * database schema and reports misalignments (missing tables/columns, extra
 * columns, type or nullability drift). No migrations, no schema history -
 * just a diff between "what the code says" and "what the DB has".
 *
 * To use it, copy this file into your project's root/
 * directory, e.g. root/dbalign.php, so it ends up next to claz/. It then
 * exposes a single route:
 *
 *   GET /dbalign/misalignments
 */

/**
 * Finds every Model subclass declared under claz/.
 *
 * Class names are extracted with a plain regex (no PHP parsing), then each
 * candidate is checked through the framework's autoloader so inheritance
 * through abstract base models is resolved correctly. Abstract classes are
 * skipped since they don't map to a table of their own.
 *
 * @param string $clazDir Absolute path to the claz/ directory.
 * @return array<string> Fully-qualified concrete Model class names.
 */
function dbalign_find_models(string $clazDir): array {
  $out = [];
  if (!is_dir($clazDir)) return $out;
  $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clazDir, FilesystemIterator::SKIP_DOTS));
  foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $ns = preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $src, $m) ? $m[1] . '\\' : '';
    if (!preg_match_all('/^\s*(?:abstract\s+|final\s+)*class\s+(\w+)/m', $src, $matches)) continue;
    foreach ($matches[1] as $name) {
      $fqcn = $ns . $name;
      if (!class_exists($fqcn)) continue;
      $rc = new ReflectionClass($fqcn);
      if (!$rc->isAbstract() && $rc->isSubclassOf(Model::class)) {
        $out[] = $fqcn;
      }
    }
  }
  return $out;
}

/**
 * Derives the columns a Model class expects to find in the database.
 *
 * Mirrors the field-selection rules used by Model::ddl() (skip static and
 * `_`-prefixed properties, skip hooked properties) but only tracks the base
 * SQL type family and nullability, which is enough to spot drift without
 * duplicating full DDL generation.
 *
 * @param string $class Fully-qualified Model class name.
 * @return array<string,array{sqlType:?string,nullable:bool}>
 */
function dbalign_expected_columns(string $class): array {
  /* 7 = ReflectionProperty::{IS_PUBLIC|IS_PROTECTED|IS_PRIVATE} */
  $fields = (new ReflectionClass($class))->getProperties(7);
  $out = [];
  foreach ($fields as $field) {
    if ($field->getHooks()) continue;
    $name = $field->getName();
    if ($field->isStatic() || str_starts_with($name, '_')) continue;

    if (CustomType::of($field)) {
      $sqlType = null; // custom SQL, don't attempt to compare
    } elseif ($name === 'id') {
      $sqlType = 'int';
    } else {
      $sqlType = match ($field->getType()?->getName()) {
        'int' => 'int',
        'string' => 'varchar',
        'float' => 'float',
        'bool' => 'tinyint',
        DBDateTime::class => 'datetime',
        default => 'text',
      };
    }

    $nullable = $name !== 'id' && ($field->getType()?->allowsNull() ?? true) && !NotNull::of($field);
    $out[$name] = ['sqlType' => $sqlType, 'nullable' => $nullable];
  }
  return $out;
}

/**
 * Derives the indexes a Model class expects to find on its table, keyed by
 * index name (a bare #[Unique] uses the column name, matching MySQL's default
 * naming for an inline UNIQUE column constraint).
 *
 * @param string $class Fully-qualified Model class name.
 * @return array<string,array{unique:bool,columns:array<string>}>
 */
function dbalign_expected_indexes(string $class): array {
  /* 7 = ReflectionProperty::{IS_PUBLIC|IS_PROTECTED|IS_PRIVATE} */
  $fields = (new ReflectionClass($class))->getProperties(7);
  $out = [];
  foreach ($fields as $field) {
    if ($field->getHooks()) continue;
    $name = $field->getName();
    if ($field->isStatic() || str_starts_with($name, '_')) continue;

    if (Unique::of($field)) {
      $out[$name] = ['unique' => true, 'columns' => [$name]];
    }
    if ($atr = Index::of($field)) {
      $out[$atr->name]['unique'] ??= false;
      $out[$atr->name]['columns'][] = $name;
    }
    if ($atr = UniqueMulti::of($field)) {
      $out[$atr->indexName]['unique'] = true;
      $out[$atr->indexName]['columns'][] = $name;
    }
  }
  return $out;
}

/**
 * Reads the actual indexes of a table from information_schema (PRIMARY excluded).
 *
 * @param string $table Table name (already includes the configured prefix).
 * @return array<string,array{unique:bool,columns:array<string>}>
 */
function dbalign_actual_indexes(string $table): array {
  $pdo = Database::get();
  $stmt = $pdo->prepare("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = :t AND INDEX_NAME != 'PRIMARY' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
  $stmt->execute(['t' => $table]);
  $out = [];
  foreach ($stmt->fetchAll() as $row) {
    $idx = $row['INDEX_NAME'];
    $out[$idx]['unique'] = !((bool)$row['NON_UNIQUE']);
    $out[$idx]['columns'][] = $row['COLUMN_NAME'];
  }
  return $out;
}

/**
 * Extracts the DDL fragment for a single column out of Model::ddl().
 *
 * Reuses the model's own DDL generation instead of re-deriving type and
 * attribute rules, so the fragment used to fix a column is always exactly
 * what the model dictates.
 *
 * @param string $class Fully-qualified Model class name.
 * @param string $column Column name.
 * @return string|null The full `` `col` TYPE ... `` definition, or null if not found.
 */
function dbalign_column_ddl(string $class, string $column): ?string {
  $ddl = $class::ddl();
  $quoted = preg_quote($column, '/');
  if (!preg_match("/`{$quoted}`\\s+(.+?)(?=,\\n|\\s*\\)\\s*\$)/s", $ddl, $m)) {
    return null;
  }
  return "`{$column}` " . trim($m[1]);
}

/**
 * Reads the actual columns of a table from information_schema.
 *
 * @param string $table Table name (already includes the configured prefix).
 * @return array<string,array{sqlType:string,nullable:bool}>|null Null if the table doesn't exist.
 */
function dbalign_actual_columns(string $table): ?array {
  $pdo = Database::get();
  $stmt = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = :t');
  $stmt->execute(['t' => $table]);
  $rows = $stmt->fetchAll();
  if (!$rows) {
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = :t');
    $stmt->execute(['t' => $table]);
    if (!$stmt->fetch()) return null;
  }
  $out = [];
  foreach ($rows as $row) {
    $out[$row['COLUMN_NAME']] = ['sqlType' => strtolower($row['DATA_TYPE']), 'nullable' => $row['IS_NULLABLE'] === 'YES'];
  }
  return $out;
}

/**
 * Compares a Model's expected columns and indexes against the live table.
 *
 * @param string $class Fully-qualified Model class name.
 * @return array<int,array<string,mixed>> Misalignment descriptors, empty if aligned.
 */
function dbalign_diff(string $class): array {
  $actual = dbalign_actual_columns($class::tbl());
  if ($actual === null) {
    return [['type' => 'missing_table', 'table' => $class::tbl()]];
  }

  $expected = dbalign_expected_columns($class);
  $issues = [];
  foreach ($expected as $name => $exp) {
    $base_issue = ['type' => 'column_issue', 'table' => $class::tbl(), 'column' => $name];
    if (!isset($actual[$name])) {
      $issues[] = [...$base_issue, 'type' => 'missing_column'];
      continue;
    }
    $act = $actual[$name];
    if ($exp['sqlType'] !== null && $exp['sqlType'] !== $act['sqlType']) {
      $issues[] = [...$base_issue, 'type' => 'type_mismatch', 'expected' => $exp['sqlType'], 'actual' => $act['sqlType']];
    }
    if ($exp['nullable'] !== $act['nullable']) {
      $issues[] = [...$base_issue, 'type' => 'nullable_mismatch', 'expected' => $exp['nullable'], 'actual' => $act['nullable']];
    }
  }
  foreach ($actual as $name => $_) {
    $base_issue = ['type' => 'column_issue', 'table' => $class::tbl(), 'column' => $name];
    if (!isset($expected[$name])) {
      $issues[] = [...$base_issue, 'type' => 'extra_column'];
    }
  }

  $actualIdx = dbalign_actual_indexes($class::tbl());
  foreach (dbalign_expected_indexes($class) as $idxName => $idx) {
    if (!isset($actualIdx[$idxName])) {
      $issues[] = [
        'type' => 'missing_index',
        'table' => $class::tbl(),
        'column' => $idx['columns'][0],
        'index' => $idxName,
        'unique' => $idx['unique'],
        'columns' => $idx['columns'],
      ];
    }
  }
  return $issues;
}

function array_to_hidden(array $data): string {
  return implode("\n", array_map(fn($k, $v) => "<input type=\"hidden\" name=\"$k\" value=\"" . htmlspecialchars($v, ENT_QUOTES) . "\">", array_keys($data), $data));
}

$APP->get('misalignments', function () {
  $clazDir = dirname(__DIR__) . '/claz';
  $out = [];
  foreach (dbalign_find_models($clazDir) as $class) {
    $issues = dbalign_diff($class);
    if ($issues) {
      $out[$class] = $issues;
    }
  }

  if (!count($out)) {
    return html('<html><body><h1>DB Alignment Report</h1><p>No misalignments found.</p></body></html>');
  }
  $cnt = count($out);
  // Only these are handled by the 'fix' route; extra_column needs manual/destructive action, missing_table has its own 'create_table' route.
  $fixable = ['missing_column', 'type_mismatch', 'nullable_mismatch', 'missing_index'];
  $tbody = implode('', array_map(fn($class, $issues) => implode('', array_map(function ($issue) use ($class, $fixable) {
    $col = $issue['column'] ?? '(whole table)';
    $btn = '';
    if ($issue['type'] === 'missing_table') {
      $fields = array_to_hidden(['claz' => $class]);
      $btn = "<form method=\"post\" action=\"create_table\" style=\"margin:0\">$fields<button type=\"submit\">Create table</button></form>";
    } elseif (in_array($issue['type'], $fixable, true)) {
      $fields = array_to_hidden(['claz' => $class, 'field' => $col, 'issue' => $issue['type']]);
      $btn = "<form method=\"post\" action=\"fix\" style=\"margin:0\">$fields<button type=\"submit\">Fix</button></form>";
    }
    return "<tr><td>$class</td><td>{$issue['table']}</td><td>$col</td><td>{$issue['type']}</td><td>" . json_encode($issue) . "</td><td>$btn</td></tr>";
  }, $issues)), array_keys($out), $out));

  return html(<<<HTML
    <html>
      <head>
        <title>DB Alignment Report</title>
        <style>
          body { font-family: sans-serif; }
          h1 { font-size: 1.5em; }
          table { border-collapse: collapse; margin-bottom: 2em; }
          th, td { border: 1px solid #ccc; padding: 0.5em; }
          th { background-color: #eee; }
        </style>
      </head>
      <body>
        <h1>DB Alignment Report</h1>
        <p>Found <strong>{$cnt}</strong> misaligned model(s).</p>
        <table>
          <thead>
            <tr><th>Model Class</th><th>Table</th><th>Column</th><th>Issue Type</th><th>Details</th><th>Fix</th></tr>
          </thead>
          <tbody>
            {$tbody}
          </tbody>
        </table>
      </body>
    </html>
  HTML);
});

$APP->post('fix', function (Post $claz, Post $field, Post $issue, Post $confirm) {
  $cls = $claz->v() ?? HTTPException::throw(400, 'missing_class');
  $fld = $field->v() ?? HTTPException::throw(400, 'missing_field');
  $iss = $issue->v() ?? HTTPException::throw(400, 'missing_issue');
  $dryRun = $confirm->v() !== 'true';

  if (!class_exists($cls) || !(new ReflectionClass($cls))->isSubclassOf(Model::class)) {
    HTTPException::throw(400, 'invalid_class');
  }

  // scoped to a single issue: a column can carry more than one (e.g. type + nullable mismatch)
  $issues = array_filter(dbalign_diff($cls), fn($i) => ($i['column'] ?? null) === $fld && $i['type'] === $iss);
  if (!$issues) {
    HTTPException::throw(404, 'no_fixable_issue');
  }

  $tbl = $cls::tbl();
  $stmts = [];
  foreach ($issues as $i) {
    $stmts[] = match ($i['type']) {
      'missing_column' => "ALTER TABLE $tbl ADD COLUMN " . dbalign_column_ddl($cls, $fld),
      'type_mismatch', 'nullable_mismatch' => "ALTER TABLE $tbl MODIFY COLUMN " . dbalign_column_ddl($cls, $fld),
      'missing_index' => "ALTER TABLE $tbl ADD " . ($i['unique'] ? 'UNIQUE' : 'INDEX') . " `{$i['index']}` (" . implode(', ', array_map(fn($c) => "`$c`", $i['columns'])) . ')',
      // extra_column is destructive, not auto-fixed here
      default => null,
    };
  }

  if ($dryRun) {
    return html('<html><body><h1>DB Alignment Fix Preview</h1><p>Dry run mode, no changes applied.</p><pre>' . htmlspecialchars(implode(";\n", array_filter($stmts))) . "</pre><form method=\"post\" action=\"fix\">\n" . array_to_hidden(['claz' => $cls, 'field' => $fld, 'issue' => $iss, 'confirm' => 'true']) . "<button type=\"submit\">Apply Fix</button></form></body></html>");
  }

  $pdo = Database::get();
  foreach (array_unique(array_filter($stmts)) as $sql) {
    try {
      $pdo->exec($sql);
    } catch (\PDOException $e) {
      DataException::throw(msg: 'fix_failed', parent: $e, more: ['sql' => $sql]);
    }
  }

  return redirect('misalignments');
});

$APP->post('create_table', function (Post $claz, Post $confirm) {
  $cls = $claz->v() ?? HTTPException::throw(400, 'missing_class');
  $dryRun = $confirm->v() !== 'true';

  if (!class_exists($cls) || !(new ReflectionClass($cls))->isSubclassOf(Model::class)) {
    HTTPException::throw(400, 'invalid_class');
  }

  if (!array_filter(dbalign_diff($cls), fn($i) => $i['type'] === 'missing_table')) {
    HTTPException::throw(404, 'no_fixable_issue');
  }

  $sql = $cls::ddl();

  if ($dryRun) {
    return html('<html><body><h1>DB Alignment Create Table Preview</h1><p>Dry run mode, no changes applied.</p><pre>' . htmlspecialchars($sql) . "</pre><form method=\"post\" action=\"create_table\">\n" . array_to_hidden(['claz' => $cls, 'confirm' => 'true']) . "<button type=\"submit\">Create Table</button></form></body></html>");
  }

  try {
    Database::get()->exec($sql);
  } catch (\PDOException $e) {
    DataException::throw(msg: 'create_table_failed', parent: $e, more: ['sql' => $sql]);
  }

  return redirect('misalignments');
});

