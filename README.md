<p align="center">
    <img src="https://raw.githubusercontent.com/Swage-Deploy/swage-core/main/public/favicon.svg" width="72" height="72" alt="Swage">
</p>

<h1 align="center">LaraOwl for Swage</h1>

<p align="center">
    Report requests, exceptions, queries and jobs from your sites to a LaraOwl monitoring server.
    <br>
    <a href="https://swagedeploy.app"><strong>swagedeploy.app</strong></a> · <a href="https://swagedeploy.app/docs/plugins">Documentation</a>
</p>

---

Connects sites to a LaraOwl monitoring server, which collects their requests, exceptions, queries and jobs. Each site's **Plugins** tab gets a **LaraOwl monitoring** panel where you enter the LaraOwl server's domain and the project's token. Swage writes them into the site's `.env` as `LARAOWL_SERVER_URL` and `LARAOWL_TOKEN`, rebuilds its config cache and reloads PHP.

## Requirements

- Swage `v1.0.1` or later
- The `laraowl/client` package in each application you connect

## Installation

1. **Install the plugin** from the **Available** list on Swage's **Plugins** page, where it is listed as LaraOwl. Swage installs the newest version, rebuilds itself with it and shows its progress.

   From the Swage host instead:

   ```bash
   sudo -u swage php /home/swage/app/current/artisan swage:plugin:install swage/laraowl
   ```

2. **Require the client in the application** and deploy the site:

   ```bash
   composer require laraowl/client
   ```

   Swage only configures the client. The panel checks the site's live release for `laraowl/client` and stays disabled until it is there. The answer is remembered until the site is next deployed; **Check again** asks the server straight away.

3. **Connect the site** on its **Plugins** tab: enter the LaraOwl server's domain, such as `owl.example.com`, and the project token.

   Optionally set **sample rates** for requests, commands, exceptions and scheduled tasks: the share of each to report, from 0 to 1, such as `0.1` for one in ten. Swage writes them as `LARAOWL_SAMPLE_REQUESTS`, `LARAOWL_SAMPLE_COMMANDS`, `LARAOWL_SAMPLE_EXCEPTIONS` and `LARAOWL_SAMPLE_TASKS`. A blank rate is removed from the `.env`, so the client reports everything of that kind.

Tokens are stored encrypted, never sent back to the browser, and redacted from task output. Leave the token blank when saving to keep the one already saved. **Remove** deletes all of these variables from the `.env`.

## Upgrading from Swage's built-in LaraOwl settings

Swage used to keep the LaraOwl server and token on each site. Installing the plugin moves them into its own `laraowl_sites` table and drops the old columns, so connected sites keep reporting without being set up again.

## Members

A site's managers, including people added to just that site, can change its settings here; its viewers can only look.

## Updating

Swage's **Plugins** page shows when a newer version is available. Update from there, or on the Swage host:

```bash
sudo -u swage php /home/swage/app/current/artisan swage:plugin:update swage/laraowl
```

## Development

The plugin implements Swage's site integration contract (`App\Sites\SiteIntegration`), version 1, so it runs inside a Swage install rather than on its own.

`tests/LaraOwlPluginTest.php` is a Pest test that runs inside a Swage checkout: it uses Swage's models, factories and test helpers. To run it:

1. Add this plugin's `src` directory to the Swage checkout's `autoload-dev` as `Swage\\LaraOwl\\`.
2. Run `composer dump-autoload`.
3. Copy the test into Swage's `tests/Feature`.
4. Run `php artisan test --filter=LaraOwlPluginTest`.

## License

Copyright (c) 2026 Node Dog Consulting. Released under the [MIT License](LICENSE).
