<?php

namespace App\Service;

use App\Service\Rule\RuleValidatorInterface;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Psr\Log\LoggerInterface;

class RuleConfigValidator implements RuleValidatorInterface
{
    private ?object $schema = null;

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function validate(array $config): array
    {
        $validator = new Validator();
        $configObject = json_decode(json_encode($config));

        $schema = $this->getSchema();
        $validator->validate($configObject, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

        $errors = [];
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
            }

            $this->logger->warning('Rule configuration validation failed', [
                'errors' => $errors,
                'config' => $config
            ]);
        }

        return $errors;
    }

    /**
     * Get schema object, building it dynamically from available rules
     */
    private function getSchema(): object
    {
        if ($this->schema === null) {
            $this->schema = $this->buildSchema();
        }
        
        return $this->schema;
    }

    /**
     * Build JSON schema dynamically from all available rule classes
     * Uses static method calls for better performance - no object creation needed
     */
    private function buildSchema(): object
    {
        $properties = [];
        
        try {
            $ruleClasses = $this->discoverRuleClasses();
            
            foreach ($ruleClasses as $ruleClass) {
                $ruleName = $this->extractRuleName($ruleClass);
                $ruleSchema = $ruleClass::getConfigSchema();
                
                if (!empty($ruleSchema)) {
                    $properties[$ruleName] = $ruleSchema;
                }
            }
            
            $this->logger->debug('Built dynamic schema using static methods', [
                'rules_count' => count($properties),
                'rule_names' => array_keys($properties)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to build dynamic schema, using fallback', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to basic schema if discovery fails
            $properties = $this->getFallbackProperties();
        }

        return json_decode(json_encode([
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
            'minProperties' => 1
        ]));
    }

    /**
     * Discover all rule classes in the Rule directory
     */
    private function discoverRuleClasses(): array
    {
        $ruleClasses = [];
        $ruleDir = __DIR__ . '/Rule';
        
        if (!is_dir($ruleDir)) {
            return $ruleClasses;
        }
        
        $files = glob($ruleDir . '/*Rule.php');
        foreach ($files as $file) {
            $className = 'App\\Service\\Rule\\' . basename($file, '.php');
            
            if (class_exists($className) && 
                is_subclass_of($className, 'App\\Service\\Rule\\AbstractObserverRule')) {
                $ruleClasses[] = $className;
            }
        }
        
        return $ruleClasses;
    }
    
    /**
     * Extract rule name from class name
     */
    private function extractRuleName(string $className): string
    {
        return basename(str_replace('\\', '/', $className));
    }

    /**
     * Fallback properties if dynamic schema building fails
     */
    private function getFallbackProperties(): array
    {
        return [
            'ObjectIdRule' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                    'minimum' => 1
                ],
                'minItems' => 1,
                'maxItems' => 100,
                'uniqueItems' => true
            ]
        ];
    }
}
