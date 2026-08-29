<?php

/**
 * @package PHPEz
 */

/**
 * Base class for property-level attributes in the PHPEz framework.
 * 
 * Provides a reusable foundation for declaring metadata attributes on object properties.
 * Subclasses define behavior (e.g., OmitEmpty, DoNotSerialize) that affects serialization,
 * deserialization, and validation of Obj instances.
 * 
 * This uses PHP 8 attributes applied to properties, enabling declarative behavior
 * without modifying code logic.
 * 
 * @subpackage iface
 * @Annotation
 * @see OmitEmpty
 * @see DoNotSerialize
 * @see DoNotDeserialize
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ObjAttribute {
  /**
   * Extract an attribute instance from a reflected property.
   * 
   * Searches the property's attributes for one matching this class name and
   * instantiates it with the attribute's arguments.
   * 
   * @param ReflectionProperty $field The property to inspect for attributes.
   * @return static|false The attribute instance if found, false otherwise.
   */
  public static function of(ReflectionProperty $field): static|null {
    foreach ($field->getAttributes() as $attr) {
      if ($attr->getName() === static::class) {
        return new static(...$attr->getArguments());
      }
    }
    return null;
  }
}

/**
 * Base class for class-level attributes in the PHPEz framework.
 * 
 * Provides a reusable foundation for declaring metadata attributes on classes.
 * Subclasses define behavior (e.g., ObjDef) that affects serialization,
 * deserialization, and validation of Obj instances.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ObjDef {
  /**
   * Extract an attribute instance from a reflected class.
   * 
   * Searches the class's attributes for one matching this class name and
   * instantiates it with the attribute's arguments.
   * 
   * @param string $class The class to inspect for attributes.
   * @return static|false The attribute instance if found, false otherwise.
   */
  public static function of(string $class): static|null {
    $reflectionClass = new ReflectionClass($class);
    foreach ($reflectionClass->getAttributes() as $attr) {
      if ($attr->getName() === static::class) {
        return new static(...$attr->getArguments());
      }
    }
    return null;
  }
}

/**
 * Attribute to omit uninitialized properties from serialization.
 * 
 * When a property is marked with #[OmitEmpty], it will not be included in the
 * serialized output if the property has not been assigned a value (checked via
 * ReflectionProperty::isInitialized()).
 * 
 * Useful for optional fields that should not appear in JSON when not set.
 * 
 * Usage:
 * ```php
 * class User extends Obj {
 *   public string $name;
 *   #[OmitEmpty]
 *   public ?string $phone = null;
 * }
 * 
 * $user = new User(['name' => 'Alice']);
 * // Serializes to: {"name": "Alice"}  (phone is omitted)
 * ```
 * 
 * @subpackage iface
 */
class OmitEmpty extends ObjAttribute {
}

/**
 * Attribute specifying the element type of an array property.
 * 
 * Array-typed properties on Obj have no inherent element type, so #[ArrayOf]
 * tells __unserialize() what class to instantiate (and call parse() on) for
 * each element of the incoming array.
 * 
 * Usage:
 * ```php
 * class Order extends Obj {
 *   #[ArrayOf(LineItem::class)]
 *   public array $items;
 * }
 * ```
 * 
 * @see Obj::__unserialize()
 * @subpackage iface
 */
class ArrayOf extends ObjAttribute {
  /**
   * Initialize the array element type attribute.
   * 
   * @param string $innerType Fully-qualified class name of the array elements.
   */
  public function __construct(public string $innerType) {
  }
}

/**
 * Attribute to exclude a property from serialization.
 * 
 * Properties marked with #[DoNotSerialize] will not be included in the output
 * of __serialize(), even if they have values. Useful for internal state,
 * computed fields, or sensitive data that should never be sent to clients.
 * 
 * Usage:
 * ```php
 * class User extends Obj {
 *   public string $name;
 *   #[DoNotSerialize]
 *   private string $passwordHash;
 * }
 * ```
 * @subpackage iface
 */
class DoNotSerialize extends ObjAttribute {
}

/**
 * Attribute to exclude a property from deserialization.
 * 
 * Properties marked with #[DoNotDeserialize] will not be populated from
 * incoming data during __unserialize(). Useful for read-only fields or
 * computed properties that should be set by business logic, not API input.
 * 
 * Usage:
 * ```php
 * class Post extends Obj {
 *   public string $title;
 *   #[DoNotDeserialize]
 *   public DateTime $createdAt;
 * }
 * ```
 * 
 * @subpackage iface
 */
class DoNotDeserialize extends ObjAttribute {
}

/**
 * Interface for custom serialization/deserialization of values.
 * 
 * Implement this interface on types that need custom marshalling logic beyond
 * the default Obj serialization. Examples: Enums, Value Objects, Date/Time types.
 * 
 * The framework automatically calls marshall() on Parsable objects during serialization
 * and parse() during deserialization when encountered as field types.
 * 
 * @see SerializableDateTime For a practical example implementation.
 * @subpackage iface
 */
interface Parsable {
  /**
   * Parse external data into this object.
   * 
   * Called during deserialization when this type is encountered in an Obj field.
   * 
   * @param mixed $data The external data to parse (typically from JSON).
   * @return static A new instance populated with parsed data.
   * @throws Exception For invalid or unparseable data.
   */
  public function parse(mixed $data): static;

  /**
   * Convert this object into a marshalled representation.
   * 
   * Called during serialization to convert the object into a format suitable
   * for JSON encoding (typically a string or primitive).
   * 
   * @return mixed The marshalled representation (string, array, etc).
   */
  public function marshall(): mixed;
}

/**
 * Base class for serializable/deserializable API data objects.
 * 
 * Obj is the foundation of PHPEz's data handling. It provides automatic
 * bidirectional serialization using PHP reflection and type hints, eliminating
 * boilerplate mappers, validators, and serializers.
 * 
 * Key features:
 * - **Automatic serialization**: Reflects on properties to build JSON output
 * - **Type-aware deserialization**: Enforces types and converts nested objects
 * - **Attribute-based control**: Use #[OmitEmpty], #[DoNotSerialize], etc.
 * - **Nested object support**: Automatically handles Obj subclasses
 * - **Custom type handling**: Supports Parsable interface for specialized types
 * - **Validation**: Enforces nullability and type requirements
 * 
 * Usage:
 * ```php
 * class CreateUserRequest extends Obj {
 *   public string $name;
 *   public string $email;
 *   #[OmitEmpty]
 *   public ?string $phone = null;
 * }
 * 
 * // Deserialization (from API input):
 * $req = new CreateUserRequest(json_decode($body, true));
 * 
 * // Serialization (to API response):
 * $response = new UserResponse(...);
 * echo json_encode($response);  // Calls __serialize() via JsonSerializable
 * ```
 * 
 * Type system:
 * - Properties MUST have type declarations (string, int, bool, float, null, or Obj subclass)
 * - Type hints are enforced during deserialization
 * - Nullable types (?Type) are allowed
 * - Use #[OmitEmpty] for optional fields
 * 
 * @see JsonSerializable
 * @see OmitEmpty
 * @see DoNotSerialize
 * @see DoNotDeserialize
 * @see Parsable
 * @subpackage iface
 */
class Obj implements JsonSerializable {
  /**
   * Base types that don't require special serialization handling.
   * 
   * @var array<string>
   */
  protected const baseTypes = ['string', 'int', 'bool', 'float', 'null'];

  /**
   * Initialize an Obj, optionally populating it with data.
   * 
   * If $data is provided, it's passed to __unserialize() to populate properties.
   * This enables single-line initialization: `new User($data)`
   * 
   * @param mixed $data Optional data to deserialize into this object.
   *                    Usually an array from json_decode() or similar.
   */
  public function __construct(mixed $data = null) {
    if ($data) {
      $this->__unserialize($data);
    }
  }

  /**
   * Serialize this object to an array suitable for JSON encoding.
   * 
   * Reflects on all properties and processes each according to:
   * 1. Skips static properties and those with #[DoNotSerialize]
   * 2. Skips uninitialized properties with #[OmitEmpty]
   * 3. For Parsable fields: calls marshall()
   * 4. For objects with __serialize(): calls __serialize()
   * 5. For base types (string, int, bool, float, null): includes as-is
   * 
   * Returns a flat array with property names as keys.
   * 
   * @return array<string,mixed> Serialized representation suitable for json_encode().
   * @throws HTTPException For unsupported field types or serialization errors.
   */
  public function __serialize(): array {
    $fields = (new ReflectionClass($this))->getProperties();
    $out = [];
    foreach ($fields as $field) {
      $fName = $field->getName();
      if ($field->isStatic() || DoNotSerialize::of($field) || $field->getHooks()) {
        continue;
      }
      $fType = $field->getType();
      $fTypeName = $fType->getName();
      if (OmitEmpty::of($field) && !$field->isInitialized($this)) {
        continue;
      }
      try {
        $fVal = $field->getValue($this);
      } catch (Exception $e) {
        throw new DataException("Failed to get [$fName]: " . $e->getMessage() . ' - Forgot an OmitEmpty?', more: [
          'type' => $this::class,
          'op' => 'serialize',
          'field' => [
            'name' => $fName,
            'expType' => $fTypeName,
          ],
        ]);
      }


      if ($fVal) {
        $fVal = self::serializeVal($fVal, $fTypeName);
      }
      $out[$fName] = $fVal;
    }
    return $out ?? [];
  }

  public static function serializeVal(mixed $val, string|null $type = null) {
    if ($type === null) {
      $type = gettype($val);
      if ($type === 'object') {
        $type = get_class($val);
      }
    }
    if (is_array($val)) {
      $val = array_map('Obj::serializeVal', $val);
    } else if (in_array($type, self::baseTypes)) {
      // ok
    } else  if (in_array('Parsable', class_implements($type))) {
      $val = $val->marshall();
    } else if (is_object($val) && method_exists($val, '__serialize')) {
      $val = $val->__serialize();
    } else {
      HttpException::throw(500, 'unsupported_type', more: [
        'unsupported' => true,
        'type' => $type,
      ]);
    }
    return $val;
  }

  /**
   * Deserialize data into this object's properties.
   * 
   * Reflects on all properties and populates them from the input data array:
   * 1. Skips static properties and those with #[DoNotDeserialize]
   * 2. Validates mandatory fields (non-nullable types with no value)
   * 3. For #[OmitEmpty] fields: allows missing data
   * 4. For Parsable types: calls parse()
   * 5. For objects with __unserialize(): recursively deserializes
   * 6. For base types: assigns directly (with type coercion)
   * 
   * Enforces type safety: throws HTTPException (400) for validation failures,
   * HTTPException (500) for unsupported types.
   * 
   * @param mixed $data Array-like data to deserialize (typically from json_decode(array)).
   * @return void Properties are populated in-place.
   * @throws HTTPException For validation errors (400) or unsupported types (500).
   */
  public function __unserialize(mixed $data): void {
    $fields = (new ReflectionClass($this))->getProperties();
    $data = (array)$data;
    foreach ($fields as $field) {
      if ($field->isStatic() || DoNotDeserialize::of($field) || $field->getHooks()) continue;
      $fName = $field->getName();
      $fType = $field->getType();
      $fTypeName = $fType->getName();
      $fVal = $data[$fName] ?? null;
      $dbg_more = fn(array $info) => [
        'type' => $this::class,
        'op' => 'unserialize',
        'field' => [
          'name' => $fName,
          'expType' => $fTypeName,
          'val' => $fVal,
          ...$info,
        ],
      ];

      // Se il campo non è presente nei dati e ha OmitEmpty, salta la validazione obbligatoria
      if (!array_key_exists($fName, $data) && OmitEmpty::of($field)) {
        continue;
      }

      if ($fVal === null && !$fType->allowsNull()) {
        HttpException::throw(400, 'invalid_input', more: $dbg_more([
          'mandatory' => true,
        ]));
      }
      if ($fVal) {
        if ($fTypeName === 'array') {
          $innerTyp = ArrayOf::of($field)?->innerType;
          if (!$innerTyp) {
            DataException::throw(code: 400, msg: "Array field $fName is missing #[ArrayOf] attribute for inner type specification.", more: $dbg_more([]));
          }
          $fVal = array_map(
            fn($e) => (new $innerTyp())->parse($e),
            $fVal
          );
        } else if (in_array($fTypeName, self::baseTypes)) {
          //ok
        } else if (in_array('Parsable', class_implements($fTypeName))) {
          $fVal = (new $fTypeName())->parse($fVal);
        } else if (is_object($fVal) && method_exists($fVal, '__unserialize')) {
          $obj = new $fTypeName();
          $obj->__unserialize($fVal);
          $fVal = $obj;
        } else {
          HttpException::throw(500, 'unsupported_type', more: $dbg_more([
            'unsupported' => true,
          ]));
        }
      }
      $this->$fName = $fVal;
    }
  }

  /**
   * Deserialize a JSON string into this object.
   * 
   * Convenience method that decodes raw JSON and calls __unserialize().
   * Used by the API framework to populate request body objects.
   * 
   * @param string $rawData Raw JSON string from request body.
   * @return void Properties are populated in-place.
   * @throws HTTPException (400) For invalid JSON or deserialization errors.
   * 
   * @example
   * ```php
   * $request = new CreateUserRequest();
   * $request->unserializeRaw(file_get_contents('php://input'));
   * ```
   */
  public function unserializeRaw(string $rawData) {
    if (!is_string($rawData) || !$rawData) {
      HTTPException::throw(400, 'invalid_input', more: [
        'got' => $rawData,
      ]);
    }
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      HTTPException::throw(400, 'invalid_input', more: [
        'json_parse_err' => json_last_error_msg()
      ]);
    }
    $this->__unserialize($data);
  }

  /**
   * Implement JsonSerializable to enable json_encode() on this object.
   * 
   * Delegates to __serialize() so that json_encode($obj) works seamlessly.
   * 
   * @return mixed The serialized array from __serialize().
   */
  public function jsonSerialize(): mixed {
    return $this->__serialize();
  }
}

/**
 * Base class for serializable DateTime objects.
 * 
 * Extends DateTime with Parsable interface support, enabling DateTime fields in Obj
 * subclasses to be automatically serialized/deserialized with consistent formatting.
 * 
 * Subclasses must implement getFormat() to specify the DateTime format string.
 * This pattern allows different DateTime representations in different contexts
 * (e.g., JSONDateTime for ISO 8601, CSVDateTime for another format).
 * 
 * Features:
 * - Automatic serialization to formatted string via marshall()
 * - Automatic deserialization from formatted string via parse()
 * - JsonSerializable support
 * - Static factory method fromDT() for easy conversion from existing DateTime
 * 
 * Example:
 * ```php
 * class JSONDateTime extends SerializableDateTime {
 *   protected static function getFormat(): string {
 *     return 'Y-m-d\\TH:i:s.uP';  // ISO 8601 with microseconds
 *   }
 * }
 * 
 * class Event extends Obj {
 *   public string $title;
 *   public JSONDateTime $date;  // Automatically serialized/deserialized
 * }
 * ```
 * 
 * @see Parsable
 * @see JsonSerializable
 * @subpackage iface
 */
abstract class SerializableDateTime extends DateTime implements Parsable, JsonSerializable {
  /**
   * Get the DateTime format string for this class.
   * 
   * Subclasses must override this to specify how DateTime objects
   * are formatted when serialized and expected when deserialized.
   * 
   * @return string A format string compatible with DateTime::format() and
   *                DateTime::createFromFormat().
   * 
   * @see https://www.php.net/manual/en/datetime.format.php
   */
  protected static abstract function getFormat(): string;

  /**
   * Convert this DateTime to a formatted string.
   * 
   * Called during serialization when this DateTime appears in an Obj field.
   * 
   * @return string The formatted date/time string.
   */
  public function marshall(): string {
    return $this->format(static::getFormat());
  }

  /**
   * Parse a formatted date string into this DateTime.
   * 
   * Called during deserialization when a string is provided for a DateTime field.
   * 
   * @param mixed $data The date string to parse (should match getFormat()).
   * @return static This instance, updated with the parsed timestamp.
   * @throws Exception For invalid data types or format mismatches.
   */
  public function parse(mixed $data): static {
    if (!is_string($data))
      throw new Exception("Invalid data type for parsing: " . gettype($data));

    $dt = DateTime::createFromFormat(static::getFormat(), $data)
      ?: throw new Exception("Invalid date format for unserialization: $data");

    return $this->setTimestamp($dt->getTimestamp());
  }

  public static function fromString(string $data): static {
    $instance = new static();
    return $instance->parse($data);
  }

  /**
   * Serialize this DateTime for JSON encoding.
   * 
   * Implements JsonSerializable to enable json_encode() on Obj instances
   * containing DateTime fields.
   * 
   * @return string The formatted date/time string (via marshall()).
   */
  public function jsonSerialize(): mixed {
    return $this->marshall();
  }

  /**
   * Create a SerializableDateTime from an existing DateTime instance.
   * 
   * Convenient factory method for converting existing DateTime objects.
   * 
   * @param DateTime $dt The source DateTime.
   * @return static A new instance with the same timestamp as $dt.
   */
  public static function fromDT(DateTime $dt): static {
    $instance = new static();
    $instance->setTimestamp($dt->getTimestamp());
    return $instance;
  }
}
