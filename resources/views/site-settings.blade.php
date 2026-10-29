<div>
    <x-panel icon="eye" :heading="__('LaraOwl monitoring')" :description="__('Reports requests, exceptions, queries and jobs to your LaraOwl server. The application must require laraowl/client; Swage sets LARAOWL_SERVER_URL, LARAOWL_TOKEN and any sample rates in its .env.')">
        @if ($connection)
            <x-slot:actions>
                <x-status color="green" :label="__('Reporting')" />
            </x-slot:actions>
        @endif

        <div wire:init="check">
        @php($disabled = $installed !== true)
        <form wire:submit="save" class="flex flex-col gap-6">
            @if (! $checked)
                <div class="flex items-center gap-2 text-zinc-500">
                    <flux:icon.arrow-path class="size-4 animate-spin" />
                    <flux:text>{{ __('Checking the site for laraowl/client...') }}</flux:text>
                </div>
            @elseif ($installed !== true)
                <flux:callout icon="information-circle" color="amber">
                    <flux:callout.heading>
                        {{ $installed === false ? __('laraowl/client is not installed') : __('Unable to check for laraowl/client') }}
                    </flux:callout.heading>
                    <flux:callout.text>
                        @if ($installed === false)
                            {{ __('Run :command in the application, then deploy the site.', ['command' => 'composer require laraowl/client']) }}
                        @else
                            {{ __('The server could not be reached. Try again in a moment.') }}
                        @endif
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button size="sm" wire:click="recheck">{{ __('Check again') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:input wire:model="server_url" :label="__('Server domain')" placeholder="owl.example.com" class="font-mono" :disabled="$disabled" />
            <flux:input wire:model="token" type="password" viewable :label="__('Project token')" :placeholder="$connection ? __('Saved. Leave blank to keep it.') : ''" class="font-mono" autocomplete="off" :disabled="$disabled" />

            <flux:fieldset>
                <flux:legend>{{ __('Sample rates') }}</flux:legend>
                <flux:description>{{ __('The share of each kind of event to report, from 0 to 1, such as 0.1 for one in ten. Leave blank to report all of them.') }}</flux:description>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="sample_rates.sample_requests" :label="__('Requests')" placeholder="1.0" class="font-mono" :disabled="$disabled" />
                    <flux:input wire:model="sample_rates.sample_commands" :label="__('Commands')" placeholder="1.0" class="font-mono" :disabled="$disabled" />
                    <flux:input wire:model="sample_rates.sample_exceptions" :label="__('Exceptions')" placeholder="1.0" class="font-mono" :disabled="$disabled" />
                    <flux:input wire:model="sample_rates.sample_tasks" :label="__('Scheduled tasks')" placeholder="1.0" class="font-mono" :disabled="$disabled" />
                </div>
            </flux:fieldset>

            @if ($error)
                <flux:callout icon="x-circle" color="red">
                    <flux:callout.heading>{{ __('The .env could not be updated') }}</flux:callout.heading>
                    <flux:callout.text class="whitespace-pre-wrap font-mono text-xs">{{ $error }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" :disabled="$disabled">{{ $connection ? __('Save') : __('Add LaraOwl') }}</flux:button>
                @if ($connection)
                    <flux:button wire:click="remove" variant="ghost">{{ __('Remove') }}</flux:button>
                @endif
            </div>
        </form>
        </div>
    </x-panel>
</div>
