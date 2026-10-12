<?php

declare(strict_types=1);

namespace App\Services;

final class WebhookUrlValidator
{
    public function isSafeHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '' || in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records) || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
