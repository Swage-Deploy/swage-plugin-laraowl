<?php

/*
 * Runs inside a Swage checkout: it uses Swage's models, factories and test helpers. See the README's Development
 * section.
 */

use App\Jobs\DeploySite;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\User;
use App\Ssh\RemoteShell;
use App\Ssh\ScriptResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Swage\LaraOwl\LaraOwlServiceProvider;
use Swage\LaraOwl\LaraOwlSite;

beforeEach(function () {
    $this->app->register(LaraOwlServiceProvider::class, force: true);
});

function laraOwlSettings(Site $site)
{
    return Livewire::actingAs($site->server->user)->test('laraowl.site-settings', ['site' => $site]);
}

/**
 * Open the site's LaraOwl settings with the client found in its live release.
 */
function laraOwlSettingsWithClient(Site $site)
{
    fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "installed\n"));

    return laraOwlSettings($site)->call('check')->assertSet('installed', true);
}

function connectLaraOwl(Site $site, string $serverUrl = 'https://owl.example.net', string $token = 'saved-token'): LaraOwlSite
{
    return LaraOwlSite::create(['site_id' => $site->id, 'server_url' => $serverUrl, 'token' => $token]);
}

test('the settings swage kept on sites move into the plugin\'s table', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();
    DB::table('sites')->where('id', $site->id)->update(['laraowl_server_url' => 'https://owl.example.net', 'laraowl_token' => encrypt('saved-token', false)]);

    $this->artisan('migrate')->assertSuccessful();

    expect(LaraOwlSite::for($site))
        ->server_url->toBe('https://owl.example.net')
        ->token->toBe('saved-token')
        ->and(LaraOwlSite::for($other))->toBeNull()
        ->and(Schema::hasColumn('sites', 'laraowl_server_url'))->toBeFalse();
});

describe('once installed', function () {
    beforeEach(function () {
        $this->artisan('migrate')->assertSuccessful();
    });

    test('adding laraowl writes its server url and token into the site\'s environment', function () {
        $site = Site::factory()->isolated('example')->create(['domain' => 'example.com']);

        $settings = laraOwlSettingsWithClient($site);
        $shell = app(RemoteShell::class);

        $settings
            ->set('server_url', 'owl.example.net/')
            ->set('token', '7|AbC.def_ghi')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('server_url', 'https://owl.example.net')
            ->assertSet('token', '');

        expect(LaraOwlSite::for($site))
            ->server_url->toBe('https://owl.example.net')
            ->token->toBe('7|AbC.def_ghi');

        expect($shell->lastScript())
            ->toContain("ENV_FILE='/home/example/example.com/shared/.env'")
            ->toContain("sudo -u example sed -i 's#^LARAOWL_SERVER_URL=.*#LARAOWL_SERVER_URL=https://owl.example.net#' \"\$ENV_FILE\"")
            ->toContain("printf '%s\\n' 'LARAOWL_TOKEN=7|AbC.def_ghi' | sudo -u example tee -a \"\$ENV_FILE\" > /dev/null")
            ->not->toMatch('/>>\s*"\$ENV_FILE"/')
            ->toContain('config:cache')
            ->toContain('systemctl reload php8.5-fpm');
    });

    test('the saved token is kept when the field is left blank', function () {
        $site = Site::factory()->create();
        connectLaraOwl($site);

        laraOwlSettingsWithClient($site)
            ->assertSet('server_url', 'https://owl.example.net')
            ->assertSet('token', '')
            ->set('server_url', 'https://owl2.example.net')
            ->call('save')
            ->assertHasNoErrors();

        expect(LaraOwlSite::for($site))
            ->server_url->toBe('https://owl2.example.net')
            ->token->toBe('saved-token');
    });

    test('laraowl settings are validated', function (string $serverUrl, string $token, string $error) {
        $site = Site::factory()->create();

        laraOwlSettingsWithClient($site)
            ->set('server_url', $serverUrl)
            ->set('token', $token)
            ->call('save')
            ->assertHasErrors([$error]);

        expect(LaraOwlSite::for($site))->toBeNull();
    })->with([
        'missing domain' => ['', 'token', 'server_url'],
        'shell characters in the domain' => ['owl.example.net;reboot', 'token', 'server_url'],
        'missing token' => ['owl.example.net', '', 'token'],
        'quote in the token' => ['owl.example.net', "tok'en", 'token'],
        'hash in the token' => ['owl.example.net', 'tok#en', 'token'],
    ]);

    test('removing laraowl deletes its variables from the environment', function () {
        $shell = fakeShell();
        $site = Site::factory()->create();
        connectLaraOwl($site);

        laraOwlSettings($site)->call('remove')->assertSet('server_url', '');

        expect(LaraOwlSite::for($site))->toBeNull();

        expect($shell->lastScript())
            ->toContain("sed -i '/^LARAOWL_SERVER_URL=/d'")
            ->toContain("sed -i '/^LARAOWL_TOKEN=/d'");
    });

    test('settings are not saved when the environment cannot be updated', function () {
        $site = Site::factory()->create();
        $settings = laraOwlSettingsWithClient($site);
        fakeShell()->respondTo('LARAOWL', new ScriptResult(1, '.env does not exist. Install the site first.'));

        $settings
            ->set('server_url', 'owl.example.net')
            ->set('token', 'token')
            ->call('save')
            ->assertSet('error', '.env does not exist. Install the site first.');

        expect(LaraOwlSite::for($site))->toBeNull();
    });

    test('the panel is on the site\'s plugins tab and never sends the saved token to the browser', function () {
        $site = Site::factory()->create();
        connectLaraOwl($site, token: 'super-secret-token');

        $this->actingAs($site->server->user)
            ->get(route('servers.sites.plugins', [$site->server, $site]))
            ->assertOk()
            ->assertSee('LaraOwl monitoring')
            ->assertSee('Reporting')
            ->assertDontSee('super-secret-token');
    });

    test('other users cannot change a site\'s laraowl settings', function () {
        $site = Site::factory()->create();

        Livewire::actingAs(User::factory()->create())
            ->test('laraowl.site-settings', ['site' => $site])
            ->assertForbidden();
    });

    test('saved tokens are redacted from task output', function () {
        $site = Site::factory()->create();
        connectLaraOwl($site, token: 'super-secret-token');

        expect($site->server->redactSecrets('token=super-secret-token'))->toBe('token=********');
    });

    test('the settings are deleted with the site', function () {
        $site = Site::factory()->create();
        connectLaraOwl($site);

        $site->delete();

        expect(LaraOwlSite::count())->toBe(0);
    });

    test('laraowl cannot be added until the package is installed', function () {
        $shell = fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "missing\n"));
        $site = Site::factory()->isolated('example')->create(['domain' => 'example.com']);

        laraOwlSettings($site)
            ->call('check')
            ->assertSet('installed', false)
            ->assertSee('laraowl/client is not installed')
            ->assertSee('composer require laraowl/client')
            ->set('server_url', 'owl.example.net')
            ->set('token', 'token')
            ->call('save')
            ->assertSet('error', 'Install laraowl/client in the application and deploy it before adding LaraOwl.');

        expect(LaraOwlSite::for($site))->toBeNull()
            ->and($shell->lastScript())->toContain("test -f '/home/example/example.com/current/vendor/laraowl/client/composer.json'");
    });

    test('laraowl stays disabled when the server cannot be checked', function () {
        fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(255, 'Connection refused'));
        $site = Site::factory()->create();

        laraOwlSettings($site)
            ->call('check')
            ->assertSet('installed', null)
            ->assertSee('Unable to check for laraowl/client');
    });

    test('laraowl can still be removed after the package is uninstalled', function () {
        $shell = fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "missing\n"));
        $site = Site::factory()->create();
        connectLaraOwl($site);

        laraOwlSettings($site)->call('check')->call('remove');

        expect(LaraOwlSite::for($site))->toBeNull();
        $shell->assertRan("sed -i '/^LARAOWL_TOKEN=/d'");
    });

    test('the laraowl check is remembered until the site is deployed again', function () {
        $site = Site::factory()->withRepository()->create();
        $shell = fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "missing\n"));

        laraOwlSettings($site)->call('check')->assertSet('installed', false);
        laraOwlSettings($site)->call('check')->assertSet('installed', false);

        expect(collect($shell->ran)->filter(fn (array $run) => str_contains($run['script'], 'vendor/laraowl/client')))->toHaveCount(1);

        DeploySite::dispatchSync(Deployment::factory()->for($site)->create());
        $shell->respondTo('vendor/laraowl/client', new ScriptResult(0, "installed\n"));

        laraOwlSettings($site)->call('check')->assertSet('installed', true);
    });

    test('checking for laraowl again ignores the remembered answer', function () {
        $site = Site::factory()->create();
        $shell = fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "missing\n"));

        laraOwlSettings($site)->call('check')->assertSet('installed', false);
        $shell->respondTo('vendor/laraowl/client', new ScriptResult(0, "installed\n"));

        laraOwlSettings($site)->call('recheck')->assertSet('installed', true);
    });

    test('an unreachable server is not remembered as missing laraowl', function () {
        $site = Site::factory()->create();
        $shell = fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(255, 'Connection refused'));

        laraOwlSettings($site)->call('check')->assertSet('installed', null);
        $shell->respondTo('vendor/laraowl/client', new ScriptResult(0, "installed\n"));

        laraOwlSettings($site)->call('check')->assertSet('installed', true);
    });

    test('the configure script is valid bash', function () {
        if (! Process::run('command -v bash')->successful()) {
            $this->markTestSkipped('bash is not available to check script syntax.');
        }

        $site = Site::factory()->isolated('example')->create();

        $result = Process::input(view('laraowl::scripts.configure', ['site' => $site, 'values' => ['LARAOWL_SERVER_URL' => 'https://owl.example.net', 'LARAOWL_TOKEN' => 'token']])->render())->run('bash -n');

        expect($result->successful())->toBeTrue($result->errorOutput());
    });
});

describe('members', function () {
    beforeEach(function () {
        $this->artisan('migrate')->assertSuccessful();
    });

    test('a site\'s own manager configures laraowl, and its viewers cannot', function () {
        $site = Site::factory()->create();
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $site->members()->attach($manager->id, ['role' => 'manager']);
        $site->members()->attach($viewer->id, ['role' => 'viewer']);
        fakeShell()->respondTo('vendor/laraowl/client', new ScriptResult(0, "installed\n"));

        Livewire::actingAs($viewer)->test('laraowl.site-settings', ['site' => $site])
            ->call('check')
            ->set('server_url', 'owl.example.net')->set('token', 'token')
            ->call('save');
        expect(LaraOwlSite::for($site))->toBeNull();

        Livewire::actingAs($manager)->test('laraowl.site-settings', ['site' => $site])
            ->call('check')
            ->set('server_url', 'owl.example.net')->set('token', 'token')
            ->call('save')
            ->assertHasNoErrors();
        expect(LaraOwlSite::for($site))->not->toBeNull();
    });
});
