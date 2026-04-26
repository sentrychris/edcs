<?php

if (! function_exists('formatSystemUpdateTime')) {
    /**
     * Get update time according to various 3rd party formats.
     */
    function formatSystemUpdateTime($system): mixed
    {
        // Spansh dumps
        if (property_isset($system, 'updateTime')
            && is_string($system->updateTime)
            && $system->updateTime
        ) {
            if (str_contains($system->updateTime, '+')) {
                return substr($system->updateTime, 0, strpos($system->updateTime, '+'));
            }

            return $system->updateTime;
        }

        // EDSM dumps
        if (property_isset($system, 'updateTime')
            && is_object($system->updateTime)
            && $system->updateTime->information
        ) {
            return $system->updateTime->information;
        }

        return now();
    }
}

if (! function_exists('bytes_format')) {
    /**
     * Format bytes to human readable format.
     */
    function bytes_format(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).$units[$pow];
    }
}

if (! function_exists('property_isset')) {
    function property_isset(mixed $object, string $property)
    {
        return property_exists($object, $property)
            && isset($object->{$property})
            && $object->{$property} !== null;
    }
}

if (! function_exists('camel_to_spaces')) {
    function camel_to_spaces($str)
    {
        return preg_replace('/(?|([A-Z])([A-Z][a-z])|([a-z])([A-Z]))/', '$1$3 $2$4', $str);
    }
}
