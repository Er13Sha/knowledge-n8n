<?php

namespace App\Services;

class IntegrationUrl
{
    public static function webhook(?string $url, string $dockerHost): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (! file_exists('/.dockerenv')) {
            return $url;
        }

        return str_replace(
            ['http://localhost:', 'http://127.0.0.1:'],
            ['http://'.$dockerHost.':', 'http://'.$dockerHost.':'],
            $url,
        );
    }
}
