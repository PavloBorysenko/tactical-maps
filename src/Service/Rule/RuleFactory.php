<?php

namespace App\Service\Rule;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class RuleFactory implements RuleFactoryInterface
{
    private array $ruleInstances = [];

    public function __construct(
        #[AutowireIterator('observer.rule')] private iterable $rules,
        private LoggerInterface $logger
    ) {
        $this->indexRules();
    }

    public function getRule(string $ruleName): ?RuleInterface
    {
        $sanitizedName = $this->sanitizeRuleName($ruleName);

        if (isset($this->ruleInstances[$sanitizedName])) {
            return $this->ruleInstances[$sanitizedName];
        }

        $this->logger->warning('Rule not found', [
            'requested' => $ruleName,
            'sanitized' => $sanitizedName,
            'available' => array_keys($this->ruleInstances)
        ]);

        return null;
    }

    public function getAllRules(): array
    {
        return $this->ruleInstances;
    }

    public function hasRule(string $ruleName): bool
    {
        $sanitizedName = $this->sanitizeRuleName($ruleName);
        return isset($this->ruleInstances[$sanitizedName]);
    }

    private function indexRules(): void
    {
        foreach ($this->rules as $rule) {
            $ruleName = $rule->getName();
            $this->ruleInstances[$ruleName] = $rule;
        }

        // Sort rules by priority
        uasort($this->ruleInstances, function (RuleInterface $a, RuleInterface $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        $this->logger->info('Rules indexed', [
            'count' => count($this->ruleInstances),
            'rules' => array_keys($this->ruleInstances)
        ]);
    }

    private function sanitizeRuleName(string $ruleName): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $ruleName);

        if (empty($sanitized)) {
            throw new \InvalidArgumentException("Invalid rule name after sanitization: $ruleName");
        }

        if (!preg_match('/^[a-zA-Z]/', $sanitized)) {
            throw new \InvalidArgumentException("Rule name must start with a letter: $sanitized");
        }

        return $sanitized;
    }
}
