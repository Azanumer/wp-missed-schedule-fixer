# Missed Schedule Fixer (WordPress plugin)

WordPress relies on WP-Cron to publish scheduled posts, and WP-Cron only runs
when someone visits the site — so low-traffic sites often end up with posts
stuck as **"Missed schedule"**. This plugin fixes that.

## What it does

- Runs an hourly check for posts still in `future` status past their scheduled time.
- Publishes them automatically, oldest first (up to 100 per run).
- A configurable **grace period** (default 15 minutes) avoids rushing a post
  that is only a few minutes late.
- A **lookback window** (default 7 days) leaves very old missed posts alone.
- Optional email summary listing every post it recovered.
- Tools → Missed Schedule page with a **Check now** button.

## Install

1. Copy this folder to `wp-content/plugins/wp-missed-schedule-fixer/`.
2. Activate **Missed Schedule Fixer** in Plugins.
3. Set grace period / lookback / notify email under Tools → Missed Schedule.

## Filters

- `wmsf_default_settings` — override the default grace period, lookback, or email.

## Notes

- For high-traffic or mission-critical scheduling, also set up a real system
  cron hitting `wp-cron.php` — this plugin is the safety net, not a
  replacement for a reliable cron.
- Deactivation clears the scheduled check. Uninstall removes the option.

## License

MIT — see `LICENSE`.
