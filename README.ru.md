# Componenta Validation

Библиотека валидации для PHP 8.4+. Пакет предоставляет валидаторы, композицию правил, строковый синтаксис правил, атрибуты свойств, маппинг валидаторов, иммутабельный контекст валидации, локализованные сообщения и фабрики для интеграции с фреймворком.

## Установка

```bash
composer require componenta/validation
```

Composer-пакет устанавливает зависимости, которые нужны всем встроенным правилам.
Если пакет подключён через `Componenta\Validation\ConfigProvider`, контейнер
должен предоставить `Cycle\Database\DatabaseInterface` и
`Componenta\Detector\MimeTypeDetectorInterface`, потому что стандартная фабрика
правил создаётся с поддержкой базы данных и MIME-типов.

Если создавать `Componenta\Validation\Rule\RuleFactory` вручную, обе зависимости
являются опциональными аргументами конструктора. `null` отключает правила,
которым нужна соответствующая зависимость:

- `exists` и `unique` регистрируются только при наличии базы данных;
- `mime_type` и MIME-проверки внутри `file` регистрируются только при наличии детектора;
- регистрацию во фреймворке предоставляет `Componenta\Validation\ConfigProvider`.

## Связанные пакеты

`componenta/validation` можно использовать отдельно, но часть правил и интеграций опирается на соседние библиотеки:

| Пакет | Зачем нужен здесь |
|---|---|
| `componenta/di` | Нужен фабрикам, когда правило или валидатор создаётся через сервис из контейнера. Библиотека использует обычные callable-фабрики и не требует специальных DI-фабрик. |
| `cycle/database` | Предоставляет `Cycle\Database\DatabaseInterface` для правил `exists` и `unique`, которые проверяют записи в базе данных. |
| `componenta/mimetype-detector` | Предоставляет `MimeTypeDetectorInterface` для правил `mime_type` и `file`, если MIME-тип определяется не только по имени файла. |
| `componenta/config` | Используется `ConfigProvider`, чтобы зарегистрировать фабрики валидаторов, словари сообщений и локали во фреймворке. |
| Сопоставление запроса из `componenta/di` | Атрибуты валидации часто используются вместе с DTO, которые создаются из HTTP-запроса через DI-маппинг. Сама валидация не зависит от HTTP. |

## Быстрый старт

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

Во фреймворк-приложении можно получить `ValidatorFactoryInterface` из
контейнера и вызвать `createFrom()` с массивом `field => rules`:

```php
$validator = $factory->createFrom([
    'email' => 'required|email|length:5,255',
]);
```

Одиночная строка правила не является определением валидатора.

## Контракт валидатора

`ValidatorInterface::validate(iterable $data, ?ContextInterface $context = null)` возвращает:

- `true`, если данные прошли проверку;
- `ErrorMessageCollectorInterface`, если есть ошибки.

Если `ContextInterface::THROW_ON_FAILURE_ATTRIBUTE` равен `true`, валидатор
выбрасывает `ValidationException` вместо возврата коллектора ошибок.

```php
$result = $validator->validate(['email' => 'invalid']);

if ($result !== true) {
    $result->has('email');
    $result->get('email');
    $result->toArray();
}
```

`Validator` иммутабелен. Методы `withRules()`, `withWalker()`, `withFormatter()` и `withLocale()` возвращают новый валидатор.

## Пути полей и маска `*`

Правила задаются по пути поля. Вложенные массивы используют точечную нотацию. Маска `*` проверяет каждый элемент на этом уровне.

```php
$validator = $factory->createFrom([
    'user.email' => 'required|email',
    'users.*.email' => 'required|email',
    'tags.*' => 'string|length:1,50',
]);
```

Обходчик данных сообщает ошибки по полному пути, например `users.0.email`.

## Синтаксис правил

Правила создаёт `RuleFactoryInterface`.

```php
$ruleFactory->createRule('email');
$ruleFactory->createRule('required|email|length:5,255');
$ruleFactory->createRule('nullable|email');
$ruleFactory->createRule('oneof:email,phone');
```

Синтаксис:

- `name`
- `name:param1,param2`
- `ruleA|ruleB|ruleC`
- `regex:/^\d+$/`

Правила через `|` оборачиваются в `AllOf`: должны пройти все правила. `nullable|...` обрабатывается отдельно: `null` проходит сразу, а не-null значение должно пройти остальные правила.

Композитные строковые правила (`allof`, `oneof`, `arrayof`) читают параметры как вложенные правила. Остальные правила считают параметры обычными строками.

## Встроенные правила

| Правило | Параметры | Значение |
|---|---|---|
| `required` | нет | Значение должно присутствовать и быть непустым. |
| `nullable` | нет | `null` допустим. |
| `filled` | нет | Переданное значение не должно быть пустым. |
| `accepted` | нет | Допускает распространённые значения согласия. |
| `in` | `value,...` | Значение входит в список. |
| `not_in` | `value,...` | Значение не входит в список. |
| `string` | нет | Значение должно быть строкой. |
| `int`, `integer` | `strict?` | Проверка целого числа, опционально строгая. |
| `array` | `list?` | Проверка массива, опционально как списка без пропусков индексов. |
| `numeric` | нет | Числовое значение. |
| `boolean`, `bool` | `strict?` | Проверка логического значения, опционально строгая. |
| `email` | нет | Email-адрес. |
| `url` | `scheme,...?` | URL, опционально с ограничением схем. |
| `regex` | `pattern` | Совпадение с регулярным выражением. |
| `uuid` | `version?` | UUID, опционально конкретной версии. |
| `alpha` | `ascii?` | Только буквы. |
| `alpha_num` | `ascii?` | Буквы и цифры. |
| `alpha_dash` | `ascii?` | Буквы, цифры, дефис и подчёркивание. |
| `length` | `min?,max?` | Ограничения длины строки. |
| `phone` | `region?` | Проверка телефонного номера. |
| `range` | `min?,max?` | Числовой диапазон. |
| `min` | `min` | Нижняя числовая граница. |
| `max` | `max` | Верхняя числовая граница. |
| `positive` | `orZero?` | Положительное число. |
| `negative` | `orZero?` | Отрицательное число. |
| `equals` | `field,strict?` | Равно другому полю. |
| `not_equals` | `field,strict?` | Не равно другому полю. |
| `gt`, `gte` | `field` | Больше / больше или равно другому полю. |
| `lt`, `lte` | `field` | Меньше / меньше или равно другому полю. |
| `confirmed` | `suffix?` | Совпадает с confirmation-полем. |
| `date` | нет | Значение похоже на дату. |
| `date_format` | `format` | Дата в точном формате. |
| `before` | `date,orEqual?,graceMinutes?` | Дата раньше целевой. |
| `before_or_equal` | `date,graceMinutes?` | Дата раньше или равна целевой. |
| `after` | `date,orEqual?,graceMinutes?` | Дата позже целевой. |
| `after_or_equal` | `date,graceMinutes?` | Дата позже или равна целевой. |
| `count` | `min?,max?` | Размер массива или countable. |
| `distinct` | `field?` | Значения массива уникальны. |
| `required_if` | `field,value` | Обязательно, когда другое поле равно значению. |
| `required_with` | `field,...` | Обязательно, когда присутствует любое из указанных полей. |
| `required_without` | `field,...` | Обязательно, когда указанные поля отсутствуют. |
| `prohibited_if` | `field,value` | Должно отсутствовать, когда условие совпадает. |
| `exclude_if` | `field,value` | Исключает значение, когда условие совпадает. |
| `when` | `field:value,then,else?` | Условные вложенные правила. |
| `password` | `min?,flags?,confirmation?` | Проверка сложности пароля. |
| `uploaded_file` | нет | Загруженный файл PSR-7. |
| `file_size` | `max,min?` | Размер загруженного файла. |
| `mime_type` | `type,...` | MIME-тип через детектор. |
| `file` | `max?,mime,...` | Комбинированная проверка файла. |
| `exists` | `table,column?` | Существование записи в базе. |
| `unique` | `table,column?` | Уникальность в базе. |
| `allof` | `rule,...` | Все вложенные правила должны пройти. |
| `oneof` | `rule,...` | Хотя бы одно вложенное правило должно пройти. |
| `arrayof` | `rule` | Каждый элемент массива должен пройти вложенное правило. |
| `ifthen` | программное использование | Условный класс правила для callable-условий. В обычной строке правила безопасно выразить PHP callable нельзя. |

## Атрибутная валидация

`AttributeValidationProvider` строит валидаторы из атрибутов свойств класса.
Классы атрибутов также разрешены на параметрах, чтобы другие слои фреймворка
могли переиспользовать те же метаданные, но этот провайдер сам сканирует только
свойства.

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

Порядок выбора имени поля:

1. `#[Field('name')]`
2. `#[Validate(..., as: 'name')]`
3. имя PHP-свойства

Типы атрибутов:

| Атрибут | Цель | Значение |
|---|---|---|
| `#[Validate]` | свойство, параметр | Строковое определение правил. `AttributeValidationProvider` читает его со свойств. |
| `#[Field]` | свойство, параметр | Переопределяет имя поля валидации. `AttributeValidationProvider` читает его со свойств. |
| `#[ValidatedBy]` | класс | Связывает класс с классом валидатора. |
| `#[Exists]` | свойство, параметр | Метаданные правила exists. |
| `#[Unique]` | свойство, параметр | Метаданные правила unique. |
| `#[When]` | свойство, параметр, повторяемый | Метаданные условного правила. |
| прямые атрибуты правил | свойство, параметр | Объекты правил создаются напрямую. |

Правила, которым нужны сервисы, должны использовать наследников `RuleAttribute`, чтобы настоящее правило строилось через `RuleFactory`.

## Провайдеры валидаторов

`ValidationProviderInterface` находит валидатор по идентификатору, обычно по имени класса.

| Провайдер | Источник |
|---|---|
| `ValidatableProvider` | `ValidatableInterface::createValidator()`. |
| `MappedValidationProvider` | `ConfigKey::VALIDATORS_MAP`. |
| `AttributeValidationProvider` | Атрибуты свойств. |
| `ValidatedByProvider` | `#[ValidatedBy]`. |
| `CompositeValidationProvider` | Пробует провайдеры по порядку. |

`ValidationProviderFactory` строит разные цепочки:

- разработка: `ValidatableInterface`, карта валидаторов, атрибуты, `#[ValidatedBy]`;
- продакшен: `ValidatableInterface` и карта валидаторов.

Провайдер, который читает атрибуты через рефлексию, по умолчанию не используется в продакшене.

## Свои правила

Правило реализует `RuleInterface`.

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

Регистрация через `RuleFactory::register()`:

```php
$ruleFactory->register('uppercase', static fn (): RuleInterface => new Uppercase());
$ruleFactory->alias('upper', 'uppercase');
```

Для композитных правил, параметры которых нужно разбирать как вложенные правила, передайте `composite: true`.

## Контекст

`Context` иммутабелен. Используйте `withAttribute()` и `withAttributes()`, чтобы получить изменённый контекст.

```php
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;

$context = new Context([
    ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE => true,
    ContextInterface::THROW_ON_FAILURE_ATTRIBUTE => true,
]);
```

Важные атрибуты контекста:

| Атрибут | Значение |
|---|---|
| `VALIDATION_DATA_ATTRIBUTE` | Исходные данные. |
| `VALIDATION_RULES_ATTRIBUTE` | Коллекция правил. |
| `CURRENT_PATH_ATTRIBUTE` | Текущий полный путь поля. |
| `CURRENT_FIELD_ATTRIBUTE` | Текущее имя поля. |
| `CURRENT_RULE_ATTRIBUTE` | Текущее имя правила. |
| `PROCESSED_RULES_ATTRIBUTE` | Обработанные правила. |
| `PROCESSED_FIELDS_ATTRIBUTE` | Обработанные пути полей. |
| `SKIP_MISSING_RULES_ATTRIBUTE` | Пропускать поля без правил. |
| `STOP_ON_FIRST_FAILURE_ATTRIBUTE` | Остановиться на первой ошибке. |
| `THROW_ON_FAILURE_ATTRIBUTE` | Выбросить `ValidationException` при ошибке. |
| `MESSAGE_FORMATTER_ATTRIBUTE` | Форматтер сообщений. |
| `LOCALE_ATTRIBUTE` | Ключ локали. |

## Сообщения

`MessageFormatterInterface` форматирует ключи ошибок через словари. Если `ConfigKey::DICTIONARY` пустой, `MessageFormatterFactory` загружает стандартные словари для настроенных локалей.

Ключи конфигурации:

| Ключ | Значение |
|---|---|
| `ConfigKey::VALIDATORS_MAP` | Карта class -> validator. |
| `ConfigKey::DICTIONARY` | Пользовательский словарь сообщений. |
| `ConfigKey::USED_LOCALES` | Загружаемые локали. |
| `ConfigKey::DEFAULT_LOCALE` | Локаль по умолчанию. |

Стандартные константы локалей: `LOCALE_EN`, `LOCALE_RU`, `LOCALE_ES`, `LOCALE_FR`, `LOCALE_DE`.

## ConfigProvider

`Componenta\Validation\ConfigProvider` регистрирует:

- `ValidatorFactoryInterface`
- `ValidationProviderInterface`
- `RuleFactoryInterface`
- `MessageFormatterInterface`
- стандартную конфигурацию локалей

Фабрика правил создаётся через обычные callable-фабрики; специальные ленивые фабрики сервисов из DI не требуются.
