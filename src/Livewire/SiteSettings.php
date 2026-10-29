<?php

namespace Swage\LaraOwl\Livewire;

use App\Exceptions\ScriptFailedException;
use App\Models\Site;
use App\Ssh\RemoteShell;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Swage\LaraOwl\LaraOwlSite;
use Swage\LaraOwl\SetLaraOwl;
use Throwable;

/**
 * The LaraOwl panel on a site's settings page.
 */
class SiteSettings extends Component
{
    public Site $site;

    public string $server_url = '';

    /**
     * A new token. The saved one never leaves the server, so a blank field keeps it.
     */
    public string $token = '';

    /**
     * The share of each kind of event to report, by the columns in SetLaraOwl::SAMPLE_RATES. Blank reports them all.
     *
     * @var array<string, string>
     */
    public array $sample_rates = [];

    public ?string $error = null;

    /**
     * Whether laraowl/client is in the site's live release; null until checked, or when the server could not be reached.
     */
    #[Locked]
    public ?bool $installed = null;

    #[Locked]
    public bool $checked = false;

    public function mount(): void
    {
        $this->authorize('view', $this->site);

        $connection = LaraOwlSite::for($this->site);

        $this->server_url = $connection->server_url ?? '';
        $this->sample_rates = collect(SetLaraOwl::SAMPLE_RATES)->map(fn (string $key, string $column) => (string) ($connection?->{$column} ?? ''))->all();
    }

    public function render(): View
    {
        return view('laraowl::site-settings', ['connection' => LaraOwlSite::for($this->site)]);
    }

    /**
     * Check the site's live release for the laraowl/client package.
     *
     * The answer is remembered until the site is next deployed, so opening the settings does not reach the server
     * every time. Only a definite answer is remembered, never a server that could not be reached.
     */
    public function check(RemoteShell $shell): void
    {
        $this->installed = Cache::get(SetLaraOwl::packageCacheKey($this->site));

        if ($this->installed === null) {
            try {
                $result = $shell->run($this->site->server, 'test -f '.escapeshellarg(SetLaraOwl::packageManifestPath($this->site)).' && echo installed || echo missing', as: $this->site->systemUser(), timeout: 20);

                $this->installed = $result->successful() ? trim($result->output) === 'installed' : null;
            } catch (Throwable) {
                $this->installed = null;
            }

            if ($this->installed !== null) {
                Cache::put(SetLaraOwl::packageCacheKey($this->site), $this->installed, now()->addDay());
            }
        }

        $this->checked = true;
    }

    /**
     * Check the site for laraowl/client again, ignoring the remembered answer.
     */
    public function recheck(RemoteShell $shell): void
    {
        Cache::forget(SetLaraOwl::packageCacheKey($this->site));

        $this->check($shell);
    }

    /**
     * Point the site at a LaraOwl server, writing its URL and token into the .env.
     */
    public function save(SetLaraOwl $setLaraOwl): void
    {
        $this->authorize('update', $this->site);

        if ($this->installed !== true) {
            $this->error = __('Install laraowl/client in the application and deploy it before adding LaraOwl.');

            return;
        }

        $savedToken = LaraOwlSite::for($this->site)?->token;

        $this->server_url = filled($this->server_url) ? SetLaraOwl::normalizeServerUrl($this->server_url) : '';
        $this->token = trim($this->token);
        $this->sample_rates = collect(SetLaraOwl::SAMPLE_RATES)->map(fn (string $key, string $column) => trim($this->sample_rates[$column] ?? ''))->all();

        $validated = $this->validate([
            'server_url' => ['required', 'string', 'max:255', 'regex:'.SetLaraOwl::SERVER_URL_PATTERN],
            'token' => [$savedToken ? 'nullable' : 'required', 'string', 'max:255', 'regex:'.SetLaraOwl::TOKEN_PATTERN],
            'sample_rates.*' => ['nullable', 'numeric', 'min:0', 'max:1', 'decimal:0,3'],
        ], [
            'server_url.regex' => __('Enter the LaraOwl server\'s domain, such as owl.example.com.'),
            'token.regex' => __('The token can only contain letters, numbers and . _ | : = + / -'),
            'sample_rates.*' => __('Enter a number from 0 to 1 with up to three decimal places, such as 0.1.'),
        ]);

        $sampleRates = collect($validated['sample_rates'])->map(fn (?string $rate) => filled($rate) ? (float) $rate : null)->all();

        $this->apply($setLaraOwl, $validated['server_url'], $validated['token'] ?: $savedToken, $sampleRates);
    }

    /**
     * Remove LaraOwl's URL and token from the .env.
     */
    public function remove(SetLaraOwl $setLaraOwl): void
    {
        $this->authorize('update', $this->site);

        $this->apply($setLaraOwl, null, null);

        $this->server_url = '';
        $this->sample_rates = array_map(fn () => '', SetLaraOwl::SAMPLE_RATES);
    }

    /**
     * @param  array<string, float|null>  $sampleRates
     */
    protected function apply(SetLaraOwl $setLaraOwl, ?string $serverUrl, ?string $token, array $sampleRates = []): void
    {
        $this->error = null;

        try {
            $setLaraOwl->handle($this->site, $serverUrl, $token, $sampleRates);
        } catch (ScriptFailedException $exception) {
            $this->error = trim($exception->task->output ?? '');

            return;
        }

        $this->token = '';

        Flux::toast(variant: 'success', text: $serverUrl ? __('LaraOwl is configured.') : __('LaraOwl removed.'));
    }
}
