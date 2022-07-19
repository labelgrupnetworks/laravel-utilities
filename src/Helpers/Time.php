<?php

namespace Labelgrup\LaravelUtilities\Helpers;

class Time
{
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
        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;
        $secondsInAWeek = 7 * $secondsInADay;

        // extract weeks
        $weekSeconds = $inputSeconds % $secondsInAWeek;
        $weeks = floor($inputSeconds / $secondsInAWeek);
        // extract days
        $daySeconds = $weekSeconds % $secondsInAWeek;
        $days = floor($daySeconds / $secondsInADay);
        // extract hours
        $hourSeconds = $daySeconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour) + ($days * 24);
        // extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);
        // extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
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
}
