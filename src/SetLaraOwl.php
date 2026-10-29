<?php

namespace Swage\LaraOwl;

use App\Actions\Servers\RunServerTask;
use App\Exceptions\ScriptFailedException;
use App\Models\Site;

class SetLaraOwl
{
    /**
     * Server URLs the LaraOwl client may report to.
     */
    public const string SERVER_URL_PATTERN = '/^https?:\/\/[A-Za-z0-9.-]+(:\d{1,5})?(\/[A-Za-z0-9._~\/-]*)?$/D';

    /**
     * Tokens LaraOwl issues, limited to characters that are safe unquoted in a .env file.
     */
    public const string TOKEN_PATTERN = '/^[A-Za-z0-9._|:=+\/-]+$/D';

    /**
     * The .env variable laraowl/client reads each kind of event's sample rate from, by the column it is saved in.
     */
    public const array SAMPLE_RATES = [
        'sample_requests' => 'LARAOWL_SAMPLE_REQUESTS',
        'sample_commands' => 'LARAOWL_SAMPLE_COMMANDS',
        'sample_exceptions' => 'LARAOWL_SAMPLE_EXCEPTIONS',
        'sample_tasks' => 'LARAOWL_SAMPLE_TASKS',
    ];

    public function __construct(protected RunServerTask $runTask) {}

    /**
     * Point the site's LaraOwl client at a server, or remove it when no server is given, and save the settings
     * once the site's .env has been updated.
     *
     * A sample rate left out, or null, is removed from the .env, so the client reports every event of that kind.
     * The application must require laraowl/client itself; Swage only configures it.
     *
     * @param  array<string, float|null>  $sampleRates  by the keys of SAMPLE_RATES
     *
     * @throws ScriptFailedException
     */
    public function handle(Site $site, ?string $serverUrl, ?string $token, array $sampleRates = []): void
    {
        $serverUrl = filled($serverUrl) ? rtrim($serverUrl, '/') : null;
        $token = $serverUrl === null ? null : $token;
        $sampleRates = collect(self::SAMPLE_RATES)->map(fn (string $key, string $column) => $serverUrl === null ? null : ($sampleRates[$column] ?? null));

        $this->runTask->run($site->server, ($serverUrl ? 'Configuring' : 'Removing')." LaraOwl for {$site->domain}", view('laraowl::scripts.configure', [
            'site' => $site,
            'values' => [
                'LARAOWL_SERVER_URL' => $serverUrl,
                'LARAOWL_TOKEN' => $token,
                ...$sampleRates->mapWithKeys(fn (?float $rate, string $column) => [self::SAMPLE_RATES[$column] => $rate === null ? null : (string) $rate])->all(),
            ],
        ])->render(), timeout: 120);

        if ($serverUrl === null) {
            LaraOwlSite::query()->where('site_id', $site->id)->delete();
        } else {
            LaraOwlSite::query()->updateOrCreate(['site_id' => $site->id], ['server_url' => $serverUrl, 'token' => $token, ...$sampleRates->all()]);
        }
    }

    /**
     * The file that exists in the site's live release once laraowl/client is installed.
     */
    public static function packageManifestPath(Site $site): string
    {
        return $site->currentPath().'/vendor/laraowl/client/composer.json';
    }

    /**
     * The cache key for whether laraowl/client is in the site's live release, which only a deployment can change.
     */
    public static function packageCacheKey(Site $site): string
    {
        return "laraowl-installed:{$site->id}";
    }

    /**
     * Turn a bare domain into the https URL LaraOwl expects.
     */
    public static function normalizeServerUrl(string $domainOrUrl): string
    {
        $domainOrUrl = trim($domainOrUrl);

        return rtrim(preg_match('/^https?:\/\//i', $domainOrUrl) ? $domainOrUrl : "https://{$domainOrUrl}", '/');
    }
}
