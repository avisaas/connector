# AviSaaS Connector

Adds a **Marketplace** page to your admin: install, update and manage the AviSaaS extensions and themes you have bought, with a single licence key.

## Requirements

- PHP 8.3 or newer
- TastyIgniter 4 (`tastyigniter/core` ^4.0)
- Composer on the server, to install, update and delete from the page. Without it the page gives you the lines to run elsewhere (see "When an action can't run from the page" below).

## Install the connector

Run this in your site's root folder (the one with `artisan` and `composer.json`):

```
composer require avisaas/ti-ext-connector
```

A Composer-installed extension is switched on unless someone has switched it off, so there is nothing to enable under Extensions. The connector has no database tables of its own, so `php artisan igniter:up` is not needed for it. Reload the admin to see the new page.

## Open the page

Go to **Manage → Marketplace** in the side menu, between Staff members and Extensions. A Super Admin can use everything on it. Other staff need these permissions on their role:

- Opening the page needs **Manage AviSaaS Connector** (group AviSaaS).
- Install, Update and Delete also need **Ability to apply updates when a new version of TastyIgniter is available** (group Advanced).
- Enable and Disable also need **Install, uninstall and delete extension** (group Advanced).

## Connect your licence key

1. Set `APP_URL` in your site's `.env` to the real web address of your site. A licence key locks to the first website it is used on, and addresses such as `localhost` or `127.0.0.1` are refused.
2. Paste the licence key from your purchase email into the box and press **Connect**.

One key covers every extension you bought. The card then shows **Licence connected**, how many extensions the key covers, the last four characters of the key and the address it is locked to (`www.` and port numbers are left out). Use **Change key** on the card to connect a different key. If the page says the key is locked to another address, change the website in your avisaas account, under Licences, and connect again.

Connecting also prepares your site's Composer: it adds the AviSaaS download address to `composer.json` and a private download credential for this website to `auth.json`. Treat `auth.json` like a password and keep it out of public repositories.

## What you see

The Marketplace lists every AviSaaS extension and theme, with tabs for All, Installed, Updates and Not owned. Each row shows:

- its state: **Up to date** with the installed version, **Update to** a newer version, **Disabled**, **Owned** but not installed yet, **Not owned**, **Free** (needs no licence key), or the date its updates ended;
- the button that fits: **Install**, **Update**, **Enable**, **Buy**, **Renew**, or **View** for a free item that is not installed yet;
- a three-dot menu with **View details**, **Check for updates**, **Disable** and **Delete**. Delete asks first and keeps the extension's settings and data unless you tick **Also delete its settings and data**.

Installs, updates and deletes run one at a time and can take a few minutes. Keep the page open.

## When an action can't run from the page

Install, update and delete run Composer on your server. If that can't happen (Composer isn't installed, PHP isn't allowed to start it, or the site's files can't be written), the row shows a box with:

- the reason, in one line;
- whether anything on your site changed;
- the exact lines to run, with a **Copy** button.

Run the lines in a terminal in your site's root folder, or send them to your hosting provider or developer. When they have run, click **Check for updates**.

- Install and Update: `composer require <package>`, then `php artisan igniter:up`. If the extension was downloaded but could not be set up, only `php artisan igniter:up`.
- Two `composer config` lines come first only when the connector could not write `composer.json` and `auth.json` itself when you connected. One of them contains your website's private download credential: treat it like a password and never post it in public.
- Delete: `composer remove <package>`. Delete does not start when Composer is missing or PHP isn't allowed to start it, so nothing is removed. If Composer starts and then fails after you ticked **Also delete its settings and data**, the box says the settings and data are already gone and only the files remain.

If the page itself stops waiting, the action may still be running. Reload the page in a few minutes to see the result.

## The daily count

The connector sends its site address and version to avisaas once a day, along with the TastyIgniter version your site runs. This is a count only: it tells us how many sites use the connector. Removing the connector stops it.

## Advanced: a different licence server

The connector uses the AviSaaS licence server built into it, so there is nothing to set. To test against another licence server, add this to your site's `.env`:

```
AVISAAS_LICENCE_SERVER=https://licence.example.com
```

Leave it empty, or remove it, to use the built-in server again. If your site's configuration is cached, run `php artisan config:cache` again after changing it.

## License

Free to install and use on any website; not for resale or redistribution — see `LICENSE_PROPRIETARY.md`.
