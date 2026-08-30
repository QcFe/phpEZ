<?php

// This file is part of the example application demonstrating how to use the PHPEz framework. It defines the routes and handlers for user-related operations, including login, logout, registration, and user management. The code utilizes the User model and the Sex session management system to handle user authentication and session persistence. The routes are defined using the $APP object, which is an instance of the application router.

class LoginData extends Obj {
  public string $uname;
  #[OmitEmpty]
  public ?string $pass = null;
}

class UserData extends LoginData {
  public string $email;
  public string $name;
  public string $surn;
  public ?int $id = null;
  #[OmitEmpty]
  public bool $isAdmin = false;
}

$APP->get('', function () {
  return User::me()->dto();
});

$APP->post('login', function (LoginData $data) {
  $usr = User::find($data->uname, 'uname');
  if (!$usr) {
    sleep(2);
    HTTPException::throw(401, 'invalid_login');
  }
  if (!$usr->verifyPw($data->pass)) {
    sleep(2);
    HTTPException::throw(401, 'invalid_login');
  }
  return $usr->setLast()->save()->toSex()->dto();
});

$APP->post('logout', function () {
  User::require();
  Sex::destroy();
});

/**
 * List all users.
 * Requires admin privileges.
 */
$APP->get('list', function () {
  User::requireAdmin();
  return array_map(
    fn(User $u) => $u->dto(),
    User::findMany()
  );
});

/**
 * Open registration mode.
 * If you want this endpoint to be only accessible to admins, you can use User::requireAdmin();
 */
$APP->post('', function (UserData $data) {
  $me = User::me();
  if ($me && !$me->isAdmin) {
    HTTPException::throw(403, 'already_logged_in');
  }
  if (!$data->pass) {
    HTTPException::throw(400, 'pass_required');
  }
  if (!$me) {
    $data->isAdmin = false; // self-registration must not grant admin
  }
  return User::fromDto($data)->setLast()->save(forCreate: true)->toSex()->dto();
});

$APP->put('{id:i}', function (int $id, UserData $data) {
  User::requireAdmin();
  return User::fromDto($data)->setLast()->save(idForUpdate: $id)->dto();
});

$APP->get('dashboard', function () {
  User::requireAdmin();
  return html('<html><body><h1>Admin Dashboard</h1></body></html>');
});
