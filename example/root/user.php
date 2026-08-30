<?php

// This file is part of the example application demonstrating how to use the phpEZ framework. It defines the routes and handlers for user-related operations, including login, logout, registration, and user management. The code utilizes the User model and the Sex session management system to handle user authentication and session persistence. The routes are defined using the $APP object, which is an instance of the application router.

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

// Login page for demonstration purposes. In a real application, you would typically serve an HTML page or redirect to a login form.
// This way though you can also see how phpEZ can also serve and easily interpolate HTML content directly from a route handler.
// You can easily create more response types by implementing a new CustomResponse class.
$APP->get('login', function () {
  return html(<<<HTML
    <!doctype html><html><head><meta charset="utf-8"><title>Login</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 20px; }
      form { max-width: 300px; margin: auto; }
      label { display: block; margin-bottom: 5px; }
      input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin-bottom: 10px; }
      input[type="submit"] { width: 100%; padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
      input[type="submit"]:hover { background-color: #45a049; }
    </style>
    </head><body>
    <h1>Login</h1>
    <form method="POST" onsubmit="event.preventDefault(); fetch('login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ uname: document.getElementById('uname').value, pass: document.getElementById('pass').value }) }).then(response => { if (response.ok) { alert('Login successful!'); } else { alert('Login failed.'); } });">
      <label for="uname">Username:</label>
      <input type="text" id="uname" name="uname" required><br><br>
      <label for="pass">Password:</label>
      <input type="password" id="pass" name="pass" required><br><br>
      <input type="submit" value="Login">
    </form>
    </body></html>
  HTML);
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
