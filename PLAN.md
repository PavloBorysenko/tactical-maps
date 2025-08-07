# PLAN.MD - Observer Rules System Implementation

## 🎯 Project Goal

Implementation of a flexible rules system for Observers using Chain of Responsibility pattern, hybrid SQL+Memory approach and secure class naming.

## 🏗️ Architectural Principles

-   **Hybrid approach**: SQL filtering for simple rules, Memory for geospatial operations
-   **Chain of Responsibility**: Each rule is a separate class
-   **Security**: Class name sanitization, fixed namespace
-   **Performance**: Instance caching, lazy loading
-   **Simple JSON**: Class names without namespace

---

## 📋 Stage 1: Infrastructure Preparation

**Time: 1-2 days | Risk: Low**

### 1.1 Create basic structure

```bash
# Create directories
mkdir -p src/Service/Rule
mkdir -p tests/Unit/Service/Rule
mkdir -p tests/Integration/Service
```

### 1.2 Base abstract class

**File: `src/Service/Rule/AbstractObserverRule.php`**

```php
<?php

namespace App\Service\Rule;

use App\Entity\GeoObject;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractObserverRule
{
    /**
     * Can this rule be applied at SQL level?
     */
    abstract public function canApplyToQuery(): bool;

    /**
     * Apply rule to QueryBuilder (SQL level)
     * Default implementation - no changes
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        return $queryBuilder; // Default: no query changes
    }

    /**
     * Apply rule to objects in memory
     * Default implementation - no changes
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        return $geoObjects; // Default: no filtering
    }
}
```

### 1.3 First test rule

**File: `src/Service/Rule/ObjectTypeRule.php`**

```php
<?php

namespace App\Service\Rule;

use Doctrine\ORM\QueryBuilder;

class ObjectTypeRule extends AbstractObserverRule
{
    public function canApplyToQuery(): bool
    {
        return true;
    }

    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        if (empty($config)) {
            return $queryBuilder;
        }

        return $queryBuilder
            ->andWhere('g.geometryType IN (:allowedTypes)')
            ->setParameter('allowedTypes', $config);
    }
}
```

### 1.4 Main service stub

**File: `src/Service/ObserverRuleService.php`**

```php
<?php

namespace App\Service;

use App\Entity\Observer;
use App\Repository\GeoObjectRepository;
use Psr\Log\LoggerInterface;

class ObserverRuleService
{
    public function __construct(
        private GeoObjectRepository $geoObjectRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Temporary - just call old method
     */
    public function getFilteredGeoObjects(Observer $observer): array
    {
        $this->logger->info('ObserverRuleService called (Stage 1)', [
            'observer' => $observer->getName()
        ]);

        // Return result as before for now
        return $this->geoObjectRepository->findActiveByMap($observer->getMap());
    }
}
```

### 1.5 services.yaml configuration

```yaml
# config/services.yaml
services:
    App\Service\ObserverRuleService:
        arguments:
            $logger: '@logger'

    App\Service\Rule\ObjectTypeRule: ~
```

### ✅ Stage 1 success criteria:

-   [ ] Code compiles without errors
-   [ ] Service is registered in DI
-   [ ] Old functionality works as before
-   [ ] ObserverRuleService entries appear in logs

---

## 📋 Stage 2: Controller Integration

**Time: 1 day | Risk: Low**

### 2.1 Update controller

**File: `src/Controller/ObserverViewerController.php`**

```php
<?php

namespace App\Controller;

use App\Repository\ObserverRepository;
use App\Service\ObserverRuleService; // <- Add import
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ObserverViewerController extends AbstractController
{
    #[Route('/observer/{token}', name: 'observer_viewer', methods: ['GET'])]
    public function view(
        string $token,
        ObserverRepository $observerRepository,
        ObserverRuleService $observerRuleService // <- Replace GeoObjectRepository
    ): Response {
        $observer = $observerRepository->findByAccessToken($token);

        if (!$observer) {
            throw new NotFoundHttpException('Observer not found or invalid token');
        }

        // Switch to new service
        $geoObjects = $observerRuleService->getFilteredGeoObjects($observer);

        return $this->render('observer_viewer/view.html.twig', [
            'observer' => $observer,
            'map' => $observer->getMap(),
            'geoObjects' => $geoObjects,
        ]);
    }
}
```

### ✅ Stage 2 success criteria:

-   [ ] Observer pages load without errors
-   [ ] "ObserverRuleService called (Stage 1)" entries visible in logs
-   [ ] Objects display as before
-   [ ] No functionality is broken

---

## 📋 Stage 3: Sanitization and Security System Implementation

**Time: 2 days | Risk: Medium**

### 3.1 Add secure logic to ObserverRuleService

**Update file: `src/Service/ObserverRuleService.php`**

```php
<?php

namespace App\Service;

use App\Entity\Observer;
use App\Entity\Map;
use App\Repository\GeoObjectRepository;
use App\Service\Rule\AbstractObserverRule;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;

class ObserverRuleService
{
    private const RULE_NAMESPACE = 'App\\Service\\Rule\\';
    private array $ruleInstances = [];

    public function __construct(
        private GeoObjectRepository $geoObjectRepository,
        private LoggerInterface $logger,
        private ContainerInterface $container
    ) {}

    public function getFilteredGeoObjects(Observer $observer): array
    {
        $map = $observer->getMap();
        $rulesConfig = $observer->getRules();

        // If no rules - work as before
        if (empty($rulesConfig)) {
            $this->logger->info('No rules configured, using default filtering', [
                'observer' => $observer->getName()
            ]);
            return $this->geoObjectRepository->findActiveByMap($map);
        }

        // Has rules - apply new logic (test only for now)
        return $this->applyRulesBasic($map, $rulesConfig, $observer);
    }

    /**
     * Basic rule application implementation (testing only)
     */
    private function applyRulesBasic(Map $map, array $rulesConfig, Observer $observer): array
    {
        $queryBuilder = $this->geoObjectRepository->createQueryBuilder('g')
            ->where('g.map = :map')
            ->andWhere('(g.ttl IS NULL OR DATE_ADD(g.createdAt, INTERVAL g.ttl MINUTE) > NOW())')
            ->setParameter('map', $map);

        $appliedRules = [];

        // Test only ObjectTypeRule for now
        if (isset($rulesConfig['ObjectTypeRule'])) {
            try {
                $rule = $this->getCachedRule('ObjectTypeRule');
                if ($rule->canApplyToQuery()) {
                    $queryBuilder = $rule->applyToQuery($queryBuilder, $rulesConfig['ObjectTypeRule']);
                    $appliedRules[] = 'ObjectTypeRule';
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to apply ObjectTypeRule', [
                    'observer' => $observer->getName(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $result = $queryBuilder->getQuery()->getResult();

        $this->logger->info('Rules applied (Stage 3)', [
            'observer' => $observer->getName(),
            'rules' => $appliedRules,
            'objects_count' => count($result)
        ]);

        return $result;
    }

    private function getCachedRule(string $ruleName): AbstractObserverRule
    {
        if (!isset($this->ruleInstances[$ruleName])) {
            $this->ruleInstances[$ruleName] = $this->createRule($ruleName);
        }

        return $this->ruleInstances[$ruleName];
    }

    /**
     * Secure rule creation with name sanitization
     */
    private function createRule(string $ruleName): AbstractObserverRule
    {
        // SECURITY: Remove all potentially dangerous characters
        $sanitizedName = $this->sanitizeRuleName($ruleName);

        // Build full class name
        $fullClassName = self::RULE_NAMESPACE . $sanitizedName;

        // Check that class exists
        if (!class_exists($fullClassName)) {
            throw new \InvalidArgumentException("Rule class not found: $sanitizedName");
        }

        // Check that this is actually a rule
        if (!is_subclass_of($fullClassName, AbstractObserverRule::class)) {
            throw new \InvalidArgumentException("Class $sanitizedName is not a valid rule");
        }

        // Create through container (with DI)
        if ($this->container->has($fullClassName)) {
            return $this->container->get($fullClassName);
        }

        // Fallback - create directly
        return new $fullClassName($this->geoObjectRepository);
    }

    /**
     * Class name sanitization - remove all dangerous characters
     */
    private function sanitizeRuleName(string $ruleName): string
    {
        // Remove everything except letters, numbers and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $ruleName);

        // Check that something remains
        if (empty($sanitized)) {
            throw new \InvalidArgumentException("Invalid rule name after sanitization: $ruleName");
        }

        // Check that starts with letter (valid PHP class name)
        if (!preg_match('/^[a-zA-Z]/', $sanitized)) {
            throw new \InvalidArgumentException("Rule name must start with a letter: $sanitized");
        }

        $this->logger->debug('Rule name sanitized', [
            'original' => $ruleName,
            'sanitized' => $sanitized
        ]);

        return $sanitized;
    }
}
```

### 3.2 Update DI configuration

```yaml
# config/services.yaml
services:
    App\Service\ObserverRuleService:
        arguments:
            $logger: '@logger'
            $container: '@service_container'

    App\Service\Rule\ObjectTypeRule: ~
```

### 3.3 Test Observer with simplified rules

**JSON for testing:**

```json
{
    "ObjectTypeRule": ["Point", "Polygon"]
}
```

### ✅ Stage 3 success criteria:

-   [ ] Observers without rules work as before
-   [ ] Observers with `ObjectTypeRule` filter by types
-   [ ] Class name sanitization works correctly
-   [ ] "Rules applied (Stage 3)" visible in logs
-   [ ] Security: injection attempts are blocked

---

## 📋 Stages 4-8: Additional Implementation Details

### Stage 4: SQL-Compatible Rules (2-3 days | Medium Risk)

-   Add SideVisibilityRule, TtlRule
-   Implement multiple rule processing
-   Test rule combinations

### Stage 5: Geospatial Rules and Hybrid Logic (3-4 days | High Risk)

-   Extend GeoObjectRepository with spatial methods
-   Implement RadiusRule, RestrictedAreaRule
-   Full hybrid SQL+Memory approach

### Stage 6: Performance Optimization (2-3 days | Medium Risk)

-   Database indexes
-   Coordinate caching
-   Bounding box optimization
-   Performance monitoring

### Stage 7: UI and Security (2 days | Low Risk)

-   Improve admin form
-   JSON validation
-   Rate limiting

### Stage 8: Testing and Documentation (2-3 days | Low Risk)

-   Unit tests for rules
-   Integration tests
-   Security tests
-   Complete documentation

---

## 📊 Project Summary

### 🎯 Key Implementation Features:

1. **Security**: Class name sanitization, injection protection
2. **Performance**: Hybrid SQL+Memory approach with caching
3. **Flexibility**: Easy addition of new rules without changing existing code
4. **Readability**: JSON configuration with simple class names
5. **Reliability**: Error handling, logging, validation

### ⏱️ Timeline:

-   **Total time**: 2-3 weeks
-   **Team**: 1-2 developers
-   **Risks**: Stage 5 (geospatial operations) - high risk

### 📈 Expected Results:

-   Flexible rules system for Observers
-   Good performance (< 1 sec for 10k+ objects)
-   Secure JSON configuration
-   Easy extensibility with new rules

### 🔧 Technical Requirements:

-   PHP 8.1+
-   MySQL 8.0+ (for JSON functions)
-   Symfony 6.0+
-   phpgeo library for geospatial calculations
