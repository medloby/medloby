<?php

$addresses = array_filter(array_map(trim(...), explode(',', (string) env('MEDLOBY_TRUSTED_PROXIES'))));
$proxies = array_values(array_filter($addresses, function (string $address): bool {
    [$ip, $prefix] = array_pad(explode('/', $address, 2), 2, null);

    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && ($prefix === null || (ctype_digit($prefix) && (int) $prefix >= 1
            && (int) $prefix <= (str_contains($ip, ':') ? 128 : 32)));
}));

return [
    'proxies' => $proxies,
    'valid' => count($proxies) === count($addresses),
];
