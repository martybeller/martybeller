# Marty Beller Site

Astro site deployed to GitHub Pages.

## Commands

Run all commands from the project root.

| Command | Action |
| :-- | :-- |
| `npm install` | Install dependencies |
| `npm run dev` | Start local dev server |
| `npm run build` | Build static site to `dist/` |
| `npm run preview` | Preview production build locally |

## Deployment

GitHub Pages deploy is handled by [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

It now triggers on:

1. Push to `main`
2. Manual `workflow_dispatch`
3. `repository_dispatch` with event type `wp-content-updated`
4. A 6-hour schedule (`cron: 0 */6 * * *`) as a fallback sync

## WordPress -> GitHub Auto Redeploy

Use this when WordPress content changes should immediately redeploy the static site.

### 1. Create a GitHub token for WordPress

1. Create a fine-grained PAT in GitHub.
2. Scope it to this repo only.
3. Grant repository permission: `Actions` = `Read and write`.
4. Save the token securely.

### 2. Install the WordPress webhook bridge

1. Copy [docs/wp-github-redeploy.php](docs/wp-github-redeploy.php) into your WordPress install as:
	`wp-content/mu-plugins/wp-github-redeploy.php`
2. Edit the constants at the top of that file:
	`MB_GITHUB_REPO` and optional post types.
3. Add the token in `wp-config.php`, not in the plugin file:

```php
define('MB_GITHUB_TOKEN', 'YOUR_FINE_GRAINED_TOKEN');
define('MB_GITHUB_REPO', 'martybeller/martybeller');
```

The plugin dispatches GitHub redeploy events when relevant posts are created, updated, trashed, restored, or deleted.

To avoid redundant builds during burst edits, it also debounces dispatches globally (default: 120 seconds). You can tune this by defining:

```php
define('MB_GITHUB_DISPATCH_DEBOUNCE_SECONDS', 120);
```

in `wp-config.php`.

### 3. Verify end-to-end

1. Update or trash a `videos` or `work` post in WordPress.
2. Check GitHub Actions for a new run of the deploy workflow.
3. After deploy completes, purge Cloudflare cache for `/` and `/work/` if needed.
