<?php

namespace Componenta\Validation\Factory;

use Componenta\Validation\ConfigKey;
use Componenta\Validation\Formatter\Dictionary;
use Componenta\Validation\Formatter\MessageFormatter;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use Componenta\Validation\Walker\Walker;
use Componenta\Validation\Walker\WalkerInterface;
use Psr\Container\ContainerInterface;

final readonly class ValidatorFactory implements ValidatorFactoryInterface
{
    private MessageFormatterInterface $messageFormatter;

    public function __construct(
        private ContainerInterface $container,
        private RuleFactoryInterface $ruleFactory = new RuleFactory(),
        private WalkerInterface $walker = new Walker(),
        ?MessageFormatterInterface $messageFormatter = null,
        private string $defaultLocale = ConfigKey::LOCALE_EN,
    ) {
        $this->messageFormatter = $messageFormatter ?? MessageFormatter::fromDefaults();
    }

    public function create(string $cls): ValidatorInterface
    {
        return $this->container->get($cls);
    }

    public function createFrom(array $rules): Validator
    {
        return new Validator($this->ruleFactory->createRules($rules), $this->walker, $this->messageFormatter, $this->defaultLocale);
    }
}