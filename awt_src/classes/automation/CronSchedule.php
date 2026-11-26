<?php

namespace automation;

final class CronSchedule
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];
    private const WEEKDAYS = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    private array $minutes;
    private array $hours;
    private array $days;
    private array $months;
    private array $weekdays;
    private bool $requireBothDays;

    public function __construct(string $expression)
    {
        $fields = preg_split('/\s+/', strtolower(trim($expression)));
        if ($fields === false || count($fields) !== 5) {
            throw new \InvalidArgumentException('Expected a five-field cron expression.');
        }

        [$minute, $hour, $day, $month, $weekday] = $fields;
        $this->minutes = self::parseField($minute, 0, 59);
        $this->hours = self::parseField($hour, 0, 23);
        $this->days = self::parseField($day, 1, 31);
        $this->months = self::parseField($month, 1, 12, self::MONTHS);
        $this->weekdays = self::parseField($weekday, 0, 7, self::WEEKDAYS);
        if (isset($this->weekdays[7])) {
            $this->weekdays[0] = true;
        }
        // Cron matches either day field when both are restricted.
        $this->requireBothDays = str_starts_with($day, '*') || str_starts_with($weekday, '*');
    }

    public function isDue(\DateTimeImmutable $time): bool
    {
        $dayMatches = isset($this->days[(int) $time->format('j')]);
        $weekdayMatches = isset($this->weekdays[(int) $time->format('w')]);
        $dateMatches = $this->requireBothDays
            ? $dayMatches && $weekdayMatches
            : $dayMatches || $weekdayMatches;

        return $dateMatches && isset(
            $this->months[(int) $time->format('n')],
            $this->hours[(int) $time->format('G')],
            $this->minutes[(int) $time->format('i')],
        );
    }

    private static function parseField(string $field, int $min, int $max, array $names = []): array
    {
        $values = [];
        foreach (explode(',', $field) as $item) {
            if (!preg_match('/^(\*|[0-9]+|[a-z]{3})(?:-([0-9]+|[a-z]{3}))?(?:\/([0-9]+))?$/D', $item, $parts, PREG_UNMATCHED_AS_NULL)) {
                throw new \InvalidArgumentException("Invalid cron field: {$field}");
            }
            [, $start, $end, $step] = $parts;
            if (($start === '*' && $end !== null) || ($step !== null && $start !== '*' && $end === null)) {
                throw new \InvalidArgumentException("Invalid cron range or step: {$item}");
            }

            $first = $start === '*' ? $min : self::parseValue($start, $names);
            $last = $start === '*' ? $max : ($end === null ? $first : self::parseValue($end, $names));
            $increment = $step === null ? 1 : (int) $step;
            if ($first < $min || $last > $max || $first > $last || $increment < 1) {
                throw new \InvalidArgumentException("Cron value out of range: {$item}");
            }

            // Iterate the bounded field so even a very large step cannot overflow.
            for ($value = $first; $value <= $last; ++$value) {
                if (($value - $first) % $increment === 0) {
                    $values[$value] = true;
                }
            }
        }
        return $values;
    }

    private static function parseValue(string $value, array $names): int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }
        return $names[$value] ?? throw new \InvalidArgumentException("Invalid cron value: {$value}");
    }
}
