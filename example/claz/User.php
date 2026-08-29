<?php


class User extends Model {
  use SoftDelete;

  protected static string $defHashFn = 'sha256';
  #[Unique]
  public string $uname;
  #[Unique]
  public string $email;
  #[OmitEmpty]
  protected string $hash;

  public DBDateTime $lastD;
  public string $lastIP;

  public string $name;
  public string $surn;

  public bool $isAdmin = false;

  protected function pwReqCheck(string $pw) {
    if (strlen($pw) < 9) {
      throw new HTTPException('pw_too_short', 400);
    }
  }

  public function setPw(string $pw, string | null $hashFn = null): static {
    static::pwReqCheck($pw);
    $hashFn = $hashFn ?? static::$defHashFn;
    $salt = bin2hex(random_bytes(16));
    $hash = hash($hashFn, $salt . $pw);
    $this->hash = "$hashFn:$hash:$salt";
    return $this;
  }

  public function verifyPw(string $pw): bool {
    if (empty($this->hash)) {
      HTTPException::throw(500, 'user_no_pw', more: ['uname' => $this->uname]);
    }
    [$hashFn, $hash, $salt] = explode(':', $this->hash);
    $computedHash = hash($hashFn, $salt . $pw);
    return hash_equals($computedHash, $hash);
  }

  public function setLast(): static {
    $this->lastD = new DBDateTime();
    $this->lastIP = HTTPSrv::remote_addr();
    return $this;
  }

  public function dto(): UserData {
    $out = new UserData();
    $out->uname = $this->uname;
    $out->email = $this->email;
    $out->name = $this->name;
    $out->surn = $this->surn;
    $out->id = $this->id;
    $out->isAdmin = $this->isAdmin;
    return $out;
  }

  public static function fromDto(UserData $data): static {
    $usr = new static();
    $usr->uname = strtolower($data->uname);
    $usr->email = strtolower($data->email);
    $usr->name = $data->name;
    $usr->surn = $data->surn;
    if ($data->id !== null) {
      $usr->id = $data->id;
    }
    if ($data->pass) {
      $usr->setPw($data->pass);
    }
    $usr->isAdmin = $data->isAdmin;
    return $usr;
  }

  public static function me() {
    try {
      return static::fromSex();
    } catch (\Throwable $e) {
      throw new \HTTPException('not_logged_in', 401, $e);
    }
  }

  public static function require() {
    self::me();
  }

  public static function requireAdmin() {
    $u = self::me();
    if (!$u->isAdmin) {
      throw new HTTPException('unauthorized', 403);
    }
  }
}
