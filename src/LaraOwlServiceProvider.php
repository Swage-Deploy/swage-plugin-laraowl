<?php

namespace Swage\LaraOwl;

use App\Sites\SiteIntegrations;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Swage\LaraOwl\Livewire\SiteSettings;

class LaraOwlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laraowl');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Livewire::component('laraowl.site-settings', SiteSettings::class);

        $this->app->make(SiteIntegrations::class)->register(LaraOwlIntegration::class);
    }
}
