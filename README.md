# Componenta Validation

Validation library for PHP 8.4+ with Componenta Config 3 or 4 and MIME Type Detector 1.x or 2.x (2.x requires Config 4). It provides immutable validators, composable rules, attribute metadata, mapped validators, localized messages, nested and wildcard field paths, and framework-neutral factories.

## Installation

```bash
composer require componenta/validation
```

For Componenta application discovery and generation of production validator factories, also install:

```bash
composer require componenta/validation-app
```

The core package can be used without `componenta/app` or `componenta/validation-app`.

## Quick start

```php
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;

$rules = new RuleFactory();
$validator = new Validator([
    'email' => $rules->createRule('required|email|length:5,255'),
    'age' => $rules->createRule('int|range:18,120'),
]);

$result = $validator->validate([
    'email' => 'user@example.com',
    'age' => 25,
]);

if ($result !== true) {
    $errors = $result->toArray();
}
```

When the package is wired through its `ConfigProvider`, inject `ValidatorFactoryInterface`:

```php
$validator = $factory->createFrom([
    'email' => 'required|email|length:5,255',
]);
```

`ValidatorInterface::validate()` accepts iterable input, including arrays, rewindable iterators, and one-shot generators. The validator retains a per-call array of values for traversal and cross-field checks. A shared iterable instance is read once per call. Recursive collections raise `InvalidArgumentException`.

The return value is `true` on success or `ErrorMessageCollectorInterface` on failure. `ContextInterface::THROW_ON_FAILURE_ATTRIBUTE` changes validation failures into `ValidationException`.

## Optional services

Database and MIME support are optional. The default DI factory uses these services only when the container has them:

- `Cycle\Database\DatabaseInterface` enables `exists` and `unique`;
- `Componenta\Detector\MimeTypeDetectorInterface` enables `mime_type` and MIME restrictions in `file`.

All other bundled rules remain available without either service. A manually created `RuleFactory` accepts the same dependencies as nullable constructor arguments.

The `phone` rule is part of the core package. Its `giggsey/libphonenumber-for-php` runtime dependency is declared by `componenta/validation`.

## Field paths and missing values

Rules are keyed by field path. Dot notation addresses nested values and `*` addresses every existing collection item:

```php
$validator = $factory->createFrom([
    'profile.email' => 'required|email',
    'items.*.sku' => 'required|string',
    'items.*.tags.*' => 'string|length:1,50',
]);
```

Missing nested leaves are validated as `null`. For example, `profile.email` fails `required` when `profile` or `email` is absent. For `items.*.sku`, every existing item receives its own missing-field target when `sku` is absent.

## Attributes

`AttributeValidationProvider` builds validator definitions from property attributes in development:

```php
use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\When;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Required;

final class CreateUserCommand
{
    #[Field('user_email')]
    #[Required]
    #[Email]
    #[Validate('length:5,255')]
    public string $email;

    #[When('status:published', then: 'required|string', else: 'nullable|string')]
    public ?string $summary = null;
}
```

The field-name priority is:

1. `#[Field('name')]`;
2. `#[Validate(..., as: 'name')]`;
3. the PHP property name.

Direct rule attributes are instantiated as rules. `RuleAttribute` subclasses such as `#[Exists]`, `#[Unique]`, and `#[When]` are hydrated through `RuleFactoryInterface`, so service-backed rules receive their configured dependencies.

A class may delegate validation to a validator service:

```php
use Componenta\Validation\Attribute\ValidatedBy;

#[ValidatedBy(CreateUserValidator::class)]
final class CreateUserCommand
{
}
```

Pass the name of a class or interface that implements `ValidatorInterface`. `ValidatorFactoryInterface::create()` resolves that name as a service ID, including explicit factories and interface mappings. Use `ConfigKey::VALIDATORS_MAP` for other service IDs. Combining `#[ValidatedBy]` with property validation rules raises `InvalidArgumentException`.

### Field callbacks in PHP 8.5

`#[Callback]` receives the current field value and `ContextInterface` during validation. The callback returns `true` on success or `ErrorMessageCollectorInterface` on failure. A field can declare several `#[Callback]` attributes alongside `#[Validate]`.

```php
use Componenta\Validation\Attribute\Callback;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Regex;

final class Invitation
{
    #[Validate('required')]
    #[Callback(self::validateInviteCode(...))]
    public string $inviteCode;

    private static function validateInviteCode(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return (new Regex('/\A[A-Z0-9]{6}\z/'))->validate($value, $context);
    }
}
```

### Callable conditions in PHP 8.5

`#[WhenCallback]` accepts a capture-free static closure or a first-class callable. The condition receives the current field value and `ContextInterface` during validation. It returns a boolean to select `then` or `else`.

Each branch accepts a rule string or a validation callback. During validation, a callback receives the current field value and `ContextInterface`. It returns `true` on success or `ErrorMessageCollectorInterface` on failure. Only the selected branch runs. The `else` branch uses `Nullable` by default.

```php
use Componenta\Validation\Attribute\WhenCallback;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Email;

final class Publication
{
    #[WhenCallback(self::isPublished(...), then: self::validatePublishedEmail(...), else: 'nullable|email')]
    public ?string $email = null;

    private static function isPublished(mixed $value, ContextInterface $context): bool
    {
        $data = $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        return ($data['status'] ?? null) === 'published';
    }

    private static function validatePublishedEmail(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return (new Email())->validate($value, $context);
    }
}
```

PHP 8.5.13 fixes scope resolution for first-class callables declared in trait attributes. The native provider handles classes using `#[Callback]` or `#[WhenCallback]` when the application uses a compiled validation map.

## Development and production providers

The default factory creates CompositeValidationProvider in this order: ValidatableProvider, explicit services in ConfigKey::VALIDATORS_MAP, then AttributeValidationProvider. The core works the same way in every environment.

With componenta/validation-app, app:build exports complete validation definitions using the exact built-in Validate, Field and ValidatedBy attributes. The artifact maps class names to field-to-rule strings or validator service names. String literals, class-name expressions and a null Validate::as can be exported without evaluating arguments.

Production inserts MapValidationProvider before AttributeValidationProvider. A class with custom attributes, rule objects, dynamic arguments or conflicting declarations is omitted from the map and handled entirely through native attributes. Field determines an alias and has priority over Validate::as; it does not define a rule itself. An absent entry returns null from MapValidationProvider so the composite can delegate to the next provider.

Each provide() creates rules through the current RuleFactoryInterface and validators through ValidatorFactoryInterface. Native attributes and nested constructor arguments are fresh for each provided validator. Validator services follow their container lifetime. Repeated validate() calls retain the returned validator's own state.

An application factory registration for ValidationProviderInterface, supplied after the package ConfigProviders, replaces the default provider entirely. Its returned provider is used directly, including null results. Existing provider instances can be returned from that factory.

Missing or invalid artifacts fall back to native attributes without starting a build. Configure the path with Componenta\Validation\App\ConfigKey::MAP_FILE. The single generated PHP file and matching source code belong to one deployment.

## Rule syntax

`RuleFactoryInterface` supports:

```php
$rules->createRule('email');
$rules->createRule('required|email|length:5,255');
$rules->createRule('nullable|email');
$rules->createRule('oneof:email,phone');
$rules->createRule('arrayof:uuid');
```

Pipe-separated definitions are composed with `AllOf`. A nullable member is composed through the same nullable-aware helper used by attributes and programmatic rule collections, so equivalent construction paths have equivalent behavior.

`ifthen` is not a string alias because a safe string grammar cannot represent an arbitrary callable condition. Use the declarative `when` rule, `#[When]`, or construct `IfThen` programmatically.

## Built-in rules

The package includes required/optional, scalar type, string, numeric, comparison, date, array, conditional, password, upload, database, and composite rules. Common names include:

```text
required, nullable, filled, accepted
string, int, array, numeric, boolean
email, url, regex, uuid, alpha, alpha_num, alpha_dash, length, phone
range, min, max, positive, negative
confirmed, equals, not_equals, gt, gte, lt, lte
date, date_format, before, before_or_equal, after, after_or_equal
count, distinct, required_if, required_with, required_without
prohibited_if, exclude_if, when, password
uploaded_file, file_size, mime_type, file
exists, unique, allof, oneof, arrayof
```

`OneOf` always evaluates alternatives until one succeeds. `STOP_ON_FIRST_FAILURE_ATTRIBUTE` controls only which errors are retained when every alternative fails; it does not change logical truth. Composite predicate methods evaluate children through `RuleInterface::validate()`, so custom rules need only implement the published interface.

The `mime_type` and `file` rules support MIME Type Detector 2.x's nullable MIME value objects and the 1.x string result contract. MIME restrictions are exact allowlists of type/subtype strings; wildcard patterns are not enabled implicitly. Client-supplied file names and MIME declarations are not used to determine the type. MIME detection is heuristic and does not independently establish upload safety.

The stock `file` rule validates upload status before size or MIME access. `MimeType` rejects failed uploads without calling `getStream()`. A configured size limit requires the uploaded file to report a size.

## Custom rules

```php
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;

final class Uppercase implements RuleInterface
{
    public string $name {
        get => 'uppercase';
    }

    public function validate(
        mixed $value,
        ContextInterface $context,
    ): true|ErrorMessageCollectorInterface {
        if (is_string($value) && $value === strtoupper($value)) {
            return true;
        }

        $errors = new ErrorMessageCollector();
        $errors->add(
            (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, ''),
            new ErrorMessage($context, 'validation.uppercase.invalid'),
        );

        return $errors;
    }
}
```

Register a custom string rule through `RuleFactory::register()` and aliases through `RuleFactory::alias()`. Mark a registration as composite only when its parameters are nested rule definitions.

## Configuration keys

| Key | Purpose |
|---|---|
| `ConfigKey::VALIDATORS_MAP` | Explicit entry-id to validator-service map. |
| `ConfigKey::DICTIONARY` | Custom message dictionary. |
| `ConfigKey::USED_LOCALES` | Locales whose dictionaries are loaded. |
| `ConfigKey::DEFAULT_LOCALE` | Default formatter locale. |

The core `ConfigProvider` registers `ValidatorFactoryInterface`, `ValidationProviderInterface`, `RuleFactoryInterface`, `MessageFormatterInterface`, and default locale configuration.
