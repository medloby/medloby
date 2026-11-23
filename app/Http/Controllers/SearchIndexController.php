<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\PrivacyPublication;
use App\Services\PublicWebsite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SearchIndexController extends Controller
{
    public function robots(PublicWebsite $site): Response
    {
        $body = $site->indexingEnabled()
            ? "User-agent: *\nDisallow:\nSitemap: ".$site->url('search.sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-cache']);
    }

    public function sitemap(PublicWebsite $site, PrivacyPublication $privacy): StreamedResponse
    {
        abort_unless($site->indexingEnabled(), 404);

        return response()->stream(function () use ($site, $privacy): void {
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            $this->writeUrl($site->url('home'));

            $policies = $privacy->policiesArePublished() ? array_keys(config('medloby.policy_pages')) : [];
            foreach ($policies as $policy) {
                $this->writeUrl($site->url('policies.show', ['policy' => $policy]));
            }

            $clinics = Business::query()->publiclyListed()->whereHas('branches', fn (Builder $branches) => $branches->where('status', 'active'))
                ->select('id', 'slug')->lazyById(500)->take(49_999 - count($policies));
            foreach ($clinics as $clinic) {
                $this->writeUrl($site->url('clinics.show', ['clinic' => $clinic->slug]));
            }
            echo "</urlset>\n";
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-cache']);
    }

    private function writeUrl(string $url): void
    {
        echo '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc></url>\n";
    }
}
