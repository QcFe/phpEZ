<?php

/**
 * Provides an example user management functionality for the application.
 * @package ExampleApp
 */
class User extends Model {
  use SoftDelete;
  use Sexable; // this enables session persistence for User objects via Sex, be sure to call Sex::initGlobal() in your index. Call User::setSex($anotherSex) to use a different Sex instance for this model than the global one.

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

  /**
   * Check if the provided password meets the requirements.
   * 
   * @param string $pw The password to check.
   * @throws HTTPException If the password does not meet the requirements.
   */
  protected function pwReqCheck(string $pw) {
    if (strlen($pw) < 9) {
      throw new HTTPException('pw_too_short', 400);
    }
  }

  /**
   * Set the password for the user, hashing it with a salt.
   * 
   * @param string $pw The password to set.
   * @param string|null $hashFn Optional hash function to use (default is sha256).
   * @return $this The current User instance for method chaining.
   * @throws HTTPException If the password does not meet requirements.
   */
  public function setPw(string $pw, string | null $hashFn = null): static {
    static::pwReqCheck($pw);
    $hashFn = $hashFn ?? static::$defHashFn;
    $salt = bin2hex(random_bytes(16));
    $hash = hash($hashFn, $salt . $pw);
    $this->hash = "$hashFn:$hash:$salt";
    return $this;
  }

  /**
   * Verify a provided password against the stored hash.
   * 
   * @param string $pw The password to verify.
   * @return bool True if the password matches, false otherwise.
   * @throws HTTPException If the user has no password set.
   */
  public function verifyPw(string $pw): bool {
    if (empty($this->hash)) {
      HTTPException::throw(500, 'user_no_pw', more: ['uname' => $this->uname]);
    }
    [$hashFn, $hash, $salt] = explode(':', $this->hash);
    $computedHash = hash($hashFn, $salt . $pw);
    return hash_equals($computedHash, $hash);
  }

  /**
   * Update the last login date and IP address for the user.
   * @return $this The current User instance for method chaining.
   */
  public function setLast(): static {
    $this->lastD = new DBDateTime();
    $this->lastIP = HTTPSrv::remote_addr();
    return $this;
  }

  /**
   * Convert the User instance to a UserData DTO.
   * @return UserData A data transfer object containing user information.
   */
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

  /**
   * Create a User instance from a UserData DTO.
   * @param UserData $data The data transfer object containing user information.
   * @return static A new User instance populated with the provided data.
   */
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

  /**
   * Get the currently logged-in user from the session.
   * Returns null if no user is logged in.
   */
  public static function me(): ?static {
    return static::fromSex();
  }

  /**
   * Require a logged-in user.
   * Throws an HTTPException if no user is logged in.
   */
  public static function require(): static {
    return self::me() ?? HTTPException::throw('not_logged_in', 401);
  }

  /**
   * Require a logged-in admin user.
   * Throws an HTTPException if no user is logged in or if the user is not an admin.
   */
  public static function requireAdmin(): static {
    $u = self::require();
    if (!$u->isAdmin) {
      HTTPException::throw('unauthorized', 403);
    }
    return $u;
  }
}
