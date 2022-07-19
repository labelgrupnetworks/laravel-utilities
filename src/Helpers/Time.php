<?php

namespace Labelgrup\LaravelUtilities\Helpers;

class Time
{
    public const SECONDS_IN_TIME = [
        'i' => 60,
        'h' => 3600,
        'd' => 86400,
        'w' => 604800
    ];

    /**
     * @param int $inputSeconds
     * @param string $unitMin
     * @return string
     */
    public static function parseTimeForHumans(
        int $inputSeconds,
        string $unitMin = 's'
    ): string
    {
        if (!array_key_exists($unitMin, self::SECONDS_IN_TIME)) {
            throw new \InvalidArgumentException(__('Invalid unitMin'));
        }

        // extract weeks
        $weekSeconds = $inputSeconds % self::SECONDS_IN_TIME['w'];
        $weeks = floor($inputSeconds / self::SECONDS_IN_TIME['w']);
        // extract days
        $daySeconds = $weekSeconds % self::SECONDS_IN_TIME['w'];
        $days = floor($daySeconds / self::SECONDS_IN_TIME['d']);
        // extract hours
        $hourSeconds = $daySeconds % self::SECONDS_IN_TIME['d'];
        $hours = floor($hourSeconds / self::SECONDS_IN_TIME['h']) + ($days * 24);
        // extract minutes
        $minuteSeconds = $hourSeconds % self::SECONDS_IN_TIME['h'];
        $minutes = floor($minuteSeconds / self::SECONDS_IN_TIME['i']);
        // extract the remaining seconds
        $remainingSeconds = $minuteSeconds % self::SECONDS_IN_TIME['i'];
        $seconds = ceil($remainingSeconds);

        $timeForHumans = '';

        if ($unitMin === 's') {
            $timeForHumans = $seconds . ' ' . __((int)$seconds === 1 ? 'second' : 'seconds');
        }

        if ($minutes && in_array($unitMin, ['s', 'i'])) {
            $timeForHumans = $minutes . ' ' . __((int)$minutes === 1 ? 'minute' : 'minutes') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        if ($hours) {
            $timeForHumans = $hours . ' ' . __((int)$hours === 1 ? 'hour' : 'hours') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        if ($days) {
            $timeForHumans = $days . ' ' . __((int)$days === 1 ? 'day' : 'days') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        if ($weeks) {
            $timeForHumans = $weeks . ' ' . __((int)$weeks === 1 ? 'week' : 'weeks') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        return $timeForHumans;
    }

    public static function convertTime(
        int $time,
        string $unitFrom,
        string $unitTo
    ): float
    {
        if (!array_key_exists($unitFrom, self::SECONDS_IN_TIME)) {
            throw new \InvalidArgumentException(__('Invalid unitFrom'));
        }

        if (!array_key_exists($unitTo, self::SECONDS_IN_TIME)) {
            throw new \InvalidArgumentException(__('Invalid unitTo'));
        }

        $time_from = $time;

        if ($unitFrom === $unitTo) {
            return $time_from;
        }

        $time_from *= self::SECONDS_IN_TIME[$unitFrom];
        return $time_from / self::SECONDS_IN_TIME[$unitTo];
    }
}
