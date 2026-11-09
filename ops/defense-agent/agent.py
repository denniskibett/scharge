#!/usr/bin/env python3
"""
Sharet VPS defense agent: security checks for every site and domain on this server.

Read-only by default. Writes a JSON report (and keeps a file-integrity baseline) under STATE_DIR
when it can; with --stdout it only prints the JSON and writes nothing (used for the first run over SSH).
Optional, opt-in defenses: --defend (see DEFENSES below). Standard library only (Python 3.8).

  python3 agent.py                 # full run, report -> /var/lib/defense-agent/reports/
  python3 agent.py --stdout        # print JSON, touch nothing
  python3 agent.py --only tls,dns  # some checks only
  python3 agent.py --digest daily --mail-to you@example.com   # also email the HTML digest

Docs: ops/defense-agent/README.md
"""
import argparse, concurrent.futures as cf, datetime as dt, glob, hashlib, http.client, json, os, re, socket, ssl
import subprocess, sys, time

SERVER_IP = "185.113.249.146"
STATE_DIR = os.environ.get("DEFENSE_STATE", "/var/lib/defense-agent")
VHOSTS = "/usr/local/lsws/conf/vhosts"
ACME = "/root/.acme.sh"
DKIM_KEYS = "/etc/opendkim/keys"
RESOLVER = "1.1.1.1"
EXPECTED_PUBLIC_PORTS = {20, 21, 22, 25, 53, 80, 110, 143, 443, 465, 587, 993, 995, 4190, 7080, 8090}
SEV = {"critical": 4, "high": 3, "medium": 2, "low": 1, "ok": 0}
UA = "sharet-defense-agent/1.0"
NOW = dt.datetime.now(dt.timezone.utc)

findings = []  # every check appends here


def add(scope, check, severity, title, fix=None, detail=None):
    findings.append({"scope": scope, "check": check, "severity": severity, "title": title,
                     "fix": fix, "detail": detail})


def sh(cmd, timeout=60):
    try:
        r = subprocess.run(cmd, shell=isinstance(cmd, str), capture_output=True, text=True, timeout=timeout)
        return r.returncode, r.stdout, r.stderr
    except subprocess.TimeoutExpired:
        return 124, "", "timeout"
    except Exception as e:  # noqa
        return 1, "", str(e)


def dig(name, rtype):
    _, out, _ = sh(["dig", "+short", "+time=3", "+tries=2", f"@{RESOLVER}", name, rtype], timeout=15)
    return [l.strip() for l in out.splitlines() if l.strip()]


def txt(name):
    return ["".join(re.findall(r'"([^"]*)"', l)) or l for l in dig(name, "TXT")]


# ---------------------------------------------------------------- inventory

def inventory():
    """Every vhost (except CyberPanel's Example), its docroot and owning /home account."""
    sites = []
    for conf in sorted(glob.glob(f"{VHOSTS}/*/vhost.conf")):
        name = os.path.basename(os.path.dirname(conf))
        if name == "Example":
            continue
        text = open(conf, errors="ignore").read()
        root = (re.search(r"^\s*docRoot\s+(\S+)", text, re.M) or [None, ""])[1]
        vhroot = (re.search(r"^\s*vhRoot\s+(\S+)", text, re.M) or [None, f"/home/{name}"])[1]
        root = root.replace("$VH_ROOT", vhroot.rstrip("/")).rstrip("/")
        cert = (re.search(r"^\s*certFile\s+(\S+)", text, re.M) or [None, None])[1]
        sites.append({"domain": name, "docroot": root, "home": vhroot.rstrip("/"), "cert_file": cert,
                      "mail_host": name.startswith("mail.")})
    return sites


def apex(domain):
    parts = domain.split(".")
    n = 3 if domain.endswith((".co.ke", ".or.ke", ".ac.ke")) else 2
    return ".".join(parts[-n:])


# ---------------------------------------------------------------- TLS

def fetch_cert(host, ip):
    """(verified_ok, error, der) for the certificate the server presents for `host`."""
    der, err, ok = None, None, False
    try:
        ctx = ssl.create_default_context()
        with socket.create_connection((ip, 443), timeout=8) as s, ctx.wrap_socket(s, server_hostname=host) as t:
            der, ok = t.getpeercert(binary_form=True), True
    except ssl.SSLCertVerificationError as e:
        err = e.verify_message or str(e)
    except Exception as e:  # noqa
        err = str(e)
    if der is None:
        try:
            ctx = ssl._create_unverified_context()
            with socket.create_connection((ip, 443), timeout=8) as s, ctx.wrap_socket(s, server_hostname=host) as t:
                der = t.getpeercert(binary_form=True)
        except Exception as e:  # noqa
            err = err or str(e)
    return ok, err, der


def x509_info(pem_or_der, der=False):
    args = ["openssl", "x509", "-noout", "-enddate", "-issuer", "-subject", "-fingerprint", "-sha256"]
    if der:
        args += ["-inform", "DER"]
    r = subprocess.run(args, input=pem_or_der, capture_output=True)
    out = r.stdout.decode(errors="ignore")
    end = re.search(r"notAfter=(.+)", out)
    expires = dt.datetime.strptime(end.group(1).strip(), "%b %d %H:%M:%S %Y %Z").replace(tzinfo=dt.timezone.utc) if end else None
    issuer = re.search(r"issuer=.*?O ?= ?([^,\n]+)", out) or re.search(r"issuer=(.+)", out)
    fp = re.search(r"Fingerprint=(\S+)", out)
    return {"expires": expires.isoformat() if expires else None,
            "days_left": (expires - NOW).days if expires else None,
            "issuer": issuer.group(1).strip() if issuer else None,
            "self_signed": bool(re.search(r"issuer=(.+)", out) and re.search(r"subject=(.+)", out)
                                and re.search(r"issuer=(.+)", out).group(1) == re.search(r"subject=(.+)", out).group(1)),
            "sha256": fp.group(1) if fp else None}


def check_tls(site, ip):
    d = site["domain"]
    ok, err, der = fetch_cert(d, ip)
    res = {"verified": ok, "error": err}
    if der:
        res.update(x509_info(der, der=True))
    acme = f"{ACME}/{d}_ecc/{d}.cer"
    if os.path.exists(acme):
        a = x509_info(open(acme, "rb").read())
        res["acme_expires"], res["acme_sha256"] = a["expires"], a["sha256"]
        res["serving_latest"] = a["sha256"] == res.get("sha256")
    days = res.get("days_left")
    if der is None:
        add(d, "tls", "high", "No HTTPS answer", "Check the vhost listener / DNS for this name.", err)
    elif res.get("self_signed"):
        add(d, "tls", "high", "Self-signed certificate", f"Issue a real cert: /root/.acme.sh/acme.sh --issue -d {d} -w {site['docroot']}")
    elif days is not None and days < 0:
        sev = "critical" if not site["mail_host"] else "high"
        hint = (" acme.sh already has a newer one: install it (acme.sh --install-cert … --reloadcmd 'systemctl restart lsws')."
                if res.get("acme_expires") and res.get("serving_latest") is False and (res.get("acme_expires") or "") > NOW.isoformat() else
                " Renew with acme.sh or the issuer.")
        add(d, "tls", sev, f"Certificate expired {-days} days ago ({res.get('issuer')})", hint.strip())
    elif not ok:
        add(d, "tls", "high", f"Certificate not trusted: {err}", "Serve the full chain (fullchain.pem) and a cert that covers this name.")
    elif days is not None and days < 14:
        add(d, "tls", "high" if days < 7 else "medium", f"Certificate expires in {days} days",
            "Make sure acme.sh renews it and installs it to the path LiteSpeed reads (--install-cert).")
    if res.get("serving_latest") is False and (days is None or days >= 0):
        add(d, "tls", "low", "Served certificate is not the latest one acme.sh holds",
            "Add an --install-cert hook so renewals reach /etc/letsencrypt/live/.")
    return res


# ---------------------------------------------------------------- DNS / mail auth

def check_dns(domain, sends_mail):
    a = dig(domain, "A")
    res = {"a": a, "points_here": SERVER_IP in a}
    if not a:
        add(domain, "dns", "high", "Domain does not resolve", "Fix the A record at the DNS host, or remove the site.")
        return res
    if not res["points_here"]:
        add(domain, "dns", "low", f"A record points elsewhere ({', '.join(a)})", "Fine if hosted elsewhere; otherwise remove the vhost.")
    if not sends_mail:
        return res
    spf = [t for t in txt(domain) if t.lower().startswith("v=spf1")]
    res["spf"] = spf[0] if spf else None
    if not spf:
        add(domain, "spf", "high", "No SPF record", f'Add TXT @ "v=spf1 a mx ip4:{SERVER_IP} ~all"')
    elif len(spf) > 1:
        add(domain, "spf", "high", "More than one SPF record (both are ignored)", "Merge them into one TXT record.")
    elif SERVER_IP not in spf[0] and " a " not in f" {spf[0]} " and " mx " not in f" {spf[0]} ":
        add(domain, "spf", "medium", "SPF does not cover this server", f"Add ip4:{SERVER_IP} to the SPF record.")
    dmarc = [t for t in txt(f"_dmarc.{domain}") if t.lower().startswith("v=dmarc1")]
    res["dmarc"] = dmarc[0] if dmarc else None
    if not dmarc:
        add(domain, "dmarc", "medium", "No DMARC record", f'Add TXT _dmarc "v=DMARC1; p=none; rua=mailto:postmaster@{domain}" then tighten to quarantine.')
    key = f"{DKIM_KEYS}/{domain}/default.private"
    if os.path.exists(key):
        code, out, errs = sh(["opendkim-testkey", "-d", domain, "-s", "default", "-k", key, "-vvv"], timeout=30)
        text = out + errs
        res["dkim"] = "ok" if "key OK" in text or "key not secure" in text else ("missing" if "record not found" in text or "No such" in text else "mismatch")
        if res["dkim"] == "missing":
            add(domain, "dkim", "high", "DKIM record default._domainkey not published",
                f"Publish the TXT from {DKIM_KEYS}/{domain}/default.txt at the DNS host.")
        elif res["dkim"] == "mismatch":
            add(domain, "dkim", "high", "DKIM record does not match the server's signing key",
                f"Replace the DNS TXT default._domainkey with the contents of {DKIM_KEYS}/{domain}/default.txt.")
    else:
        res["dkim"] = "no-key"
        add(domain, "dkim", "medium", "Server has no DKIM key for this domain", "Enable DKIM for it in CyberPanel › Email › DKIM Manager.")
    return res


# ---------------------------------------------------------------- HTTP

def request(host, path="/", https=True, method="GET", ip=SERVER_IP, limit=4096, retry=1):
    r = _request(host, path, https, method, ip, limit)
    if r[0] is None and retry and "timed out" in r[2]:
        time.sleep(3)
        return _request(host, path, https, method, ip, limit)
    return r


def _request(host, path, https, method, ip, limit):
    try:
        cls = http.client.HTTPSConnection if https else http.client.HTTPConnection
        kw = {"context": ssl._create_unverified_context()} if https else {}
        c = cls(ip, 443 if https else 80, timeout=25, **kw)
        if https:  # SNI must carry the host name
            c.sock = c._context.wrap_socket(socket.create_connection((ip, 443), timeout=25), server_hostname=host)
        c.request(method, path, headers={"Host": host, "User-Agent": UA})
        r = c.getresponse()
        body = r.read(limit).decode(errors="ignore")
        return r.status, {k.lower(): v for k, v in r.getheaders()}, body
    except Exception as e:  # noqa
        return None, {}, str(e)


EXPOSED = [  # path, regex that proves real content, severity, what it is
    ("/.env", r"^(APP_KEY|DB_PASSWORD|APP_ENV)=", "critical", ".env (passwords and keys) is downloadable"),
    ("/.git/HEAD", r"^ref: refs/", "critical", ".git folder is downloadable (full source code)"),
    ("/.git/config", r"\[core\]", "critical", ".git folder is downloadable"),
    ("/storage/logs/laravel.log", r"\.(ERROR|INFO|WARNING|DEBUG):", "high", "Laravel log is public"),
    ("/laravel/storage/logs/laravel.log", r"\.(ERROR|INFO|WARNING|DEBUG):", "high", "Laravel log is public"),
    ("/phpinfo.php", r"PHP Version|phpinfo\(\)", "high", "phpinfo page is public"),
    ("/info.php", r"PHP Version|phpinfo\(\)", "high", "phpinfo page is public"),
    ("/composer.json", r'"require"\s*:', "low", "composer.json is public (reveals package versions)"),
    ("/vendor/composer/installed.json", r'"packages"|"name"\s*:', "medium", "vendor folder is public"),
    ("/backup.sql", r"(CREATE TABLE|INSERT INTO)", "critical", "Database dump is public"),
    ("/.DS_Store", r"Bud1", "low", ".DS_Store is public (lists files)"),
    ("/wp-config.php.bak", r"DB_PASSWORD", "critical", "WordPress config backup is public"),
]
LISTING_PATHS = ["/storage/", "/uploads/", "/images/", "/assets/", "/vendor/", "/backup/"]
HEADERS = {"strict-transport-security": "medium", "x-content-type-options": "low", "x-frame-options": "low",
           "referrer-policy": "low", "content-security-policy": "low"}


def check_http(site):
    d = site["domain"]
    res = {}
    st, h, body = request(d, https=True)
    res["status"] = st
    if st is None:
        add(d, "uptime", "critical", "Site does not answer on HTTPS", "Check LiteSpeed and the vhost.", body[:200])
        return res
    if st >= 500:
        add(d, "uptime", "critical", f"Home page returns HTTP {st}", "Look at the app log (storage/logs) and the LiteSpeed error log.")
    elif st in (403, 404):
        add(d, "uptime", "medium", f"Home page returns HTTP {st}", "Point the docroot at the app's public folder, or remove the vhost.")
    if "x-powered-by" in h and re.search(r"\d", h["x-powered-by"]):
        add(d, "headers", "low", f"Reveals software version ({h['x-powered-by']})", "Set expose_php = Off in the lsphp php.ini.")
    missing = [k for k in HEADERS if k not in h]
    res["missing_headers"] = missing
    if missing:
        worst = max((HEADERS[k] for k in missing), key=lambda s: SEV[s])
        add(d, "headers", worst, "Missing security headers: " + ", ".join(missing),
            "Add them in the vhost (LiteSpeed › context › extra headers) or the app middleware.")
    if "Whoops!" in body or "Ignition" in body or "Stack trace" in body:
        add(d, "debug", "critical", "Error page shows a stack trace / debug screen", "Set APP_DEBUG=false and run php artisan config:cache.")
    st80, h80, _ = request(d, https=False)
    res["http_redirect"] = st80 in (301, 302, 307, 308) and h80.get("location", "").startswith("https://")
    if st80 is not None and not res["http_redirect"]:
        add(d, "redirect", "medium", f"http:// does not redirect to https:// (HTTP {st80})",
            "Add a rewrite to https in the vhost (CyberPanel › Websites › Rewrite Rules › Force HTTP → HTTPS).")
    for path, proof, sev, what in EXPOSED:
        s, _, b = request(d, path)
        if s == 200 and re.search(proof, b, re.M):
            add(d, "exposed", sev, f"{what} ({path})", "Block it in the vhost / .htaccess, move it out of the docroot, and rotate any secrets it contained.")
    for path in LISTING_PATHS:
        s, _, b = request(d, path)
        if s == 200 and re.search(r"<title>Index of|Directory listing for", b, re.I):
            add(d, "listing", "medium", f"Directory listing is on ({path})", "Turn off autoIndex in the vhost or add 'Options -Indexes'.")
    return res


# ---------------------------------------------------------------- Laravel apps

def laravel_apps(sites):
    """Every Laravel app under /home; 'live' when a vhost docroot is inside it."""
    serves = {}  # app dir -> domains
    for s in sites:
        r = os.path.realpath(s["docroot"])
        targets = {r, os.path.dirname(r)}  # docroot is the app's public/ or the app itself
        idx = os.path.join(r, "index.php")
        if os.path.exists(idx):  # public_html/index.php requiring __DIR__.'/../laravel/vendor/autoload.php'
            for m in re.finditer(r"__DIR__\s*\.\s*['\"](/[^'\"]*?)/(?:vendor|bootstrap|storage)/", open(idx, errors="ignore").read()[:5000]):
                targets.add(os.path.realpath(os.path.normpath(r + m.group(1))))
        for t in targets:
            serves.setdefault(t, set()).add(s["domain"])
    apps = []
    for artisan in sorted(set(glob.glob("/home/*/*/artisan") + glob.glob("/home/*/*/*/artisan"))):
        app = os.path.dirname(artisan)
        if "/vendor/" in app or "/node_modules/" in app:
            continue
        apps.append({"path": app, "live_for": sorted(serves.get(os.path.realpath(app), set()))})
    return apps


def env_value(text, key):
    m = re.search(rf"^{key}\s*=\s*\"?([^\"\n#]*)", text, re.M)
    return m.group(1).strip() if m else None


def check_laravel(app, do_audit):
    p, scope = app["path"], (app["live_for"][0] if app["live_for"] else app["path"])
    res = {"path": p, "live_for": app["live_for"]}
    envf = os.path.join(p, ".env")
    if not app["live_for"]:
        if re.search(r"(_prev_|backup|/old$|-l10$)", p):
            add(p, "stale-copy", "low", "Old copy of an app is lying around", "Delete it or move it to /root/scha-backups once you no longer need it.")
        res["live"] = False
        return res
    res["live"] = True
    if os.path.exists(envf):
        text = open(envf, errors="ignore").read()
        res["app_env"], res["app_debug"], res["mail"] = env_value(text, "APP_ENV"), env_value(text, "APP_DEBUG"), env_value(text, "MAIL_MAILER")
        if (res["app_debug"] or "").lower() == "true":
            add(scope, "laravel", "critical", f"APP_DEBUG=true in {p}/.env", "Set APP_DEBUG=false, then php artisan config:cache.")
        if (res["app_env"] or "").lower() not in ("production", "prod"):
            add(scope, "laravel", "medium", f"APP_ENV={res['app_env']} in {p}/.env", "Set APP_ENV=production.")
        if not env_value(text, "APP_KEY"):
            add(scope, "laravel", "critical", "APP_KEY is empty", "php artisan key:generate (then log everyone in again).")
        mode = os.stat(envf).st_mode
        if mode & 0o004:
            add(scope, "laravel", "high", f".env is world-readable ({oct(mode & 0o777)})", f"chmod 640 {envf}")
    else:
        add(scope, "laravel", "medium", "No .env file", "The app may be running on defaults.")
    for sub in ("storage", "bootstrap/cache"):
        sp = os.path.join(p, sub)
        if os.path.isdir(sp) and os.stat(sp).st_mode & 0o002:
            add(scope, "laravel", "medium", f"{sub} is world-writable (777)", f"chmod -R o-w {sp} (owner = the site user, 775 at most).")
    if do_audit and os.path.exists(os.path.join(p, "composer.lock")):
        code, out, err = sh(f"cd {p!r} && COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=256M timeout 90 nice -n 19 composer audit --locked --format=json --no-interaction 2>/dev/null", timeout=100)
        try:
            data = json.loads(out[out.index("{"):])
            advisories = data.get("advisories") or {}
            n = sum(len(v) for v in advisories.values()) if isinstance(advisories, dict) else 0
            res["advisories"] = n
            if n:
                pkgs = sorted(advisories)[:8]
                sev = "high" if any(a.get("severity") in ("high", "critical") for v in advisories.values() for a in v) else "medium"
                add(scope, "composer", sev, f"{n} known vulnerabilities in packages ({', '.join(pkgs)})",
                    f"cd {p} && composer update {' '.join(pkgs)} --with-dependencies (test first).")
        except Exception:  # noqa
            res["advisories"] = None
    return res


# ---------------------------------------------------------------- server

def since_today(lines, days=1):
    """auth.log/mail.log lines from the last `days` days (syslog has no year)."""
    cutoff = dt.datetime.now() - dt.timedelta(days=days)
    out = []
    for l in lines:
        try:
            t = dt.datetime.strptime(f"{dt.datetime.now().year} {l[:15]}", "%Y %b %d %H:%M:%S")
        except ValueError:
            continue
        if t >= cutoff:
            out.append(l)
    return out


def check_server():
    s = {}
    # disk / memory / load
    st = os.statvfs("/")
    s["disk_used_pct"] = round(100 * (1 - st.f_bavail / st.f_blocks), 1)
    if s["disk_used_pct"] > 90:
        add("server", "disk", "critical", f"Disk {s['disk_used_pct']}% full", "Clear old backups in /root/scha-backups, logs and laravel_prev_* folders.")
    elif s["disk_used_pct"] > 80:
        add("server", "disk", "medium", f"Disk {s['disk_used_pct']}% full", "Clear old backups and laravel_prev_* copies.")
    s["load"] = os.getloadavg()
    # open ports
    _, out, _ = sh("ss -tlnH")
    public = set()
    for l in out.splitlines():
        addr = l.split()[3]
        host, port = addr.rsplit(":", 1)
        if not host.startswith(("127.", "[::1]")) and port.isdigit():
            public.add(int(port))
    s["public_ports"] = sorted(public)
    unexpected = sorted(public - EXPECTED_PUBLIC_PORTS)
    _, csfconf, _ = sh("grep -E '^TCP_IN' /etc/csf/csf.conf")
    allowed = set(int(x) for x in re.findall(r"\d+", csfconf.split("=", 1)[-1])) if csfconf else set()
    exposed = [p for p in unexpected if p in allowed]
    if exposed:
        add("server", "ports", "high", f"Unexpected ports open to the internet: {exposed}", "Close them in CSF TCP_IN unless needed.")
    elif unexpected:
        add("server", "ports", "low", f"Services listen on {unexpected} (firewalled by CSF)", "Bind them to 127.0.0.1 if they do not need the network.")
    for p in (7080, 8090):
        if p in public and p in allowed:
            add("server", "ports", "medium", f"Admin panel port {p} open to everyone",
                f"Allow only your IPs: add 'tcp|in|d={p}|s=<your IP>' to /etc/csf/csf.allow and drop {p} from TCP_IN.")
    # SSH brute force
    try:
        auth = since_today(open("/var/log/auth.log", errors="ignore").read().splitlines())
    except Exception:  # noqa
        auth = []
    fails = [l for l in auth if re.search(r"Failed password|Invalid user|authentication failure", l)]
    ips = {}
    for l in fails:
        m = re.search(r"from (\d+\.\d+\.\d+\.\d+)", l)
        if m:
            ips[m.group(1)] = ips.get(m.group(1), 0) + 1
    s["ssh_failed_24h"], s["ssh_top_ips"] = len(fails), sorted(ips.items(), key=lambda x: -x[1])[:10]
    s["ssh_root_logins_24h"] = len([l for l in auth if re.search(r"Accepted \S+ for root", l)])
    _, sshd, _ = sh("sshd -T 2>/dev/null | grep -Ei '^(passwordauthentication|permitrootlogin) '")
    s["sshd"] = dict(l.split() for l in sshd.splitlines() if len(l.split()) == 2)
    if s["sshd"].get("passwordauthentication") == "yes":
        add("server", "ssh", "high", "SSH accepts passwords", "Key login already works: set PasswordAuthentication no in /etc/ssh/sshd_config and reload sshd.")
    if s["sshd"].get("permitrootlogin") == "yes":
        add("server", "ssh", "medium", "Root may log in with a password", "Set PermitRootLogin prohibit-password.")
    if len(fails) > 200:
        add("server", "ssh", "medium", f"{len(fails)} failed SSH logins in 24 h", "Expected noise if passwords are off; CSF/lfd blocks repeat offenders.")
    # CSF
    try:
        deny = [l for l in open("/etc/csf/csf.deny", errors="ignore") if re.match(r"^\d", l)]
    except Exception:  # noqa
        deny = []
    _, lim, _ = sh("grep -E '^DENY_IP_LIMIT' /etc/csf/csf.conf")
    limit = int((re.findall(r"\d+", lim) or [200])[0])
    s["csf_permanent_blocks"], s["csf_limit"] = len(deny), limit
    _, temp, _ = sh("csf -t")
    s["csf_temp_blocks"] = len([l for l in temp.splitlines() if re.search(r"\d+\.\d+\.\d+\.\d+", l)])
    if len(deny) >= limit:
        add("server", "csf", "medium", f"CSF block list is full ({len(deny)}/{limit}); new attackers push old ones out",
            "Raise DENY_IP_LIMIT (e.g. 1000) and consider LF_PERMBLOCK / blocklists in csf.blocklists.")
    _, alert, _ = sh("grep -E '^LF_ALERT_TO' /etc/csf/csf.conf")
    if '""' in alert:
        add("server", "csf", "low", "lfd alerts go nowhere (LF_ALERT_TO empty)", "Set LF_ALERT_TO to your email.")
    # mail queue / bounces
    try:
        mail = since_today(open("/var/log/mail.log", errors="ignore").read().splitlines())
    except Exception:  # noqa
        mail = []
    s["mail_sent_24h"] = len([l for l in mail if "status=sent" in l])
    s["mail_bounced_24h"] = len([l for l in mail if "status=bounced" in l])
    s["mail_deferred_24h"] = len([l for l in mail if "status=deferred" in l])
    s["dkim_errors_24h"] = len([l for l in mail if re.search(r"opendkim.*(error|no signature data|bad signature)", l, re.I)])
    _, q, _ = sh("postqueue -p | tail -1")
    m = re.search(r"in (\d+) Request", q)
    s["mail_queue"] = int(m.group(1)) if m else 0
    if s["mail_bounced_24h"] > 20:
        add("server", "mail", "medium", f"{s['mail_bounced_24h']} bounced emails in 24 h", "Read /var/log/mail.log for status=bounced: bad addresses or a blocklisting.")
    if s["mail_queue"] > 50:
        add("server", "mail", "high", f"{s['mail_queue']} emails stuck in the queue", "postqueue -p; look for spam from a compromised site or a blocked relay.")
    for rbl in ("zen.spamhaus.org", "bl.spamcop.net", "b.barracudacentral.org"):
        rev = ".".join(reversed(SERVER_IP.split(".")))
        hit = [a for a in dig(f"{rev}.{rbl}", "A") if a.startswith("127.0.0.") and a not in ("127.255.255.254", "127.255.255.255")]
        if hit:
            add("server", "blocklist", "high", f"Server IP is listed on {rbl}", "Find what sent spam, stop it, then request delisting.")
    # OS updates
    _, up, _ = sh("apt list --upgradable 2>/dev/null", timeout=60)
    s["updates_security"] = len([l for l in up.splitlines() if "-security" in l])
    s["updates_total"] = len([l for l in up.splitlines() if "/" in l])
    s["reboot_required"] = os.path.exists("/var/run/reboot-required")
    if s["updates_security"]:
        add("server", "updates", "medium", f"{s['updates_security']} security updates pending", "apt-get upgrade (unattended-upgrades can do this nightly).")
    if s["reboot_required"]:
        add("server", "updates", "low", "Reboot required for installed updates", "Reboot in a quiet hour.")
    _, rel, _ = sh("lsb_release -rs")
    if rel.strip().startswith("20.04"):
        add("server", "os", "medium", "Ubuntu 20.04 is out of standard support (since May 2025)",
            "Enable Ubuntu Pro ESM (free for 5 machines) or plan a move to 22.04/24.04.")
    # admin users
    uid0 = [l.split(":")[0] for l in open("/etc/passwd") if l.split(":")[2] == "0"]
    sudo = (sh("getent group sudo")[1].strip().split(":") + [""])[3]
    s["uid0_users"], s["sudo_users"] = uid0, [u for u in sudo.split(",") if u]
    keys = []
    for kf in ["/root/.ssh/authorized_keys"] + glob.glob("/home/*/.ssh/authorized_keys"):
        try:
            for l in open(kf, errors="ignore"):
                if l.strip() and not l.startswith("#"):
                    keys.append(kf + " " + hashlib.sha256(l.strip().split()[1].encode()).hexdigest()[:16] if len(l.split()) > 1 else kf)
        except Exception:  # noqa
            pass
    s["ssh_keys"] = keys
    if len(uid0) > 1:
        add("server", "users", "critical", f"Extra users with root powers: {uid0}", "Remove any uid 0 account that is not root.")
    return s


# ---------------------------------------------------------------- integrity + malware

MALWARE = [
    (r"eval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(", "eval of encoded code"),
    (r"(assert|eval)\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)", "runs code sent by the visitor"),
    (r"\$_(POST|GET|REQUEST|COOKIE)\s*\[[^\]]+\]\s*\(", "calls a function named by the visitor"),
    (r"(shell_exec|passthru|system|proc_open|popen)\s*\(\s*\$_(POST|GET|REQUEST)", "shell command from the visitor"),
    (r"preg_replace\s*\(\s*['\"].*/e['\"]", "preg_replace /e code execution"),
    (r"FilesMan|c99shell|r57shell|WSO \d|b374k|IndoXploit|AnonymousFox", "known web shell name"),
    (r"move_uploaded_file\s*\(\s*\$_FILES\[[^\]]+\]\['tmp_name'\]\s*,\s*\$_(POST|GET)", "uploader taking path from visitor"),
]
SKIP_DIRS = {"node_modules", ".git", "cache", "sessions", "views", "logs", ".cache"}
# Old deploy copies and backups are not served; skipping them keeps the scan (and memory) small on a 2 GB box.
SKIP_COPIES = re.compile(r"^(laravel_prev_.*|releases|versions|old.*|.*backup.*|\.trash)$", re.I)


def webroot_files(sites):
    homes = sorted(set(s["home"] for s in sites if os.path.isdir(s["home"])))
    for home in homes:
        for dirpath, dirnames, files in os.walk(home):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS and not SKIP_COPIES.match(d)]
            for f in files:
                if f.endswith((".php", ".phtml", ".php5", ".php7", ".phar", ".htaccess")) or f == ".htaccess":
                    yield os.path.join(dirpath, f)


def check_files(sites, persist, full=False):
    base_file = os.path.join(STATE_DIR, "baseline.json")
    old = {}
    if os.path.exists(base_file):
        try:
            old = json.load(open(base_file))
            if old and not isinstance(next(iter(old.values())), list):
                old = {}  # older format
        except Exception:  # noqa
            old = {}
    # baseline entry: [size, mtime_ns, sha256]. Unchanged size+mtime = not re-read (unless --full).
    new, hits, read = {}, [], 0
    for f in webroot_files(sites):
        try:
            stt = os.stat(f)
            if stt.st_size > 3_000_000:
                continue
            prev = old.get(f)
            if prev and not full and prev[0] == stt.st_size and prev[1] == stt.st_mtime_ns:
                new[f] = prev
                continue
            data = open(f, "rb").read()
            read += 1
        except Exception:  # noqa
            continue
        h = hashlib.sha256(data).hexdigest()
        new[f] = [stt.st_size, stt.st_mtime_ns, h]
        if prev and prev[2] == h and not full:
            continue  # touched, same content
        text = data.decode("latin-1")
        for rx, what in MALWARE:
            if re.search(rx, text, re.I):
                hits.append({"file": f, "why": what})
                break
        if f.endswith((".php", ".phtml")) and re.search(r"/(uploads|storage/app/public|images|img|media)/", f) and "/vendor/" not in f:
            hits.append({"file": f, "why": "PHP file inside an uploads/images folder"})
    added = [f for f in new if f not in old] if old else []
    changed = [f for f in new if f in old and old[f][2] != new[f][2]] if old else []
    removed = [f for f in old if f not in new] if old else []
    for h in hits[:200]:
        add(h["file"].split("/")[2] if h["file"].startswith("/home/") else "server", "malware", "high",
            f"Suspicious code: {h['why']} in {h['file']}", "Open the file; if you did not write it, take the site offline, restore from backup and rotate passwords.")
    code_changes = [f for f in added + changed if "/vendor/" not in f and "/storage/" not in f]
    if old and code_changes:
        add("server", "integrity", "low" if len(code_changes) < 50 else "medium",
            f"{len(code_changes)} PHP files added or changed since the last run",
            "Expected after a deploy; otherwise look at the list in the report.", code_changes[:50])
    if persist:
        os.makedirs(STATE_DIR, exist_ok=True)
        tmp = base_file + ".tmp"
        json.dump(new, open(tmp, "w"))
        os.replace(tmp, base_file)
    return {"files": len(new), "read": read, "baseline": bool(old), "added": len(added), "changed": len(changed), "removed": len(removed),
            "suspicious": hits[:200], "changed_sample": code_changes[:50]}


# ---------------------------------------------------------------- admin changes

def admin_changes(server, previous):
    """New root/sudo users or SSH keys since the previous report."""
    if not previous or "server_checks" not in previous:
        return []
    p = previous["server_checks"]
    out = []
    for key, what in (("uid0_users", "root-level user"), ("sudo_users", "sudo user"), ("ssh_keys", "SSH key")):
        for item in sorted(set(server.get(key, [])) - set(p.get(key, []))):
            out.append(f"New {what}: {item}")
            add("server", "admins", "critical", f"New {what} since last run: {item}",
                "If this was not you, remove it now, change root's keys and check /var/log/auth.log.")
    return out


# ---------------------------------------------------------------- defenses (opt-in)

def defend(server, cfg):
    """Opt-in actions. Each one is off unless named in --defend."""
    done = []
    if "block-ssh-bruteforce" in cfg:
        for ip, n in server.get("ssh_top_ips", []):
            if n >= 20 and not ip.startswith(("127.", "10.", "192.168.")):
                code, _, _ = sh(["csf", "-td", ip, "86400", f"defense-agent: {n} failed SSH logins"])
                if code == 0:
                    done.append(f"blocked {ip} for 24h ({n} failed SSH logins)")
    return done


# ---------------------------------------------------------------- dashboard copy

def domain_status(report):
    worst = {}
    for f in report["findings"]:
        if SEV[f["severity"]] > SEV[worst.get(f["scope"], "ok")]:
            worst[f["scope"]] = f["severity"]
    return worst


def publish(report, target):
    """latest.json + history.json for the scharge dashboard, owned by the folder's owner."""
    os.makedirs(target, exist_ok=True)
    st = os.stat(target)
    hist_file = os.path.join(target, "history.json")
    try:
        hist = json.load(open(hist_file))
    except Exception:  # noqa
        hist = []
    hist = [h for h in hist if h["at"][:10] != report["generated_at"][:10]]
    hist.append({"at": report["generated_at"], "summary": report["summary"], "status": domain_status(report)})
    for name, data in (("latest.json", report), ("history.json", hist[-120:])):
        tmp = os.path.join(target, name + ".tmp")
        json.dump(data, open(tmp, "w"), default=str)
        os.chown(tmp, st.st_uid, st.st_gid)
        os.chmod(tmp, 0o640)
        os.replace(tmp, os.path.join(target, name))


def publish_sites(report, sites):
    """Each /home/<account>/security/site.json gets only that account's domains and findings (for the security kit
    page inside each app). Owned by the account's user, mode 640."""
    homes = {}
    for s in sites:
        if s["mail_host"] or not s["home"].startswith("/home/"):
            continue
        homes.setdefault(s["home"], set()).add(s["domain"])
    for home, names in homes.items():
        if not os.path.isdir(home):
            continue
        names = set(names) | {apex(n) for n in names}
        account = os.path.basename(home)
        mine = [f for f in report["findings"] if f["scope"] in names or f["scope"] == account
                or str(f["scope"]).startswith(home + "/")]
        sub = {
            "generated_at": report["generated_at"], "server": report["server"],
            "sites": {n: v for n, v in report.get("sites", {}).items() if n in names},
            "domains": {n: v for n, v in report.get("domains", {}).items() if n in names},
            "laravel": [a for a in report.get("laravel", []) if a["path"].startswith(home + "/") and a.get("live")],
            "findings": mine,
            "summary": {k: len([f for f in mine if f["severity"] == k]) for k in ("critical", "high", "medium", "low")},
        }
        try:
            target = os.path.join(home, "security")
            st = os.stat(home)
            if not os.path.isdir(target):
                os.makedirs(target, mode=0o750)
                os.chown(target, st.st_uid, st.st_gid)
            hist_file = os.path.join(target, "site-history.json")
            try:
                hist = json.load(open(hist_file))
            except Exception:  # noqa
                hist = []
            hist = [h for h in hist if h["at"][:10] != sub["generated_at"][:10]]
            hist.append({"at": sub["generated_at"], "summary": sub["summary"]})
            for name, data in (("site.json", sub), ("site-history.json", hist[-120:])):
                tmp = os.path.join(target, name + ".tmp")
                json.dump(data, open(tmp, "w"), default=str)
                os.chown(tmp, st.st_uid, st.st_gid)
                os.chmod(tmp, 0o640)
                os.replace(tmp, os.path.join(target, name))
        except Exception as e:  # noqa
            print("publish failed for", home, e, file=sys.stderr)


# ---------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--stdout", action="store_true", help="print JSON only; write nothing on the server")
    ap.add_argument("--only", default="", help="comma list: tls,dns,http,laravel,server,files")
    ap.add_argument("--audit", action="store_true", help="run composer audit (slow; weekly)")
    ap.add_argument("--full", action="store_true", help="re-read every file, not just changed ones (weekly)")
    ap.add_argument("--publish", default=os.environ.get("DEFENSE_PUBLISH", ""), help="also copy latest.json + history.json here (dashboard)")
    ap.add_argument("--publish-sites", action="store_true", help="write /home/<account>/security/site.json for the security kit")
    ap.add_argument("--defend", default="", help="comma list of opt-in defenses, e.g. block-ssh-bruteforce")
    ap.add_argument("--digest", choices=["daily", "weekly"], help="also email the digest")
    ap.add_argument("--mail-to", default=os.environ.get("DEFENSE_MAIL_TO", ""))
    a = ap.parse_args()
    only = set(filter(None, a.only.split(",")))
    want = lambda k: not only or k in only  # noqa
    t0 = time.time()

    sites = inventory()
    web = [s for s in sites if not s["mail_host"]]
    mail_domains = sorted(set(os.listdir(DKIM_KEYS)) if os.path.isdir(DKIM_KEYS) else set())
    report = {"generated_at": NOW.isoformat(), "server": SERVER_IP, "agent": "1.0", "sites": {}, "domains": {}}

    with cf.ThreadPoolExecutor(max_workers=12) as pool:
        if want("tls"):
            for s, r in zip(sites, pool.map(lambda s: check_tls(s, SERVER_IP), sites)):
                report["sites"].setdefault(s["domain"], {"docroot": s["docroot"], "mail_host": s["mail_host"]})["tls"] = r
        if want("http"):
            with cf.ThreadPoolExecutor(max_workers=3) as hpool:  # PHP workers are few; do not overload the box
                http_results = list(hpool.map(check_http, web))
            for s, r in zip(web, http_results):
                report["sites"].setdefault(s["domain"], {"docroot": s["docroot"], "mail_host": False})["http"] = r
        if want("dns"):
            names = sorted(set(apex(s["domain"]) for s in sites) | set(mail_domains) | set(s["domain"] for s in sites))
            for n, r in zip(names, pool.map(lambda n: check_dns(n, n in mail_domains), names)):
                report["domains"][n] = r
    if want("laravel"):
        apps = laravel_apps(sites)
        with cf.ThreadPoolExecutor(max_workers=1 if a.audit else 3) as pool:  # composer audit is memory-hungry
            report["laravel"] = list(pool.map(lambda app: check_laravel(app, a.audit), apps))
    if want("server"):
        report["server_checks"] = check_server()
    if want("files"):
        report["files"] = check_files(sites, persist=not a.stdout, full=a.full)
    previous = None
    try:
        previous = json.load(open(f"{STATE_DIR}/reports/latest.json"))
    except Exception:  # noqa
        pass
    if "server_checks" in report:
        report["admin_changes"] = admin_changes(report["server_checks"], previous)
    if previous and "laravel" not in report and "laravel" in previous:
        report["laravel"] = previous["laravel"]
    # composer advisories only refresh weekly: carry last week's findings forward on daily runs
    if not a.audit and previous and want("laravel"):
        carried = [f for f in previous.get("findings", []) if f["check"] == "composer"]
        for f in carried:
            f = dict(f, carried=True)
            findings.append(f)
    if a.defend and not a.stdout:
        report["defenses"] = defend(report.get("server_checks", {}), set(a.defend.split(",")))

    # Names that do not resolve: one finding for the name, everything else about it is demoted (it is not
    # reachable by visitors) except files that leak secrets, which anyone can fetch via the server IP.
    dead = {n for n, d in report["domains"].items() if not d.get("a")}
    for f in findings:
        if f["scope"] in dead and f["check"] not in ("dns", "exposed", "laravel", "malware"):
            f["severity"] = "low"
            f["title"] += " (name does not resolve)"
    findings.sort(key=lambda f: (-SEV[f["severity"]], f["scope"], f["check"]))
    report["findings"] = findings
    report["summary"] = {k: len([f for f in findings if f["severity"] == k]) for k in ("critical", "high", "medium", "low")}
    report["took_seconds"] = round(time.time() - t0, 1)

    if a.stdout:
        json.dump(report, sys.stdout, indent=1, default=str)
        return
    os.makedirs(f"{STATE_DIR}/reports", exist_ok=True)
    path = f"{STATE_DIR}/reports/{NOW.strftime('%Y-%m-%dT%H%M')}.json"
    json.dump(report, open(path, "w"), indent=1, default=str)
    os.replace(path, path)  # atomic enough for a single writer
    latest = f"{STATE_DIR}/reports/latest.json"
    if os.path.lexists(latest):
        os.remove(latest)
    os.symlink(os.path.basename(path), latest)
    for old in sorted(glob.glob(f"{STATE_DIR}/reports/20*.json"))[:-90]:  # keep 90 runs
        os.remove(old)
    print(path, report["summary"])
    if a.publish:
        publish(report, a.publish)
    if a.publish_sites:
        publish_sites(report, sites)
    here = os.path.dirname(os.path.abspath(__file__))
    if report.get("admin_changes") and a.mail_to and "alert-admins" in a.defend:
        sh([sys.executable, os.path.join(here, "digest.py"), "--alert", "--to", a.mail_to, "--send"], timeout=120)
    if a.digest and a.mail_to:
        sh([sys.executable, os.path.join(here, "digest.py"), "--period", a.digest, "--to", a.mail_to, "--send"], timeout=120)


if __name__ == "__main__":
    main()
