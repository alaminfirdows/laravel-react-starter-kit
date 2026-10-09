<?php

namespace App\Domain\Task\Rules;

use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

/**
 * Checks `schedule.cron` and `schedule.timezone` in an action config (array or JSON string).
 * A cron expression must parse and must produce a next run date.
 */
class ActionSchedule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = self::problem($value);

        if ($problem !== null) {
            $fail($problem);
        }
    }

    public static function problem(mixed $config): ?string
    {
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        $schedule = is_array($config) ? ($config['schedule'] ?? null) : null;

        if (! is_array($schedule)) {
            return null;
        }

        $timezone = $schedule['timezone'] ?? config('app.timezone');

        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return __('The schedule timezone is not a valid timezone.');
        }

        if (! array_key_exists('cron', $schedule)) {
            return null;
        }

        $cron = $schedule['cron'];

        if (! is_string($cron) || ! CronExpression::isValidExpression($cron)) {
            return __('The schedule cron is not a valid cron expression.');
        }

        try {
            new CronExpression($cron)->getNextRunDate('now', 0, false, $timezone);
        } catch (RuntimeException) {
            return __('The schedule cron never runs.');
        }

        return null;
    }
}
