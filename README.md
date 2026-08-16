# Componenta Validation

Validation library for PHP 8.4+. It provides immutable validators, composable rules, attribute metadata, mapped validators, localized messages, nested and wildcard field paths, and framework-neutral factories.

## Installation

```bash
composer require componenta/validation
```

For Componenta application discovery and production compilation of validation attributes, also install:

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

`ValidatorInterface::validate()` accepts any iterable. The implementation materializes it once, so arrays, rewindable iterators, and one-shot generators have the same validation semantics. The return value is `true` or `ErrorMessageCollectorInterface`; `ContextInterface::THROW_ON_FAILURE_ATTRIBUTE` changes failures into `ValidationException`.

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

The referenced identifier is resolved through `ValidatorFactoryInterface::create()`, normally from the container. It may therefore use an explicit factory, an autowired concrete validator, or an interface/service identifier mapped by the application. Declaring `#[ValidatedBy]` together with property validation rules is ambiguous and is rejected instead of silently choosing one source.

## Development and production providers

The default provider order preserves explicit application configuration:

1. `ValidatableProvider` for `ValidatableInterface`;
2. `MappedValidationProvider` for `ConfigKey::VALIDATORS_MAP`;
3. `CompiledValidationProvider` for `ConfigKey::COMPILED_VALIDATORS`;
4. reflection-based attribute discovery in development only.

`VALIDATORS_MAP` remains the manual map from entry id to a validator service. `COMPILED_VALIDATORS` is a versioned descriptor map produced by the application integration; it is not a map of generated validator classes.

With `componenta/validation-app`, `app:build` scans attribute metadata in development and stores a descriptor map in the application config cache. In production the compiled provider hydrates rules and delegates creation to the ordinary `ValidatorFactoryInterface`, so the standard walker, formatter, locale, custom rule factory, and explicit container factories continue to apply. Validator services referenced by `#[ValidatedBy]` are also contributed as DI v4 autowiring roots. Explicit application factories still win over generated DI factories.

The integration marks the compiled map as required outside development. A missing or incompatible map fails during provider construction rather than silently disabling attribute validation. Re-run `app:build` whenever validation attributes, validator services, rule aliases, or their dependencies change.

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

The stock `file` rule validates upload status before size or MIME access. `MimeType` rejects failed uploads without calling `getStream()`.

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
| `ConfigKey::COMPILED_VALIDATORS` | Versioned compiled attribute descriptor map. |
| `ConfigKey::DICTIONARY` | Custom message dictionary. |
| `ConfigKey::USED_LOCALES` | Locales whose dictionaries are loaded. |
| `ConfigKey::DEFAULT_LOCALE` | Default formatter locale. |

The core `ConfigProvider` registers `ValidatorFactoryInterface`, `ValidationProviderInterface`, `RuleFactoryInterface`, `MessageFormatterInterface`, and default locale configuration.
