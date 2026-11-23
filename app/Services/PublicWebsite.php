<?php

namespace App\Services;

use Illuminate\Http\Request;

final class PublicWebsite
{
    public function __construct(private readonly PrivacyPublication $privacy) {}

    public function hasSecureOrigin(): bool
    {
        $url = filter_var(config('app.url'), FILTER_VALIDATE_URL);
        if ($url === false) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host']) && in_array($parts['path'] ?? '', ['', '/'], true)
            && array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts)) === [];
    }

    public function indexingEnabled(): bool
    {
        return app()->isProduction() && config('medloby.search_indexing') === true && $this->hasSecureOrigin();
    }

    public function canIndex(Request $request): bool
    {
        return $this->indexingEnabled() && match ($request->route()?->getName()) {
            'home' => ! $request->hasAny(['klinik', 'konum', 'tedavi', 'klinik_sayfa']),
            'clinics.show', 'search.robots', 'search.sitemap' => true,
            'policies.show' => $this->privacy->policiesArePublished(),
            default => false,
        };
    }

    /** @param array<string, string> $parameters */
    public function url(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, absolute: false);
    }
}
