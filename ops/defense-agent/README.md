# VPS defense agent

Security checks for every domain and site on the Sharet VPS (185.113.249.146, CyberPanel + OpenLiteSpeed),
a daily/weekly email digest, and the **Setup › Server security** page in scharge (`/admin/security`).

Plain Python 3.8 standard library. Nothing to `pip install`.

| File | What it does |
|---|---|
| `agent.py` | Runs every check and writes a JSON report. Read-only apart from its own state, plus the opt-in defenses. |
| `digest.py` | Turns the latest report (compared with an earlier one) into an HTML email and sends it through local Postfix as `noreply@sharet.africa`. |
| `run.sh` | What systemd runs. Mondays: weekly mode. Other days: daily mode. |
| `defense-agent.service` / `.timer` | Run every day at 06:00 Nairobi (03:00 UTC), niced, idle I/O, CPU capped at 60%. |
| `defense-agent.env.example` | Template for `/etc/defense-agent.env`. |

## Where things live on the server

- `/opt/defense-agent/`: the scripts (copy of this folder).
- `/etc/defense-agent.env`: recipient, publish folder, enabled defenses (root, mode 600).
- `/var/lib/defense-agent/reports/*.json`: one report per run (last 90 kept). `latest.json` is a symlink.
- `/var/lib/defense-agent/baseline.json`: file-integrity baseline (size, mtime, sha256 of every PHP and .htaccess file under `/home`).
- `/home/scharge.sharet.africa/security/` and `/home/sharet.africa/security/` `{latest,history}.json`: the full report for the all-domains dashboards (scharge `/admin/security`, sharet.africa Administration › Security). `DEFENSE_PUBLISH` takes a comma list.
- `/home/<account>/security/site{,-history}.json`: one per hosting account, holding only that account's own names, apps and findings, plus the parent domain's SPF/DKIM/DMARC (`--publish-sites`). Each site's own page reads it.
- `python3 agent.py --republish` rewrites all of these from the latest report without running the checks again.
  scharge reads it via `config('server_security.report_dir')` (env `SERVER_SECURITY_DIR`).

## What it checks

- **TLS** (every vhost): cert answers, trusted chain, issuer, days left, self-signed, and whether the served cert is the latest one acme.sh holds (`/root/.acme.sh/<d>_ecc`).
- **DNS / mail auth** (via 1.1.1.1): A record resolves and points here; for domains with a DKIM key in `/etc/opendkim/keys`, SPF (single record, covers this IP), DKIM (`opendkim-testkey` against `default.private`) and DMARC.
- **HTTP** (web vhosts, 3 at a time against the local IP with SNI): status code, http→https redirect, security headers, debug/stack-trace pages, `X-Powered-By` version leaks, exposed `.env`, `.git`, Laravel logs, phpinfo, vendor, SQL dumps, and directory listings. A file only counts as exposed when its content proves it.
- **Laravel** (every `artisan` under `/home`; "live" when a vhost serves it): `APP_DEBUG`, `APP_ENV`, `APP_KEY` set, `.env` world-readable, world-writable `storage` and `bootstrap/cache`, and `composer audit` (weekly). Old copies (`laravel_prev_*`, `releases/`, `old*`) are listed as low-severity clean-ups. The agent reads only those keys from `.env`; values are never stored.
- **Server**: disk use; public ports compared with CSF `TCP_IN`; admin panels 7080/8090 open to everyone; sshd password/root settings; failed SSH logins and top IPs (24 h); CSF block list size; Postfix sent, bounced and deferred counts, queue size, OpenDKIM errors; Spamhaus, SpamCop and Barracuda listings; pending security updates; reboot needed; OS support status; root (uid 0) and sudo users; SSH keys (fingerprints only).
- **Files**: new or changed PHP files since the last run (deploys show up here), and webshell/malware patterns (eval of encoded code, visitor-controlled exec, known shell names, PHP inside uploads/images folders). Daily runs re-read only files whose size or mtime changed. Weekly runs re-read everything.

Severity: **critical** = fix today, **high** = this week, **medium** = plan it, **low** = nice to have.
A name that does not resolve gets one `dns` finding, and its other findings drop to low. Exposed secrets stay critical, because anyone can fetch them through the server IP.

## Automatic defenses (opt-in, `DEFENSE_DEFEND`)

- `block-ssh-bruteforce`: `csf -td <ip> 86400` for any IP with 20 or more failed SSH logins in 24 h. These blocks expire by themselves.
- `alert-admins`: an immediate email when a new root/sudo user or SSH key appears since the previous run. Nothing is changed.

Nothing else changes the server. Each finding comes with a fix for a person to apply.

## Running it by hand

```bash
systemctl start defense-agent.service                    # same as the timer (daily or weekly mode by weekday)
MODE=weekly sh /opt/defense-agent/run.sh                 # force weekly (audit + full rescan + weekly digest)
python3 /opt/defense-agent/agent.py --only tls,dns        # quick partial run (writes a report)
python3 /opt/defense-agent/agent.py --stdout --only http  # print JSON, write nothing
python3 /opt/defense-agent/digest.py --period daily       # preview the email text (no send)
journalctl -u defense-agent.service -n 50                 # last run's log
```

From a laptop without installing anything (read-only, nothing written on the server):

```bash
ssh root@185.113.249.146 'python3 - --stdout --only tls,dns,http' < ops/defense-agent/agent.py > report.json
```

## Deploying changes

```bash
cd ops/defense-agent && python3 -m py_compile agent.py digest.py
scp agent.py digest.py run.sh root@185.113.249.146:/opt/defense-agent/
scp defense-agent.service defense-agent.timer root@185.113.249.146:/etc/systemd/system/ && ssh root@185.113.249.146 systemctl daemon-reload
```

## Extending it

- New check: write a `check_xxx()` in `agent.py` that calls `add(scope, check, severity, title, fix)`, where `scope` is the domain, an app path, or `"server"`. Wire it into `main()` behind `want("xxx")`. The digest and the dashboard pick it up automatically, since they only read `findings`.
- New exposed path: add a line to `EXPOSED` (path, regex that proves it is real, severity, label).
- New malware pattern: add to `MALWARE`. Keep patterns specific; vendor code is noisy.
- New defense: add a branch to `defend()` under its own name, and enable it in `/etc/defense-agent.env`.
- Thresholds: `EXPECTED_PUBLIC_PORTS`, the SSH-block threshold (20) in `defend()`, and the days-left limits in `check_tls()`.

## Security kit (per-site page + attempt log)

Every live Laravel app also runs the security kit (`ops/security-kit`, see its README). It provides:
- `/admin/security-report`, built from the `/home/<account>/security/site.json` the agent writes with `--publish-sites`
- a JSON Lines log of login, sign-up, password and code attempts, with a guard that refuses guessing networks and accounts
- a small "Security" button on the app's pages for people allowed to see the report

`SECURITY_ADMINS` on every app is set to kibettdennis@gmail.com, dennis@sharet.africa and admin@sharet.africa.
The app's own super-admin/sysadmin users can see the page too.

| Site | App folder | Who can open it (2026-11-09) |
|---|---|---|
| sharet.africa | /home/sharet.africa/public_html | admin@sharet.africa, kibettdennis@ (menu: admin › Security; badge off) |
| breve.sharet.africa | /home/breve.sharet.africa/public_html | kibettdennis@, dennis@sharet.africa |
| aiyotickets.com | /home/aiyotickets.com/laravel | kibettdennis@ |
| eserviz.aiyotickets.com | /home/eserviz.aiyotickets.com/eserviz | sysadmin@eserviz.co.ke |
| chatbotfaqs.sharet.africa | /home/chatbotfaqs.sharet.africa/laravel | kibettdennis@ + 4 superadmins (eCitizen staff) |
| fitbeat.sharet.africa | /home/fitbeat.sharet.africa/laravel | kibettdennis@ |
| followup.sharet.africa | /home/followup.sharet.africa/laravel | nobody yet (none of your emails has an account) |
| invites.aiyotickets.com | /home/invites.aiyotickets.com/laravel | kibettdennis@ |
| loans.sharet.africa | /home/loans.sharet.africa/laravel | kibettdennis@, sysadmin@aimzoneventures.com |
| ndoams.sharet.africa | /home/ndoams.sharet.africa/laravel | kibettdennis@ |
| thewillishotel.co.ke | /home/thewillishotel.co.ke/laravel | kibettdennis@ |

Left out on purpose (kit removed 9 Nov; `DEFENSE_SITES_SKIP` stops their per-site report): mensch.sharet.africa and
menschtech.co.ke (Mensch Tech). The server-wide checks still cover them.
Not installed: scharge (it has the full `/admin/security` dashboard and its own KYC log). Sites whose names do not
resolve were also skipped: invites.sharet.africa, lendu, renteasy.co.ke, sharetweddings.imaginenation.co.ke.

Backups from every install: `/root/scha-backups/security-kit-*.tgz` (providers + bootstrap/cache) and
`/root/scha-backups/env-<domain>-*` (each .env before SECURITY_ADMINS was added).
