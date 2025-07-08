<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

class TimezoneHelper
{
    /**
     * Convert timestamp to Cairo timezone (UTC+3)
     */
    public static function toCairoTime($timestamp)
    {
        return Carbon::parse($timestamp)->addHour()->setTimezone('Africa/Cairo');
    }
    
    /**
     * Convert timestamp to Saudi Arabia timezone (UTC+3)
     */
    public static function toSaudiTime($timestamp)
    {
        return Carbon::parse($timestamp)->setTimezone('Asia/Riyadh');
    }
    
    /**
     * Convert timestamp to specific timezone
     */
    public static function toTimezone($timestamp, $timezone)
    {
        return Carbon::parse($timestamp)->setTimezone($timezone);
    }
    
    /**
     * Get Cairo date string
     */
    public static function getCairoDate($timestamp)
    {
        return self::toCairoTime($timestamp)->toDateString();
    }
    
    /**
     * Get Cairo time string
     */
    public static function getCairoTimeString($timestamp)
    {
        return self::toCairoTime($timestamp)->toTimeString();
    }
    
    /**
     * Get Saudi Arabia date string
     */
    public static function getSaudiDate($timestamp)
    {
        return self::toSaudiTime($timestamp)->toDateString();
    }
    
    /**
     * Get Saudi Arabia time string
     */
    public static function getSaudiTimeString($timestamp)
    {
        return self::toSaudiTime($timestamp)->toTimeString();
    }
    
    /**
     * Get UTC+3 time (works for both Cairo and Saudi Arabia)
     */
    public static function getUTC3Time($timestamp)
    {
        return Carbon::parse($timestamp)->setTimezone('+03:00');
    }
    
    /**
     * Get UTC+3 date string
     */
    public static function getUTC3Date($timestamp)
    {
        return self::getUTC3Time($timestamp)->toDateString();
    }
    
    /**
     * Get UTC+3 time string
     */
    public static function getUTC3TimeString($timestamp)
    {
        return self::getUTC3Time($timestamp)->toTimeString();
    }
} 