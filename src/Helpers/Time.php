<?php

namespace Labelgrup\LaravelUtilities\Helpers;

class Time
{
    public static function parseTimeFormHumans(
        int $inputSeconds,
        string $unitMin = 's'
    ): string
    {
        $secondsInAMinute = 60;
        $secondsInAnHour  = 60 * $secondsInAMinute;
        $secondsInADay    = 24 * $secondsInAnHour;

        // extract days
        $days = floor($inputSeconds / $secondsInADay);

        // extract hours
        $hourSeconds = $inputSeconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour) + ($days * 24);
        // extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        $timeForHumans = '';

        if ( $unitMin === 's' ) {
            $timeForHumans = $seconds . ' ' . __('seconds');
        }

        if ( $minutes && in_array($unitMin, ['s', 'i']) ) {
            $timeForHumans = $minutes . ' ' . __('minutes') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        if ( $hours ) {
            $timeForHumans = $hours . ' ' . __('hours') . (!empty($timeForHumans) ? ' ' . $timeForHumans : '');
        }

        return $timeForHumans;
    }
}
