set -Eeuo pipefail

ENV_FILE='{!! $site->path() !!}/shared/.env'

{{-- The site's user owns the .env, so it is read as that user: root would follow a link put in its place to another file. --}}
swage_as_site() {
    sudo -u {!! $site->systemUser() !!} "$@"
}

if ! swage_as_site test -f "$ENV_FILE"; then
    echo "$ENV_FILE does not exist. Install the site first." >&2
    exit 1
fi

# Make sure appended keys start on a line of their own.
if swage_as_site test -s "$ENV_FILE" && [ -n "$(swage_as_site tail -c 1 "$ENV_FILE")" ]; then
    echo | sudo -u {!! $site->systemUser() !!} tee -a "$ENV_FILE" > /dev/null
fi

# Values are validated to characters that are safe unquoted in .env and in the sed expressions below.
@foreach ($values as $key => $value)
@if ($value === null)
sudo -u {!! $site->systemUser() !!} sed -i '/^{!! $key !!}=/d' "$ENV_FILE"
@else
if swage_as_site grep -q '^{!! $key !!}=' "$ENV_FILE"; then
    sudo -u {!! $site->systemUser() !!} sed -i 's#^{!! $key !!}=.*#{!! $key !!}={!! $value !!}#' "$ENV_FILE"
else
    printf '%s\n' '{!! $key !!}={!! $value !!}' | sudo -u {!! $site->systemUser() !!} tee -a "$ENV_FILE" > /dev/null
fi
@endif
@endforeach
@if ($values['LARAOWL_SERVER_URL'] === null)
echo "Removed LaraOwl from the environment"
@else
echo "Pointed LaraOwl at {!! $values['LARAOWL_SERVER_URL'] !!}"
@endif

@include('scripts.sites.reload-config')
