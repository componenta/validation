<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Detector\MimeTypeDetectorInterface;
use Cycle\Database\DatabaseInterface;
use InvalidArgumentException;

/** Creates validation rules from the package's declarative string grammar. */
final class RuleFactory implements RuleFactoryInterface
{
    /** @var array<string, callable(array): RuleInterface> */
    private array $factories = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, true> */
    private array $compositeRules = [];

    public function __construct(
        private readonly ?DatabaseInterface $database = null,
        private readonly ?MimeTypeDetectorInterface $detector = null,
    ) {
        $this->registerDefaults();
    }

    public function createRule(string $definition): RuleInterface
    {
        $definition = trim($definition);
        if ($definition === '') {
            throw new InvalidArgumentException('Rule definition cannot be empty.');
        }

        $lower = strtolower($definition);
        foreach ([
            'allof:' => AllOf::class,
            'all_of:' => AllOf::class,
            'oneof:' => OneOf::class,
            'one_of:' => OneOf::class,
        ] as $prefix => $composite) {
            if (!str_starts_with($lower, $prefix)) {
                continue;
            }

            $children = array_map(
                $this->createRule(...),
                $this->splitCompositeRules(substr($definition, strlen($prefix))),
            );

            return $composite === AllOf::class
                ? RuleComposer::all(...$children)
                : new OneOf(...$children);
        }

        foreach (['arrayof:', 'array_of:'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return new ArrayOf($this->createRule(substr($definition, strlen($prefix))));
            }
        }

        $parts = $this->splitByPipe($definition);
        if (count($parts) === 1) {
            return $this->createSingleRule($definition);
        }

        $nullable = null;
        $rules = [];

        foreach ($parts as $part) {
            $rule = $this->createSingleRule($part);
            if ($nullable === null && $rule instanceof Nullable) {
                $nullable = $rule;
                continue;
            }

            $rules[] = $rule;
        }

        if ($rules === []) {
            return $nullable ?? throw new InvalidArgumentException('Composite rule contains no rules.');
        }

        return $nullable === null
            ? RuleComposer::all(...$rules)
            : RuleComposer::all($nullable, ...$rules);
    }

    /**
     * @param string|string[] $names
     * @param callable(array): RuleInterface $ruleFactory
     */
    public function register(
        string|array $names,
        callable $ruleFactory,
        bool $composite = false,
    ): RuleFactory {
        $names = is_array($names) ? array_values($names) : [$names];
        $name = array_shift($names);

        if (!is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('Rule name must be a non-empty string.');
        }

        $name = strtolower($name);
        $this->factories[$name] = $ruleFactory;
        if ($composite) {
            $this->compositeRules[$name] = true;
        }

        foreach ($names as $alias) {
            $this->alias($alias, $name, $composite);
        }

        return $this;
    }

    public function alias(string $alias, string $target, bool $composite = false): RuleFactory
    {
        $alias = strtolower(trim($alias));
        $target = strtolower(trim($target));

        if ($alias === '' || !isset($this->factories[$target])) {
            throw new InvalidArgumentException(sprintf('Unknown rule: "%s"', $target));
        }

        $this->aliases[$alias] = $target;
        if ($composite || isset($this->compositeRules[$target])) {
            $this->compositeRules[$alias] = true;
        }

        return $this;
    }

    public function has(string $name): bool
    {
        $name = strtolower($name);

        return isset($this->factories[$name]) || isset($this->aliases[$name]);
    }

    public function createRules(array $definitions): array
    {
        $rules = [];

        foreach ($definitions as $field => $definition) {
            if ($definition instanceof RuleInterface) {
                $rules[$field] = $definition;
                continue;
            }

            if (!is_string($definition)) {
                throw new InvalidArgumentException(sprintf(
                    'Rule definition for field "%s" must be a string or %s; got %s.',
                    (string) $field,
                    RuleInterface::class,
                    get_debug_type($definition),
                ));
            }

            $rules[$field] = $this->createRule($definition);
        }

        return $rules;
    }

    private function createSingleRule(string $definition): RuleInterface
    {
        $definition = trim($definition);

        if (str_starts_with(strtolower($definition), 'regex:')) {
            return new Regex(substr($definition, 6));
        }

        $colon = strpos($definition, ':');
        $name = strtolower($colon === false ? $definition : substr($definition, 0, $colon));
        $factory = $this->resolveFactory($name);
        if ($factory === null) {
            throw new InvalidArgumentException(sprintf('Unknown rule: "%s"', $name));
        }

        $params = $colon === false
            ? []
            : $this->parseParams(substr($definition, $colon + 1), $name);

        return $factory($params);
    }

    /** @return callable(array): RuleInterface|null */
    private function resolveFactory(string $name): ?callable
    {
        if (isset($this->factories[$name])) {
            return $this->factories[$name];
        }

        $target = $this->aliases[$name] ?? null;

        return $target === null ? null : $this->factories[$target] ?? null;
    }

    /** @return list<RuleInterface|string> */
    private function parseParams(string $content, string $parentRule): array
    {
        $params = [];
        $nested = isset($this->compositeRules[$parentRule]);

        foreach ($this->splitByComma($content) as $param) {
            if ($nested) {
                $colon = strpos($param, ':');
                $ruleName = strtolower($colon === false ? $param : substr($param, 0, $colon));
                if ($ruleName !== '' && !is_numeric($ruleName) && $this->has($ruleName)) {
                    $params[] = $this->createRule($param);
                    continue;
                }
            }

            $params[] = $param;
        }

        return $params;
    }

    /** @return non-empty-list<string> */
    private function splitCompositeRules(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('Composite rule requires at least one nested rule.');
        }

        if (str_contains($content, '|')) {
            return $this->splitByPipe($content);
        }

        if (!str_contains($content, ',')) {
            return [$content];
        }

        $children = array_values(array_filter(
            array_map('trim', explode(',', $content)),
            static fn (string $child): bool => $child !== '',
        ));

        if (count($children) > 1 && array_all(
            $children,
            fn (string $child): bool => !str_contains($child, ':') && $this->has($child),
        )) {
            return $children;
        }

        // Parameterized nested rules must use the unambiguous pipe separator,
        // for example: oneof:email|length:2,100.
        return [$content];
    }

    /** @return non-empty-list<string> */
    private function splitByPipe(string $content): array
    {
        if (str_starts_with(strtolower($content), 'regex:')) {
            $regexEnd = $this->findRegexEnd($content, 6);
            if ($regexEnd === false || $regexEnd >= strlen($content) - 1) {
                return [$content];
            }

            $regex = substr($content, 0, $regexEnd + 1);
            $rest = substr($content, $regexEnd + 1);

            return str_starts_with($rest, '|')
                ? [$regex, ...$this->splitByPipe(substr($rest, 1))]
                : [$content];
        }

        $parts = array_values(array_filter(
            array_map('trim', explode('|', $content)),
            static fn (string $part): bool => $part !== '',
        ));

        return $parts === [] ? [''] : $parts;
    }

    /** @return list<string> */
    private function splitByComma(string $content): array
    {
        if (str_contains($content, '|')) {
            return [$content];
        }

        return array_map('trim', explode(',', $content));
    }

    private function findRegexEnd(string $content, int $start): int|false
    {
        if (!isset($content[$start])) {
            return false;
        }

        $delimiter = $content[$start];
        $escaped = false;

        for ($index = $start + 1, $length = strlen($content); $index < $length; $index++) {
            $character = $content[$index];
            if ($character === $delimiter && !$escaped) {
                while (isset($content[$index + 1])
                    && preg_match('/[imsxADSUXJu]/', $content[$index + 1]) === 1
                ) {
                    $index++;
                }

                return $index;
            }

            $escaped = $character === '\\' && !$escaped;
            if ($character !== '\\') {
                $escaped = false;
            }
        }

        return false;
    }

    private function registerDefaults(): void
    {
        $this->register(['allof', 'all_of'], static fn (array $params): RuleInterface => RuleComposer::all(...$params), composite: true);
        $this->register(['oneof', 'one_of'], static fn (array $params): RuleInterface => new OneOf(...$params), composite: true);
        $this->register(['arrayof', 'array_of'], static fn (array $params): RuleInterface => new ArrayOf(
            $params[0] ?? throw new InvalidArgumentException('arrayof requires a nested rule.'),
        ), composite: true);

        $this->factories['required'] = static fn (): RuleInterface => new Required();
        $this->factories['nullable'] = static fn (): RuleInterface => new Nullable();
        $this->factories['filled'] = static fn (): RuleInterface => new Filled();
        $this->factories['in'] = static fn (array $params): RuleInterface => new In($params);
        $this->factories['not_in'] = static fn (array $params): RuleInterface => new NotIn($params);
        $this->factories['accepted'] = static fn (): RuleInterface => new Accepted();

        $this->factories['string'] = static fn (): RuleInterface => new IsString();
        $this->factories['int'] = static fn (array $params): RuleInterface => new IsInt(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'strict'], true),
        );
        $this->factories['array'] = static fn (array $params): RuleInterface => new IsArray(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'list'], true),
        );
        $this->factories['numeric'] = static fn (): RuleInterface => new Numeric();
        $this->factories['boolean'] = static fn (array $params): RuleInterface => new IsBoolean(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'strict'], true),
        );
        $this->alias('integer', 'int');
        $this->alias('is_int', 'int');
        $this->alias('is_string', 'string');
        $this->alias('is_array', 'array');
        $this->alias('bool', 'boolean');
        $this->alias('is_bool', 'boolean');

        $this->factories['email'] = static fn (): RuleInterface => new Email();
        $this->factories['url'] = static fn (array $params): RuleInterface => $params === [] ? new Url() : new Url($params);
        $this->factories['regex'] = static fn (array $params): RuleInterface => new Regex((string) ($params[0] ?? '//'));
        $this->factories['uuid'] = static fn (array $params): RuleInterface => new Uuid(
            isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null,
        );
        $this->factories['alpha'] = static fn (array $params): RuleInterface => new Alpha(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'ascii'], true),
        );
        $this->factories['alpha_num'] = static fn (array $params): RuleInterface => new AlphaNumeric(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'ascii'], true),
        );
        $this->factories['alpha_dash'] = static fn (array $params): RuleInterface => new AlphaDash(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1', 'ascii'], true),
        );
        $this->factories['length'] = static fn (array $params): RuleInterface => new Length(
            min: isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null,
            max: isset($params[1]) && $params[1] !== '' ? (int) $params[1] : null,
        );
        $this->factories['phone'] = static fn (array $params): RuleInterface => new Phone(
            isset($params[0]) && $params[0] !== '' ? (string) $params[0] : null,
        );

        $this->factories['range'] = fn (array $params): RuleInterface => new Range(
            min: isset($params[0]) && $params[0] !== '' ? $this->toNumber((string) $params[0]) : null,
            max: isset($params[1]) && $params[1] !== '' ? $this->toNumber((string) $params[1]) : null,
        );
        $this->factories['min'] = fn (array $params): RuleInterface => new Range(
            min: isset($params[0]) && $params[0] !== '' ? $this->toNumber((string) $params[0]) : null,
        );
        $this->factories['max'] = fn (array $params): RuleInterface => new Range(
            max: isset($params[0]) && $params[0] !== '' ? $this->toNumber((string) $params[0]) : null,
        );
        $this->factories['positive'] = static fn (array $params): RuleInterface => new Positive(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1'], true),
        );
        $this->factories['negative'] = static fn (array $params): RuleInterface => new Negative(
            isset($params[0]) && in_array(strtolower((string) $params[0]), ['true', '1'], true),
        );

        $this->factories['equals'] = static fn (array $params): RuleInterface => new Equals(
            (string) ($params[0] ?? throw new InvalidArgumentException('equals requires field.')),
            !isset($params[1]) || !in_array(strtolower((string) $params[1]), ['false', '0'], true),
        );
        $this->factories['not_equals'] = static fn (array $params): RuleInterface => new NotEquals(
            (string) ($params[0] ?? throw new InvalidArgumentException('not_equals requires field.')),
            !isset($params[1]) || !in_array(strtolower((string) $params[1]), ['false', '0'], true),
        );
        $this->factories['gt'] = static fn (array $params): RuleInterface => new GreaterThan(
            (string) ($params[0] ?? throw new InvalidArgumentException('gt requires field.')),
            isset($params[1]) && in_array(strtolower((string) $params[1]), ['true', '1'], true),
        );
        $this->factories['gte'] = static fn (array $params): RuleInterface => new GreaterThan(
            (string) ($params[0] ?? throw new InvalidArgumentException('gte requires field.')),
            orEqual: true,
        );
        $this->factories['lt'] = static fn (array $params): RuleInterface => new LessThan(
            (string) ($params[0] ?? throw new InvalidArgumentException('lt requires field.')),
            isset($params[1]) && in_array(strtolower((string) $params[1]), ['true', '1'], true),
        );
        $this->factories['lte'] = static fn (array $params): RuleInterface => new LessThan(
            (string) ($params[0] ?? throw new InvalidArgumentException('lte requires field.')),
            orEqual: true,
        );
        $this->factories['confirmed'] = static fn (array $params): RuleInterface => new Confirmed((string) ($params[0] ?? 'confirmation'));

        $this->factories['date'] = static fn (): RuleInterface => new Date();
        $this->factories['date_format'] = static fn (array $params): RuleInterface => new DateFormat(
            (string) ($params[0] ?? throw new InvalidArgumentException('date_format requires format.')),
        );
        $this->factories['before'] = static fn (array $params): RuleInterface => new Before(
            $params[0] ?? throw new InvalidArgumentException('before requires date.'),
            isset($params[1]) && in_array(strtolower((string) $params[1]), ['true', '1'], true),
            isset($params[2]) && $params[2] !== '' ? (int) $params[2] : 0,
        );
        $this->factories['before_or_equal'] = static fn (array $params): RuleInterface => new Before(
            $params[0] ?? throw new InvalidArgumentException('before_or_equal requires date.'),
            orEqual: true,
            graceMinutes: isset($params[1]) && $params[1] !== '' ? (int) $params[1] : 0,
        );
        $this->factories['after'] = static fn (array $params): RuleInterface => new After(
            $params[0] ?? throw new InvalidArgumentException('after requires date.'),
            isset($params[1]) && in_array(strtolower((string) $params[1]), ['true', '1'], true),
            isset($params[2]) && $params[2] !== '' ? (int) $params[2] : 0,
        );
        $this->factories['after_or_equal'] = static fn (array $params): RuleInterface => new After(
            $params[0] ?? throw new InvalidArgumentException('after_or_equal requires date.'),
            orEqual: true,
            graceMinutes: isset($params[1]) && $params[1] !== '' ? (int) $params[1] : 0,
        );

        $this->factories['count'] = static fn (array $params): RuleInterface => new Count(
            min: isset($params[0]) && $params[0] !== '' ? (int) $params[0] : null,
            max: isset($params[1]) && $params[1] !== '' ? (int) $params[1] : null,
        );
        $this->factories['distinct'] = static fn (array $params): RuleInterface => new Distinct(
            isset($params[0]) && $params[0] !== '' ? (string) $params[0] : null,
        );

        $this->factories['required_if'] = fn (array $params): RuleInterface => new RequiredIf(
            (string) ($params[0] ?? throw new InvalidArgumentException('required_if requires field.')),
            $this->castValue((string) ($params[1] ?? throw new InvalidArgumentException('required_if requires value.'))),
        );
        $this->factories['required_with'] = static fn (array $params): RuleInterface => $params === []
            ? throw new InvalidArgumentException('required_with requires fields.')
            : new RequiredWith(array_map('strval', $params));
        $this->factories['required_without'] = static fn (array $params): RuleInterface => $params === []
            ? throw new InvalidArgumentException('required_without requires fields.')
            : new RequiredWithout(array_map('strval', $params));
        $this->factories['prohibited_if'] = fn (array $params): RuleInterface => new ProhibitedIf(
            (string) ($params[0] ?? throw new InvalidArgumentException('prohibited_if requires field.')),
            $this->castValue((string) ($params[1] ?? throw new InvalidArgumentException('prohibited_if requires value.'))),
        );
        $this->factories['exclude_if'] = fn (array $params): RuleInterface => new ExcludeIf(
            (string) ($params[0] ?? throw new InvalidArgumentException('exclude_if requires field.')),
            $this->castValue((string) ($params[1] ?? throw new InvalidArgumentException('exclude_if requires value.'))),
        );
        $this->factories['when'] = fn (array $params): RuleInterface => $this->createWhenRule($params);

        $this->factories['password'] = fn (array $params): RuleInterface => new Password(
            min: isset($params[0]) && $params[0] !== '' ? (int) $params[0] : 8,
            flags: isset($params[1]) && $params[1] !== ''
                ? $this->parsePasswordFlags((string) $params[1])
                : Password::REQUIRE_UPPER | Password::REQUIRE_LOWER | Password::REQUIRE_DIGIT | Password::REQUIRE_SPECIAL,
            confirmationField: isset($params[2]) && $params[2] !== '' ? (string) $params[2] : null,
        );

        $this->factories['uploaded_file'] = static fn (): RuleInterface => new UploadedFile();
        $this->factories['file_size'] = static fn (array $params): RuleInterface => new FileSize(
            max: FileSize::parseSize((string) ($params[0] ?? throw new InvalidArgumentException('file_size requires max size.'))),
            min: isset($params[1]) && $params[1] !== '' ? FileSize::parseSize((string) $params[1]) : 0,
        );

        if ($this->detector !== null) {
            $this->factories['mime_type'] = fn (array $params): RuleInterface => $params === []
                ? throw new InvalidArgumentException('mime_type requires at least one type.')
                : new MimeType($this->detector, array_map('strval', $params));
        }

        $this->factories['file'] = function (array $params): RuleInterface {
            $rules = [new UploadedFile()];
            if (isset($params[0]) && $params[0] !== '' && $params[0] !== '0') {
                $rules[] = new FileSize(max: FileSize::parseSize((string) $params[0]));
            }

            $mimeTypes = array_values(array_filter(
                array_map('strval', array_slice($params, 1)),
                static fn (string $value): bool => str_contains($value, '/'),
            ));
            if ($mimeTypes !== []) {
                if ($this->detector === null) {
                    throw new InvalidArgumentException('The file rule requires a MIME detector when MIME types are configured.');
                }

                $rules[] = new MimeType($this->detector, $mimeTypes);
            }

            return count($rules) === 1 ? $rules[0] : new Sequential(...$rules);
        };

        if ($this->database !== null) {
            $this->factories['exists'] = fn (array $params): RuleInterface => new Exists(
                $this->database,
                (string) ($params[0] ?? throw new InvalidArgumentException('exists requires table.')),
                (string) ($params[1] ?? 'id'),
            );
            $this->factories['unique'] = fn (array $params): RuleInterface => new Unique(
                $this->database,
                (string) ($params[0] ?? throw new InvalidArgumentException('unique requires table.')),
                (string) ($params[1] ?? 'id'),
            );
        }
    }

    private function toNumber(string $value): int|float
    {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function castValue(string $value): mixed
    {
        $lower = strtolower($value);

        return match (true) {
            $lower === 'true' => true,
            $lower === 'false' => false,
            $lower === 'null' => null,
            is_numeric($value) => str_contains($value, '.') ? (float) $value : (int) $value,
            default => $value,
        };
    }

    private function parsePasswordFlags(string $flags): int
    {
        $result = 0;

        foreach (explode('+', strtolower($flags)) as $part) {
            $result |= match (trim($part)) {
                'upper', 'u' => Password::REQUIRE_UPPER,
                'lower', 'l' => Password::REQUIRE_LOWER,
                'digit', 'd', 'number', 'n' => Password::REQUIRE_DIGIT,
                'special', 's', 'symbol' => Password::REQUIRE_SPECIAL,
                'all' => Password::REQUIRE_UPPER | Password::REQUIRE_LOWER | Password::REQUIRE_DIGIT | Password::REQUIRE_SPECIAL,
                'letters' => Password::REQUIRE_UPPER | Password::REQUIRE_LOWER,
                default => 0,
            };
        }

        return $result;
    }

    /** @param list<RuleInterface|string> $params */
    private function createWhenRule(array $params): When
    {
        $condition = (string) ($params[0] ?? throw new InvalidArgumentException('when requires condition (field:value).'));
        $then = (string) ($params[1] ?? throw new InvalidArgumentException('when requires then rules.'));
        $else = isset($params[2]) && $params[2] !== '' ? (string) $params[2] : null;

        return new When(
            $condition,
            $this->createRule($then),
            $else === null ? new Nullable() : $this->createRule($else),
        );
    }
}
