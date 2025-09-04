<?php

namespace App\Service\Rule;

use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Time range rule - allows access only within specified time range
 * 
 * This rule allows access only during specified hours of the day (e.g., 09:00-17:00).
 * Uses UTC time to avoid timezone issues.
 */
class TimeRangeRule implements RuleInterface
{
    /**
     * Get rule name for identification
     * 
     * @return string Rule name
     */
    public function getName(): string
    {
        return 'time_range';
    }

    /**
     * Get rule priority for sorting
     * Higher priority (lower number) = executes earlier
     * 
     * @return int Priority value (10 = highest priority, before all other rules)
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Apply rule to QueryBuilder - blocks queries outside time range
     * 
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param array $config Rule configuration with time range
     * @return QueryBuilder Modified query builder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        if (!$this->isWithinTimeRange($config)) {
            // Outside time range - return guaranteed empty result
            $queryBuilder->andWhere('1 = 0');
        }
        
        return $queryBuilder;
    }

    /**
     * Apply rule to objects in memory - blocks objects outside time range
     * 
     * @param array $geoObjects Array of geo objects
     * @param array $config Rule configuration with time range
     * @return array Filtered array of objects
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        if (!$this->isWithinTimeRange($config)) {
            return []; // Outside time range
        }
        
        return $geoObjects;
    }

    /**
     * Get JSON Schema for rule configuration
     * 
     * @return array JSON Schema for validation
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'start_time' => [
                    'type' => 'string',
                    'pattern' => '^([0-1][0-9]|2[0-3]):[0-5][0-9]$',
                    'description' => 'Start time in HH:MM format (24-hour, UTC)'
                ],
                'end_time' => [
                    'type' => 'string',
                    'pattern' => '^([0-1][0-9]|2[0-3]):[0-5][0-9]$',
                    'description' => 'End time in HH:MM format (24-hour, UTC)'
                ],
                'timezone' => [
                    'type' => 'string',
                    'enum' => [
                        'UTC', 
                        'Europe/London', 
                        'Europe/Paris', 
                        'Europe/Berlin', 
                        'Europe/Moscow',
                        'America/New_York',
                        'America/Los_Angeles',
                        'Asia/Tokyo',
                        'Asia/Shanghai'
                    ],
                    'description' => 'Timezone for time range (defaults to UTC)'
                ]
            ],
            'required' => ['start_time', 'end_time'],
            'additionalProperties' => false
        ];
    }

    /**
     * Check if current time is within the specified range
     * 
     * @param array $config Rule configuration
     * @return bool True if within time range
     */
    private function isWithinTimeRange(array $config): bool
    {
        if (!isset($config['start_time']) || !isset($config['end_time'])) {
            return true; // Invalid config, allow access
        }
        
        $timezone = $config['timezone'] ?? 'UTC';
        
        try {
            $dateTimeZone = new \DateTimeZone($timezone);
            $currentTime = new \DateTime('now', $dateTimeZone);
            
            $startTime = $this->parseTimeString($config['start_time'], $currentTime, $dateTimeZone);
            $endTime = $this->parseTimeString($config['end_time'], $currentTime, $dateTimeZone);
            
            // Handle case where end time is next day (e.g., 22:00 - 06:00)
            if ($endTime <= $startTime) {
                // Time range spans midnight
                return $currentTime >= $startTime || $currentTime <= $endTime;
            } else {
                // Normal time range within same day
                return $currentTime >= $startTime && $currentTime <= $endTime;
            }
            
        } catch (\Exception $e) {
            // On any error (invalid timezone, invalid time format), allow access
            return true;
        }
    }

    /**
     * Parse time string (HH:MM) into DateTime object for today
     * 
     * @param string $timeString Time in HH:MM format
     * @param \DateTime $referenceDate Reference date for the time
     * @param \DateTimeZone $timezone Timezone to use
     * @return \DateTime DateTime object for the specified time today
     * @throws \Exception If time format is invalid
     */
    private function parseTimeString(string $timeString, \DateTime $referenceDate, \DateTimeZone $timezone): \DateTime
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $timeString, $matches)) {
            throw new \Exception("Invalid time format: $timeString");
        }
        
        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];
        
        if ($hours > 23 || $minutes > 59) {
            throw new \Exception("Invalid time values: $timeString");
        }
        
        $dateTime = clone $referenceDate;
        $dateTime->setTime($hours, $minutes, 0);
        
        return $dateTime;
    }
}
