<?php

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
  public bool $isAdmin;
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
  global $SEX;
  $SEX->destroy();
});

$APP->get('list', function () {
  User::requireAdmin();
  return array_map(
    fn(User $u) => $u->dto(),
    User::findMany()
  );
});

$APP->post('', function (UserData $data) {
  User::requireAdmin();
  return User::fromDto($data)->setLast()->save(forCreate: true)->dto();
});

$APP->put('{id:i}', function (int $id, UserData $data) {
  User::requireAdmin();
  return User::fromDto($data)->setLast()->save(idForUpdate: $id)->dto();
});

$APP->get('dashboard', function () {
  User::requireAdmin();
  return html('<html><body><h1>Admin Dashboard</h1></body></html>');
});
