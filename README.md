# Componenta Validation

Validation library for PHP 8.4+. The package provides validators, rule composition, string rule syntax, property attributes, mapped validators, immutable validation context, localized messages, and factories for framework integration.

## Installation

```bash
composer require componenta/validation
```

The Composer package installs the dependencies needed by all bundled rules. When
the package is wired through `Componenta\Validation\ConfigProvider`, the
container must provide `Cycle\Database\DatabaseInterface` and
`Componenta\Detector\MimeTypeDetectorInterface`, because the default rule factory
is built with database and MIME support.

If you instantiate `Componenta\Validation\Rule\RuleFactory` manually, both
dependencies are optional constructor arguments. Passing `null` disables the
rules that need that dependency:

- `exists` and `unique` are registered only when a database is available;
- `mime_type` and MIME checks inside `file` are registered only when a detector is available;
- framework registration is provided by `Componenta\Validation\ConfigProvider`.

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/di` | Creates validators/rules through ordinary callable factories and is commonly used with request DTO mapping. |
| `cycle/database` | Provides `Cycle\Database\DatabaseInterface` for database-backed `exists` and `unique` rules. |
| `componenta/mimetype-detector` | Provides `MimeTypeDetectorInterface` for MIME/file rules when MIME type should be detected from bytes or files. |
| `componenta/config` | Registers validator factories, message dictionaries, and locales in framework applications. |
| `psr/http-message` | HTTP applications often supply PSR-7 request data before validation, although validation itself is transport-neutral. |

## Quick Start

```php
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;

$ruleFactory = new RuleFactory();

$validator = new Validator([
    'email' => $ruleFactory->createRule('required|email|length:5,255'),
    'age' => $ruleFactory->createRule('int|range:18,120'),
]);

$result = $validator->validate([
    'email' => 'user@example.com',
    'age' => 25,
]);

if ($result !== true) {
    $errors = $result->toArray();
}
```

In a framework application, inject `ValidatorFactoryInterface` and call
`createFrom()` with an array of field names to rule definitions:

```php
$validator = $factory->createFrom([
    'email' => 'required|email|length:5,255',
]);
```

A standalone string rule is not a validator definition.

## Validator Contract

`ValidatorInterface::validate(iterable $data, ?ContextInterface $context = null)` returns:

- `true` when validation passes;
- `ErrorMessageCollectorInterface` when validation fails.

When `ContextInterface::THROW_ON_FAILURE_ATTRIBUTE` is `true`, validation
throws `ValidationException` instead of returning the collector.

```php
$result = $validator->validate(['email' => 'invalid']);

if ($result !== true) {
    $result->has('email');
    $result->get('email');
    $result->toArray();
}
```

`Validator` is immutable. Methods such as `withRules()`, `withWalker()`, `withFormatter()`, and `withLocale()` return new validators.

## Field Paths And Wildcards

Rules are keyed by field path. Nested arrays use dot notation. Wildcards validate every element at that level.

```php
$validator = $factory->createFrom([
    'user.email' => 'required|email',
    'users.*.email' => 'required|email',
    'tags.*' => 'string|length:1,50',
]);
```

The walker reports errors under fully qualified paths, for example `users.0.email`.

## Rule Syntax

Rules are created by `RuleFactoryInterface`.

```php
$ruleFactory->createRule('email');
$ruleFactory->createRule('required|email|length:5,255');
$ruleFactory->createRule('nullable|email');
$ruleFactory->createRule('oneof:email,phone');
```

Syntax:

- `name`
- `name:param1,param2`
- `ruleA|ruleB|ruleC`
- `regex:/^\d+$/`

Pipe-separated rules are wrapped in `AllOf`: every rule must pass. `nullable|...` is special: `null` passes immediately; non-null values must pass the remaining rules.

Composite string rules (`allof`, `oneof`, `arrayof`) parse their parameters as nested rule definitions. Other rules treat parameters as plain strings.

## Built-In Rules

| Rule | Parameters | Meaning |
|---|---|---|
| `required` | none | Value must be present and non-empty. |
| `nullable` | none | Null is accepted. |
| `filled` | none | Present value must not be empty. |
| `accepted` | none | Accepts common truthy consent values. |
| `in` | `value,...` | Value must be one of the listed values. |
| `not_in` | `value,...` | Value must not be one of the listed values. |
| `string` | none | Value must be string. |
| `int`, `integer` | `strict?` | Integer check, optionally strict. |
| `array` | `list?` | Array check, optionally list-like. |
| `numeric` | none | Numeric value. |
| `boolean`, `bool` | `strict?` | Boolean check, optionally strict. |
| `email` | none | Email address. |
| `url` | `scheme,...?` | URL, optionally restricted to schemes. |
| `regex` | `pattern` | Regular expression match. |
| `uuid` | `version?` | UUID, optionally specific version. |
| `alpha` | `ascii?` | Letters only. |
| `alpha_num` | `ascii?` | Letters and digits. |
| `alpha_dash` | `ascii?` | Letters, digits, dash and underscore. |
| `length` | `min?,max?` | String length bounds. |
| `phone` | `region?` | Phone number validation. |
| `range` | `min?,max?` | Numeric range. |
| `min` | `min` | Numeric lower bound. |
| `max` | `max` | Numeric upper bound. |
| `positive` | `orZero?` | Positive number. |
| `negative` | `orZero?` | Negative number. |
| `equals` | `field,strict?` | Equals another field. |
| `not_equals` | `field,strict?` | Does not equal another field. |
| `gt`, `gte` | `field` | Greater than / greater than or equal to another field. |
| `lt`, `lte` | `field` | Less than / less than or equal to another field. |
| `confirmed` | `suffix?` | Matches confirmation field. |
| `date` | none | Date-like value. |
| `date_format` | `format` | Date in exact format. |
| `before` | `date,orEqual?,graceMinutes?` | Date before target. |
| `before_or_equal` | `date,graceMinutes?` | Date before or equal to target. |
| `after` | `date,orEqual?,graceMinutes?` | Date after target. |
| `after_or_equal` | `date,graceMinutes?` | Date after or equal to target. |
| `count` | `min?,max?` | Array/countable size. |
| `distinct` | `field?` | Array values are distinct. |
| `required_if` | `field,value` | Required when another field equals value. |
| `required_with` | `field,...` | Required when any listed field is present. |
| `required_without` | `field,...` | Required when listed fields are absent. |
| `prohibited_if` | `field,value` | Must be absent when condition matches. |
| `exclude_if` | `field,value` | Excludes value when condition matches. |
| `when` | `field:value,then,else?` | Conditional nested rules. |
| `password` | `min?,flags?,confirmation?` | Password complexity rule. |
| `uploaded_file` | none | PSR uploaded file. |
| `file_size` | `max,min?` | Uploaded file size. |
| `mime_type` | `type,...` | MIME type through detector. |
| `file` | `max?,mime,...` | Combined uploaded file checks. |
| `exists` | `table,column?` | Database existence. |
| `unique` | `table,column?` | Database uniqueness. |
| `allof` | `rule,...` | Every nested rule must pass. |
| `oneof` | `rule,...` | At least one nested rule must pass. |
| `arrayof` | `rule` | Every array item must pass the nested rule. |
| `ifthen` | programmatic use | Conditional rule class for callable conditions. Plain string definitions cannot express the callable condition safely. |

## Attribute Validation

`AttributeValidationProvider` builds validators from class property attributes.
The attribute classes are also allowed on parameters so other framework layers
can reuse the same metadata, but this provider itself scans properties only.

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

Field name resolution order:

1. `#[Field('name')]`
2. `#[Validate(..., as: 'name')]`
3. PHP property name

Attribute types:

| Attribute | Target | Meaning |
|---|---|---|
| `#[Validate]` | property, parameter | String rule definition. `AttributeValidationProvider` reads it from properties. |
| `#[Field]` | property, parameter | Override validation field name. `AttributeValidationProvider` reads it from properties. |
| `#[ValidatedBy]` | class | Link class to validator class. |
| `#[Exists]` | property, parameter | Database exists rule metadata. |
| `#[Unique]` | property, parameter | Database unique rule metadata. |
| `#[When]` | property, parameter, repeatable | Conditional rule metadata. |
| direct rule attributes | property, parameter | Rule objects instantiated directly. |

Rules requiring services should use `RuleAttribute` subclasses so the real rule is built by `RuleFactory`.

## Validation Providers

`ValidationProviderInterface` resolves validators by entry id, usually class name.

| Provider | Source |
|---|---|
| `ValidatableProvider` | `ValidatableInterface::createValidator()`. |
| `MappedValidationProvider` | `ConfigKey::VALIDATORS_MAP`. |
| `AttributeValidationProvider` | Property attributes. |
| `ValidatedByProvider` | `#[ValidatedBy]`. |
| `CompositeValidationProvider` | Tries providers in order. |

`ValidationProviderFactory` builds different chains:

- development: validatable, mapped, attribute, validated-by;
- production: validatable and mapped.

Reflection-based attribute provider is excluded from production by default.

## Custom Rules

Rules implement `RuleInterface`.

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

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if (is_string($value) && $value === strtoupper($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $collector->add(
            (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, ''),
            new ErrorMessage($context, 'validation.uppercase.invalid'),
        );

        return $collector;
    }
}
```

Register it through `RuleFactory::register()`:

```php
$ruleFactory->register('uppercase', static fn (): RuleInterface => new Uppercase());
$ruleFactory->alias('upper', 'uppercase');
```

For composite rules whose parameters should be parsed as nested rules, pass `composite: true`.

## Context

`Context` is immutable. Use `withAttribute()` and `withAttributes()` to derive a modified context.

```php
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;

$context = new Context([
    ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE => true,
    ContextInterface::THROW_ON_FAILURE_ATTRIBUTE => true,
]);
```

Important context attributes:

| Attribute | Meaning |
|---|---|
| `VALIDATION_DATA_ATTRIBUTE` | Original input data. |
| `VALIDATION_RULES_ATTRIBUTE` | Rules collection. |
| `CURRENT_PATH_ATTRIBUTE` | Current full field path. |
| `CURRENT_FIELD_ATTRIBUTE` | Current field name. |
| `CURRENT_RULE_ATTRIBUTE` | Current rule name. |
| `PROCESSED_RULES_ATTRIBUTE` | Rule names processed so far. |
| `LAST_PROCESSED_RULE_ATTRIBUTE` | Last processed rule name. |
| `PROCESSED_FIELDS_ATTRIBUTE` | Field paths processed so far. |
| `LAST_PROCESSED_FIELD_ATTRIBUTE` | Last processed field path. |
| `SKIP_MISSING_RULES_ATTRIBUTE` | Skip fields without rules. |
| `STOP_ON_FIRST_FAILURE_ATTRIBUTE` | Stop on the first error. |
| `THROW_ON_FAILURE_ATTRIBUTE` | Throw `ValidationException` after failure. |
| `MESSAGE_FORMATTER_ATTRIBUTE` | Formatter used for messages. |
| `LOCALE_ATTRIBUTE` | Locale key. |

## Messages

`MessageFormatterInterface` formats error keys through dictionaries. If `ConfigKey::DICTIONARY` is empty, `MessageFormatterFactory` loads default dictionaries for configured locales.

Configuration keys:

| Key | Meaning |
|---|---|
| `ConfigKey::VALIDATORS_MAP` | Class-to-validator map. |
| `ConfigKey::DICTIONARY` | Custom message dictionary. |
| `ConfigKey::USED_LOCALES` | Locales to load. |
| `ConfigKey::DEFAULT_LOCALE` | Default locale. |

Default locale constants include `LOCALE_EN`, `LOCALE_RU`, `LOCALE_ES`, `LOCALE_FR`, and `LOCALE_DE`.

## ConfigProvider

`Componenta\Validation\ConfigProvider` registers:

- `ValidatorFactoryInterface`
- `ValidationProviderInterface`
- `RuleFactoryInterface`
- `MessageFormatterInterface`
- default locale configuration

Rule factory creation accepts normal callable factories; it does not require DI-specific lazy service factories.
