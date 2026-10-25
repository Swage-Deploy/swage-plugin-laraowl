<?php

namespace Swage\LaraOwl;

use App\Models\Server;
use App\Models\Site;
use App\Sites\SiteIntegration;
use Illuminate\Support\Facades\Cache;

class LaraOwlIntegration implements SiteIntegration
{
    public function id(): string
    {
        return 'laraowl';
    }

    public function settingsComponent(): string
    {
        return 'laraowl.site-settings';
    }

    /**
     * The project tokens of the server's sites.
     *
     * @return list<string|null>
     */
    public function secrets(Server $server): array
    {
        return LaraOwlSite::query()->whereIn('site_id', $server->sites()->select('id'))->get()->pluck('token')->all();
    }

    /**
     * A deployment can add or remove packages, so the site is checked for laraowl/client again.
     */
    public function deployed(Site $site): void
    {
        Cache::forget(SetLaraOwl::packageCacheKey($site));
    }
}
