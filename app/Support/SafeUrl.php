<?php

namespace App\Support;

/**
 * SSRF guard for merchant-supplied webhook URLs.
 *
 * resolve() returns the vetted IP so the HTTP client can be pinned to it,
 * which closes the DNS-rebinding gap between checking and connecting.
 */
class SafeUrl
{
    /**
     * Validate the URL and resolve its host to a public IPv4 address.
     *
     * @return array{host: string, port: int, ip: string}|null
     */
    public static function resolve(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! ($scheme === 'https' || ($scheme === 'http' && config('gateway.webhook_allow_insecure')))) {
            return null;
        }

        // Credentials in the URL are a common SSRF/parser-confusion vector.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        // IPv6 literals are rejected outright; mapped forms like
        // [::ffff:127.0.0.1] are too easy to get wrong.
        if (str_starts_with($host, '[')) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! static::isPublicIp($ip)) {
                return null;
            }
        }

        return [
            'host' => $host,
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'ip' => $ips[0],
        ];
    }

    /**
     * Whether the address is routable on the public internet.
     */
    public static function isPublicIp(string $ip): bool
    {
        if (config('gateway.webhook_allow_private_network')) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Carrier-grade NAT (100.64.0.0/10) is not covered by the PHP flags.
        $long = ip2long($ip);

        return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
    }
}
