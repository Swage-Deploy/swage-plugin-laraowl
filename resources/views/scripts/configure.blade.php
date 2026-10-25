set -Eeuo pipefail

ENV_FILE='{!! $site->path() !!}/shared/.env'

if [ ! -f "$ENV_FILE" ]; then
    echo "$ENV_FILE does not exist. Install the site first." >&2
    exit 1
fi

# Make sure appended keys start on a line of their own.
if [ -s "$ENV_FILE" ] && [ -n "$(tail -c 1 "$ENV_FILE")" ]; then
    echo | sudo -u {!! $site->systemUser() !!} tee -a "$ENV_FILE" > /dev/null
fi

# Values are validated to characters that are safe unquoted in .env and in the sed expressions below.
@foreach ($values as $key => $value)
@if ($value === null)
sudo -u {!! $site->systemUser() !!} sed -i '/^{!! $key !!}=/d' "$ENV_FILE"
@else
if grep -q '^{!! $key !!}=' "$ENV_FILE"; then
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
