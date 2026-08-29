<?php

if (!class_exists('Database')) {
  http_response_code(404);
  exit;
}

$DB_HOST = '1.2.3.4';

if ($_SERVER['HTTP_HOST'] === 'localhost') {
  $DB_HOST = 'localhost';
  $debug = true;
}

Database::cfg(
  "mysql:host=$DB_HOST;dbname=MyDatabase",
  'MyUser',
  'MyPassword',
);
