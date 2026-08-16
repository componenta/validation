# Componenta Validation

Библиотека валидации для PHP 8.4+. Она предоставляет неизменяемые валидаторы, композицию правил, метаданные в атрибутах, ручные карты валидаторов, локализованные сообщения, вложенные и wildcard-пути, а также независимые от фреймворка фабрики.

## Установка

```bash
composer require componenta/validation
```

Для обнаружения атрибутов в Componenta-приложении и их production-компиляции также установите:

```bash
composer require componenta/validation-app
```

Основной пакет можно использовать без `componenta/app` и `componenta/validation-app`.

## Быстрый старт

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

При подключении через `ConfigProvider` внедряйте `ValidatorFactoryInterface`:

```php
$validator = $factory->createFrom([
    'email' => 'required|email|length:5,255',
]);
```

`ValidatorInterface::validate()` принимает любой iterable. Реализация материализует его один раз, поэтому массивы, перематываемые итераторы и одноразовые генераторы имеют одинаковую семантику. Метод возвращает `true` или `ErrorMessageCollectorInterface`; атрибут контекста `ContextInterface::THROW_ON_FAILURE_ATTRIBUTE` преобразует ошибку в `ValidationException`.

## Необязательные сервисы

Поддержка базы данных и MIME необязательна. Стандартная DI-фабрика обращается к сервисам только при их наличии в контейнере:

- `Cycle\Database\DatabaseInterface` включает `exists` и `unique`;
- `Componenta\Detector\MimeTypeDetectorInterface` включает `mime_type` и MIME-ограничения в `file`.

Все остальные встроенные правила работают без этих сервисов. При ручном создании `RuleFactory` обе зависимости также являются nullable-параметрами конструктора.

Правило `phone` входит в основной пакет. Его runtime-зависимость `giggsey/libphonenumber-for-php` объявлена непосредственно в `componenta/validation`.

## Пути полей и отсутствующие значения

Правила индексируются путями полей. Точка обозначает вложенность, а `*` — каждый существующий элемент коллекции:

```php
$validator = $factory->createFrom([
    'profile.email' => 'required|email',
    'items.*.sku' => 'required|string',
    'items.*.tags.*' => 'string|length:1,50',
]);
```

Отсутствующие вложенные листья проверяются как `null`. Например, `profile.email` не проходит `required`, когда отсутствует `profile` или `email`. Для `items.*.sku` каждый существующий элемент получает отдельную цель с отсутствующим значением, если в нём нет `sku`.

## Атрибуты

В development `AttributeValidationProvider` строит определения валидаторов по атрибутам свойств:

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

Приоритет имени поля:

1. `#[Field('name')]`;
2. `#[Validate(..., as: 'name')]`;
3. имя PHP-свойства.

Прямые rule-атрибуты создаются как правила. Наследники `RuleAttribute`, например `#[Exists]`, `#[Unique]` и `#[When]`, гидратируются через `RuleFactoryInterface`, поэтому правила с сервисными зависимостями получают настроенные зависимости.

Класс может делегировать проверку отдельному validator service:

```php
use Componenta\Validation\Attribute\ValidatedBy;

#[ValidatedBy(CreateUserValidator::class)]
final class CreateUserCommand
{
}
```

Указанный идентификатор разрешается через `ValidatorFactoryInterface::create()`, обычно из контейнера. Поэтому можно использовать явную фабрику, автоматически собираемый конкретный валидатор или интерфейс/service id, сопоставленный приложением. Одновременное объявление `#[ValidatedBy]` и правил на свойствах неоднозначно и отклоняется вместо молчаливого выбора одного источника.

## Development- и production-провайдеры

Порядок стандартных провайдеров сохраняет приоритет явной конфигурации приложения:

1. `ValidatableProvider` для `ValidatableInterface`;
2. `MappedValidationProvider` для `ConfigKey::VALIDATORS_MAP`;
3. `CompiledValidationProvider` для `ConfigKey::COMPILED_VALIDATORS`;
4. reflection-обнаружение атрибутов только в development.

`VALIDATORS_MAP` остаётся ручной картой `entry id → validator service`. `COMPILED_VALIDATORS` — версионированная карта дескрипторов, создаваемая интеграционным пакетом; это не набор сгенерированных классов валидаторов.

При установленном `componenta/validation-app` команда `app:build` в development сканирует атрибуты и записывает descriptor map в кеш конфигурации приложения. В production compiled provider гидратирует правила и делегирует создание обычному `ValidatorFactoryInterface`, поэтому продолжают применяться стандартные walker, formatter, locale, пользовательская фабрика правил и явные фабрики контейнера. Сервисы из `#[ValidatedBy]` одновременно передаются DI v4 как корни autowiring-компиляции. Явные фабрики приложения имеют приоритет над сгенерированными DI-фабриками.

Интеграционный пакет помечает compiled map как обязательную вне development. Отсутствующая или несовместимая карта вызывает ошибку при создании провайдера, а не молча отключает атрибутную валидацию. Повторно запускайте `app:build` после изменения атрибутов, validator services, aliases правил или их зависимостей.

## Строковый синтаксис правил

`RuleFactoryInterface` поддерживает:

```php
$rules->createRule('email');
$rules->createRule('required|email|length:5,255');
$rules->createRule('nullable|email');
$rules->createRule('oneof:email,phone');
$rules->createRule('arrayof:uuid');
```

Правила через `|` объединяются в `AllOf`. Nullable-правило компонуется тем же общим механизмом, который используют атрибуты и программные коллекции правил, поэтому эквивалентные способы создания имеют одинаковое поведение.

Строкового alias `ifthen` нет: безопасная строковая грамматика не может выразить произвольный callable-condition. Используйте декларативное правило `when`, атрибут `#[When]` или создавайте `IfThen` программно.

## Встроенные правила

Пакет содержит правила обязательности, типов, строк, чисел, сравнений, дат, массивов, условий, паролей, загрузки файлов, базы данных и композиции. Основные имена:

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

`OneOf` всегда проверяет альтернативы до первого успеха. `STOP_ON_FIRST_FAILURE_ATTRIBUTE` определяет только состав ошибок, когда не прошла ни одна альтернатива, но не меняет логический результат. Predicate-методы композитов проверяют дочерние правила через `RuleInterface::validate()`, поэтому пользовательскому правилу достаточно реализовать опубликованный интерфейс.

Стандартное правило `file` проверяет статус загрузки до размера и MIME. `MimeType` отклоняет ошибочную загрузку, не вызывая `getStream()`.

## Пользовательские правила

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

Регистрируйте пользовательское строковое правило через `RuleFactory::register()`, а aliases — через `RuleFactory::alias()`. Флаг `composite` нужен только тогда, когда параметры регистрации являются вложенными определениями правил.

## Ключи конфигурации

| Ключ | Назначение |
|---|---|
| `ConfigKey::VALIDATORS_MAP` | Явная карта `entry id → validator service`. |
| `ConfigKey::COMPILED_VALIDATORS` | Версионированная карта скомпилированных дескрипторов атрибутов. |
| `ConfigKey::DICTIONARY` | Пользовательский словарь сообщений. |
| `ConfigKey::USED_LOCALES` | Список загружаемых локалей. |
| `ConfigKey::DEFAULT_LOCALE` | Локаль formatter по умолчанию. |

Основной `ConfigProvider` регистрирует `ValidatorFactoryInterface`, `ValidationProviderInterface`, `RuleFactoryInterface`, `MessageFormatterInterface` и стандартную конфигурацию локалей.
