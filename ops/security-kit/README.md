# Security kit (per-site security page + KYC attempt log)

A drop-in for the Laravel 11/12 apps on the VPS. Installing it adds one folder (`app/SecurityKit`) and one line in
`bootstrap/providers.php`. Nothing else in the app changes.

What each site gets:

- **Attempt log**: every POST to a login, sign-up, password-reset or one-time-code endpoint (`login`, `register`,
  `signup`, `otp`, `verify-otp`, `verify-code`, `confirm-email`, `two-factor-challenge`, `forgot-password`,
  `reset-password`, `password/email`) is written to `storage/logs/kyc/attempts-YYYY-MM-DD.jsonl`, one JSON
  object per line. Each line holds the time, event (`login.ok`, `login.fail`, `register.ok`, `otp.fail`,
  `*.blocked`…), IP, account (email/phone), user agent, path and HTTP status. Passwords and codes are never written.
- **Guard**: those requests are refused (HTTP 429 for JSON, otherwise back with an error) when a network has
  40+ failures in an hour, or failures on 6+ accounts in an hour, or 10+ sign-ups in an hour, or one account has
  20+ failures in a day. Limits can be changed with `SECURITY_IP_FAIL_PER_HOUR`, `SECURITY_IP_ACCOUNTS_PER_HOUR`,
  `SECURITY_IP_SIGNUPS_PER_HOUR` and `SECURITY_ACCOUNT_FAIL_PER_DAY`. `SECURITY_TRUSTED_IPS` (comma list) is
  never refused. Kenyan mobile data shares IPs (CGNAT), which is why the per-network limits are loose.
- **Page** `/admin/security-report` (signed-in admins only): this site's findings from the defense agent
  (`/home/<account>/security/site.json`, rewritten every morning), the daily trend, its domains' certificate and
  SPF/DKIM/DMARC status, and the attempt log (24 h totals, IPs with a block/watch verdict, recent failures).
  The page counts as admin for anyone listed in `SECURITY_ADMINS` (comma list of emails), anyone with a
  Spatie role such as admin, super-admin or sysadmin, or anyone with an `is_admin` / `role=admin` style column.

Each page is branded as its own site, with tabs (Overview, What to fix, Domains, Sign-in attempts). The site's name,
colour and logo come from `SECURITY_BRAND_NAME`, `SECURITY_BRAND_COLOR` and `SECURITY_BRAND_LOGO`. When those are unset,
the page falls back to the app name, red and the first logo file found in `public/`. The brands set on 9 Nov came from each homepage.
The "Security" button on the app's own pages can be turned off with `SECURITY_BADGE=false`.
Settings are read from `.env` directly when the config is cached.

sharet.africa carries the kit in its own repo (`app/SecurityKit`, branch `claude/security-dashboard`). It also has the
all-domains page, Administration › Security (`/admin/security`). Keep the two copies of the kit the same.

Turn the kit off without uninstalling it: `SECURITY_KIT=false` in `.env`, then `php artisan config:cache`.

## Install / uninstall (on the server, as root)

```bash
# copy the kit up (from this folder)
COPYFILE_DISABLE=1 tar cf - --no-xattrs install.sh app | ssh root@185.113.249.146 'mkdir -p /opt/security-kit && cd /opt/security-kit && tar xf - --no-same-owner'

bash /opt/security-kit/install.sh /home/breve.sharet.africa/public_html breve.sharet.africa
bash /opt/security-kit/install.sh /home/breve.sharet.africa/public_html breve.sharet.africa --uninstall
```

`install.sh` does the following:
1. Backs up `bootstrap/providers.php` and `bootstrap/cache` to `/root/scha-backups/security-kit-<site>-<time>.tgz`.
2. Copies the kit in, owned by the site user.
3. Adds the provider.
4. Rebuilds only the caches the app already used (config and route cache).
5. Checks that the route exists, that the home page returns the same status as before, and that the report page doesn't return a 5xx.

If any step fails, it restores the backup.

Updating the kit on a site means re-running `install.sh`, which replaces `app/SecurityKit`.

## Where it is installed

See the table in `ops/defense-agent/README.md` (section "Security kit").
