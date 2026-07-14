<?php

namespace App\Support;

use Illuminate\Support\Str;

class SafePublicHttpUrl
{
    /**
     * Normalize merchant-entered domains while preserving explicit schemes.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);

        if (! preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $url)) {
            return 'https://'.$url;
        }

        return $url;
    }

    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = Str::lower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = trim($host, '[]');

        if (self::isBlockedHostName($host)) {
            return false;
        }

        $addresses = self::resolvedAddresses($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                return false;
            }
        }

        return true;
    }

    private static function isBlockedHostName(string $host): bool
    {
        $host = Str::lower(rtrim($host, '.'));

        return $host === 'localhost' || Str::endsWith($host, '.localhost');
    }

    /**
     * @return array<int, string>
     */
    private static function resolvedAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];

        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    private static function isPublicIp(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
