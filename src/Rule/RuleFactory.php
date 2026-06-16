<?php

namespace Componenta\Validation\Rule;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\ContextInterface;
use Cycle\Database\DatabaseInterface;
use InvalidArgumentException;

/**
 * Factory for creating rules from string definitions.
 *
 * Syntax: rule or rule:param1,param2,...
 * Multiple rules separated by "|"
 *
 * Auto-detection of nested rules in parameters only applies to composite rules
 * (registered with `composite: true`). For all other rules, parameters are plain strings.
 *
 * Examples:
 *   "required"                          -> Required
 *   "length:8,255"                      -> Length(8, 255)
 *   "in:draft,published"                -> In(['draft', 'published'])
 *   "required|email"                    -> AllOf(Required, Email)
 *   "required|email|length:,255"        -> AllOf(Required, Email, Length)
 *   "allof:required|email"              -> AllOf(Required, Email)
 *   "allof:required|length:2,100"       -> AllOf(Required, Length)
 *   "arrayof:email"                     -> ArrayOf(Email)
 *   "regex:/^\d+$/"                     -> Regex('/^\d+$/')
 */
final class RuleFactory implements RuleFactoryInterface
{
    /** @var array<string, callable(array): RuleInterface> */
    private array $factories = [];
    private array $aliases = [];
    /** @var array<string, true> Rules whose parameters are nested rules, not plain strings. */
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
            throw new InvalidArgumentException('Rule definition cannot be empty');
        }

        // Multiple rules: "required|email" -> AllOf (default)
        // But if nullable is present: "nullable|email" -> OneOf
        $parts = $this->splitByPipe($definition);

        if (count($parts) > 1) {
            $nullableRule = null;
            $rules = [];

            foreach ($parts as $p) {
                $rule = $this->createSingleRule($p);
                // Check if this is the nullable rule (use instanceof for reliability)
                if ($nullableRule === null && $rule instanceof Nullable) {
                    $nullableRule = $rule;
                    continue;
                }

                $rules[] = $rule;
            }

            return $nullableRule === null ? new AllOf(...$rules)
                : new IfThen($nullableRule->inverse(...), new AllOf(...$rules));
        }

        return $this->createSingleRule($definition);
    }

    private function createSingleRule(string $definition): RuleInterface
    {
        $definition = trim($definition);

        // Parse: name or name:params
        if (str_starts_with($definition, 'regex:')) {
            // Special case for regex (contains colons)
            return new Regex(substr($definition, 6));
        }

        $colonPos = strpos($definition, ':');

        if ($colonPos === false) {
            // No params
            $name = strtolower($definition);
            $factory = $this->resolveFactory($name);

            if ($factory === null) {
                throw new InvalidArgumentException(sprintf('Unknown rule: "%s"', $name));
            }

            return $factory([]);
        }

        $name = strtolower(substr($definition, 0, $colonPos));
        $paramsString = substr($definition, $colonPos + 1);

        $factory = $this->resolveFactory($name);

        if ($factory === null) {
            throw new InvalidArgumentException(sprintf('Unknown rule: "%s"', $name));
        }

        $params = $this->parseParams($paramsString, $name);

        return $factory($params);
    }

    /**
     * Resolve factory by name or alias.
     */
    private function resolveFactory(string $name): ?callable
    {
        // Try direct factory lookup
        if (isset($this->factories[$name])) {
            return $this->factories[$name];
        }

        // Try alias lookup
        if (isset($this->aliases[$name])) {
            $target = $this->aliases[$name];
            return $this->factories[$target] ?? null;
        }

        return null;
    }

    /**
     * @param string|string[] $names
     * @param callable(array): RuleInterface $ruleFactory
     * @param bool $composite Whether parameters should be auto-detected as nested rules
     */
    public function register(string|array $names, callable $ruleFactory, bool $composite = false): RuleFactory
    {
        $names = is_array($names) ? $names : [$names];

        $name = array_shift($names);
        $this->factories[strtolower($name)] = $ruleFactory;

        if ($composite) {
            $this->compositeRules[strtolower($name)] = true;
        }

        foreach ($names as $alias) {
            $this->alias($alias, $name, $composite);
        }

        return $this;
    }

    public function alias(string $alias, string $target, bool $composite = false): RuleFactory
    {
        $alias = strtolower($alias);
        $target = strtolower($target);

        if (!isset($this->factories[$target])) {
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

    /**
     * Parse params, auto-detecting rules vs strings.
     *
     * Auto-detection of nested rules only applies to composite rules (allof, oneof, arrayof, ifthen).
     * For all other rules, parameters are always treated as plain strings.
     *
     * @return array<int, RuleInterface|string>
     */
    private function parseParams(string $content, string $parentRule = ''): array
    {
        $result = [];
        $autoDetectRules = isset($this->compositeRules[$parentRule]);

        foreach ($this->splitByComma($content) as $param) {
            if ($autoDetectRules) {
                // Extract rule name (e.g., "length:8,255" -> "length", "required" -> "required")
                $colonPos = strpos($param, ':');
                $ruleName = $colonPos !== false
                    ? strtolower(substr($param, 0, $colonPos))
                    : strtolower($param);

                // Check if it's a known rule (and not purely numeric/empty)
                if ($ruleName !== '' && !is_numeric($ruleName) && $this->has($ruleName)) {
                    $result[] = $this->createRule($param);
                    continue;
                }
            }

            $result[] = $param;
        }

        return $result;
    }

    /**
     * Split by pipe "|", respecting regex patterns.
     */
    private function splitByPipe(string $content): array
    {
        // Handle regex specially
        if (str_starts_with($content, 'regex:')) {
            $regexEnd = $this->findRegexEnd($content, 6);
            if ($regexEnd !== false && $regexEnd < strlen($content) - 1) {
                $regexPart = substr($content, 0, $regexEnd + 1);
                $rest = substr($content, $regexEnd + 1);
                if (str_starts_with($rest, '|')) {
                    return array_merge([$regexPart], $this->splitByPipe(substr($rest, 1)));
                }
            }
            return [$content];
        }

        $result = [];
        $current = '';
        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            $char = $content[$i];

            if ($char === '|') {
                if ($current !== '') {
                    $result[] = trim($current);
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        if ($current !== '') {
            $result[] = trim($current);
        }

        return $result;
    }

    /**
     * Split by comma ",", but not inside nested rules.
     */
    private function splitByComma(string $content): array
    {
        // Check for pipe - if present, this is a nested rule list
        if (str_contains($content, '|')) {
            return [$content];
        }

        $result = [];
        $current = '';
        $len = strlen($content);

        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];

            if ($char === ',') {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $result[] = trim($current);

        return $result;
    }

    /**
     * Find end of regex pattern (closing delimiter + modifiers).
     */
    private function findRegexEnd(string $content, int $start): int|false
    {
        $len = strlen($content);
        if ($start >= $len) {
            return false;
        }

        $delimiter = $content[$start];
        $i = $start + 1;

        while ($i < $len) {
            if ($content[$i] === $delimiter && $content[$i - 1] !== '\\') {
                // Skip modifiers
                while (isset($content[$i + 1]) && preg_match('/[imsxADSUXJu]/', $content[$i + 1])) {
                    $i++;
                }
                return $i;
            }
            $i++;
        }

        return false;
    }

    private function registerDefaults(): void
    {
        // Composite (parameters are auto-detected as nested rules)
        $this->register(['allof', 'all_of'], static fn(array $p) => new AllOf(...$p), composite: true);
        $this->register(['oneof', 'one_of'], static fn(array $p) => new OneOf(...$p), composite: true);
        $this->register(['arrayof', 'array_of'], static fn(array $p) => new ArrayOf($p[0]), composite: true);
        $this->register(['ifthen', 'if_then'], static fn(array $p) => new IfThen($p[0], $p[1], $p[2] ?? null), composite: true);

        // Basic
        $this->factories['required'] = static fn() => new Required();
        $this->factories['nullable'] = static fn() => new Nullable();
        $this->factories['filled'] = static fn() => new Filled();
        $this->factories['in'] = static fn(array $p) => new In($p);
        $this->factories['not_in'] = static fn(array $p) => new NotIn($p);
        $this->factories['accepted'] = static fn() => new Accepted();

        // Type
        $this->factories['string'] = static fn() => new IsString();
        $this->factories['int'] = static fn(array $p) => new IsInt(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'strict'], true)
        );
        $this->factories['array'] = static fn(array $p) => new IsArray(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'list'], true)
        );
        $this->factories['numeric'] = static fn() => new Numeric();
        $this->factories['boolean'] = static fn(array $p) => new IsBoolean(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'strict'], true)
        );

        $this->alias('integer', 'int');
        $this->alias('is_int', 'int');
        $this->alias('is_string', 'string');
        $this->alias('is_array', 'array');
        $this->alias('bool', 'boolean');
        $this->alias('is_bool', 'boolean');

        // String
        $this->factories['email'] = static fn() => new Email();
        $this->factories['url'] = static fn(array $p) => empty($p) ? new Url() : new Url($p);
        $this->factories['regex'] = static fn(array $p) => new Regex((string) ($p[0] ?? '//'));
        $this->factories['uuid'] = static fn(array $p) => new Uuid(
            isset($p[0]) && $p[0] !== '' ? (int) $p[0] : null
        );
        $this->factories['alpha'] = static fn(array $p) => new Alpha(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'ascii'], true)
        );
        $this->factories['alpha_num'] = static fn(array $p) => new AlphaNumeric(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'ascii'], true)
        );
        $this->factories['alpha_dash'] = static fn(array $p) => new AlphaDash(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1', 'ascii'], true)
        );
        $this->factories['length'] = static fn(array $p) => new Length(
            min: isset($p[0]) && $p[0] !== '' ? (int) $p[0] : null,
            max: isset($p[1]) && $p[1] !== '' ? (int) $p[1] : null,
        );
        $this->factories['phone'] = static fn(array $p) => new Phone(
            isset($p[0]) && $p[0] !== '' ? (string) $p[0] : null,
        );

        // Numeric
        $this->factories['range'] = fn(array $p) => new Range(
            min: isset($p[0]) && $p[0] !== '' ? $this->toNumber((string) $p[0]) : null,
            max: isset($p[1]) && $p[1] !== '' ? $this->toNumber((string) $p[1]) : null,
        );

        // `min:N` / `max:N` - one-bound shortcuts over `Range`. Numeric +
        // date semantics mirror `range`. NOT polymorphic on the value type
        // (Laravel-style):
        //   - Strings: use `length:lo[,hi]` (length-based bound)
        //   - Arrays:  use `count:lo[,hi]`  (size-based bound)
        // A non-numeric value silently fails the predicate - same behaviour
        // as `range:N,`. Added as a DX convenience after a recurring bug
        // where authors instinctively wrote `min:0|max:50` and hit
        // "Unknown rule".
        $this->factories['min'] = fn(array $p) => new Range(
            min: isset($p[0]) && $p[0] !== '' ? $this->toNumber((string) $p[0]) : null,
        );
        $this->factories['max'] = fn(array $p) => new Range(
            max: isset($p[0]) && $p[0] !== '' ? $this->toNumber((string) $p[0]) : null,
        );

        $this->factories['positive'] = static fn(array $p) => new Positive(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1'], true)
        );
        $this->factories['negative'] = static fn(array $p) => new Negative(
            isset($p[0]) && in_array(strtolower((string) $p[0]), ['true', '1'], true)
        );

        // Comparison
        $this->factories['equals'] = static fn(array $p) => new Equals(
            (string) ($p[0] ?? throw new InvalidArgumentException('equals requires field')),
            !isset($p[1]) || !in_array(strtolower((string) $p[1]), ['false', '0'], true),
        );
        $this->factories['not_equals'] = static fn(array $p) => new NotEquals(
            (string) ($p[0] ?? throw new InvalidArgumentException('not_equals requires field')),
            !isset($p[1]) || !in_array(strtolower((string) $p[1]), ['false', '0'], true),
        );
        $this->factories['gt'] = static fn(array $p) => new GreaterThan(
            (string) ($p[0] ?? throw new InvalidArgumentException('gt requires field')),
            isset($p[1]) && in_array(strtolower((string) $p[1]), ['true', '1'], true),
        );
        $this->factories['gte'] = static fn(array $p) => new GreaterThan(
            (string) ($p[0] ?? throw new InvalidArgumentException('gte requires field')),
            orEqual: true,
        );
        $this->factories['lt'] = static fn(array $p) => new LessThan(
            (string) ($p[0] ?? throw new InvalidArgumentException('lt requires field')),
            isset($p[1]) && in_array(strtolower((string) $p[1]), ['true', '1'], true),
        );
        $this->factories['lte'] = static fn(array $p) => new LessThan(
            (string) ($p[0] ?? throw new InvalidArgumentException('lte requires field')),
            orEqual: true,
        );
        $this->factories['confirmed'] = static fn(array $p) => new Confirmed((string) ($p[0] ?? 'confirmation'));

        // Date
        $this->factories['date'] = static fn() => new Date();
        $this->factories['date_format'] = static fn(array $p) => new DateFormat(
            (string) ($p[0] ?? throw new InvalidArgumentException('date_format requires format'))
        );
        $this->factories['before'] = static fn(array $p) => new Before(
            $p[0] ?? throw new InvalidArgumentException('before requires date'),
            isset($p[1]) && in_array(strtolower((string) $p[1]), ['true', '1'], true),
            isset($p[2]) && $p[2] !== '' ? (int) $p[2] : 0,
        );
        $this->factories['before_or_equal'] = static fn(array $p) => new Before(
            $p[0] ?? throw new InvalidArgumentException('before_or_equal requires date'),
            orEqual: true,
            graceMinutes: isset($p[1]) && $p[1] !== '' ? (int) $p[1] : 0,
        );
        $this->factories['after'] = static fn(array $p) => new After(
            $p[0] ?? throw new InvalidArgumentException('after requires date'),
            isset($p[1]) && in_array(strtolower((string) $p[1]), ['true', '1'], true),
            isset($p[2]) && $p[2] !== '' ? (int) $p[2] : 0,
        );
        $this->factories['after_or_equal'] = static fn(array $p) => new After(
            $p[0] ?? throw new InvalidArgumentException('after_or_equal requires date'),
            orEqual: true,
            graceMinutes: isset($p[1]) && $p[1] !== '' ? (int) $p[1] : 0,
        );

        // Array
        $this->factories['count'] = static fn(array $p) => new Count(
            min: isset($p[0]) && $p[0] !== '' ? (int) $p[0] : null,
            max: isset($p[1]) && $p[1] !== '' ? (int) $p[1] : null,
        );
        $this->factories['distinct'] = static fn(array $p) => new Distinct(
            isset($p[0]) && $p[0] !== '' ? (string) $p[0] : null
        );

        // Conditional
        $this->factories['required_if'] = fn(array $p) => new RequiredIf(
            (string) ($p[0] ?? throw new InvalidArgumentException('required_if requires field')),
            $this->castValue((string) ($p[1] ?? throw new InvalidArgumentException('required_if requires value'))),
        );
        $this->factories['required_with'] = static fn(array $p) => empty($p)
            ? throw new InvalidArgumentException('required_with requires fields')
            : new RequiredWith(array_map('strval', $p));
        $this->factories['required_without'] = static fn(array $p) => empty($p)
            ? throw new InvalidArgumentException('required_without requires fields')
            : new RequiredWithout(array_map('strval', $p));
        $this->factories['prohibited_if'] = fn(array $p) => new ProhibitedIf(
            (string) ($p[0] ?? throw new InvalidArgumentException('prohibited_if requires field')),
            $this->castValue((string) ($p[1] ?? throw new InvalidArgumentException('prohibited_if requires value'))),
        );
        $this->factories['exclude_if'] = fn(array $p) => new ExcludeIf(
            (string) ($p[0] ?? throw new InvalidArgumentException('exclude_if requires field')),
            $this->castValue((string) ($p[1] ?? throw new InvalidArgumentException('exclude_if requires value'))),
        );
        $this->factories['when'] = fn(array $p) => $this->createWhenRule($p);

        // Password
        $this->factories['password'] = fn(array $p) => new Password(
            min: isset($p[0]) && $p[0] !== '' ? (int) $p[0] : 8,
            flags: isset($p[1]) && $p[1] !== ''
                ? $this->parsePasswordFlags((string) $p[1])
                : Password::REQUIRE_UPPER | Password::REQUIRE_LOWER | Password::REQUIRE_DIGIT | Password::REQUIRE_SPECIAL,
            confirmationField: isset($p[2]) && $p[2] !== '' ? (string) $p[2] : null,
        );

        // File
        $this->factories['uploaded_file'] = static fn() => new UploadedFile();
        $this->factories['file_size'] = static fn(array $p) => new FileSize(
            max: FileSize::parseSize((string) ($p[0] ?? throw new InvalidArgumentException('file_size requires max size'))),
            min: isset($p[1]) && $p[1] !== '' ? FileSize::parseSize((string) $p[1]) : 0,
        );

        if ($this->detector !== null) {
            $this->factories['mime_type'] = fn(array $p) => empty($p)
                ? throw new InvalidArgumentException('mime_type requires at least one type')
                : new MimeType($this->detector, $p);
        }

        $this->factories['file'] = function (array $p): RuleInterface {
            $rules = [new UploadedFile()];

            if (isset($p[0]) && $p[0] !== '' && $p[0] !== '0') {
                $rules[] = new FileSize(max: FileSize::parseSize((string) $p[0]));
            }

            $mimeTypes = array_filter(
                array_slice($p, 1),
                static fn(string $v): bool => str_contains($v, '/'),
            );

            if ($mimeTypes !== [] && $this->detector !== null) {
                $rules[] = new MimeType($this->detector, array_values($mimeTypes));
            }

            return count($rules) === 1 ? $rules[0] : new AllOf(...$rules);
        };

        // Database
        if ($this->database !== null) {
            $this->factories['exists'] = fn(array $p) => new Exists(
                $this->database,
                (string) ($p[0] ?? throw new InvalidArgumentException('exists requires table')),
                (string) ($p[1] ?? 'id'),
            );

            $this->factories['unique'] = fn(array $p) => new Unique(
                $this->database,
                (string) ($p[0] ?? throw new InvalidArgumentException('unique requires table')),
                (string) ($p[1] ?? 'id'),
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

    /**
     * Create When rule from parsed parameters.
     *
     * Expected params: [condition, thenRules, elseRules?]
     * Condition format: "field:value"
     */
    private function createWhenRule(array $p): When
    {
        $condition = (string) ($p[0] ?? throw new InvalidArgumentException('when requires condition (field:value)'));
        $thenRules = (string) ($p[1] ?? throw new InvalidArgumentException('when requires then rules'));
        $elseRules = isset($p[2]) && $p[2] !== '' ? (string) $p[2] : null;

        return new When(
            $condition,
            $this->createRule($thenRules),
            $elseRules !== null ? $this->createRule($elseRules) : new Nullable(),
        );
    }

    public function createRules(array $definitions): array
    {
        return array_map(function ($definition) {
            return $definition instanceof RuleInterface ? $definition : $this->createRule($definition);
        }, $definitions);
    }
}
