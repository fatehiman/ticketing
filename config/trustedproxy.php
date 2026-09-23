<?php

/*
 * Proxies trusted for X-Forwarded-* headers (read by TrustProxies at request time).
 * Behind a CDN (waybill: ArvanCloud) set TRUSTED_PROXIES=* so the login rate limit
 * sees the visitor's IP instead of the CDN's. Empty on deb10 (no proxy).
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
