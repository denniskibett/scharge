#!/usr/bin/env python3
"""
Email digest for the defense agent. Reads /var/lib/defense-agent/reports/*.json, compares with an earlier
report (previous run for daily, ~7 days back for weekly) and sends an HTML email via the local Postfix
(sendmail) from noreply@sharet.africa, which OpenDKIM signs.

  python3 digest.py --period daily  --to you@example.com           # print the email (no send)
  python3 digest.py --period weekly --to you@example.com --send
  python3 digest.py --alert --to you@example.com --send             # admin-change alert only
  python3 digest.py --report r.json --out mail.html                 # render a given report to a file
"""
import argparse, datetime as dt, glob, html, json, os, subprocess, sys
from email.message import EmailMessage
from email.utils import formatdate, make_msgid

STATE_DIR = os.environ.get("DEFENSE_STATE", "/var/lib/defense-agent")
FROM = os.environ.get("DEFENSE_MAIL_FROM", "Sharet Security <noreply@sharet.africa>")
DASHBOARD = os.environ.get("DEFENSE_DASHBOARD", "https://scharge.sharet.africa/admin/security")
SEV = {"critical": 4, "high": 3, "medium": 2, "low": 1, "ok": 0}
COLOR = {"critical": "#B3261E", "high": "#D9480F", "medium": "#B58100", "low": "#5F6B7A", "ok": "#2B8A3E"}
INK, RED, BLUSH = "#1A1214", "#E0362C", "#F4F1F1"


def reports():
    return sorted(f for f in glob.glob(f"{STATE_DIR}/reports/20*.json"))


def load(path):
    return json.load(open(path)) if path and os.path.exists(path) else None


def key(f):
    return (f["scope"], f["check"], f["title"].split(" (")[0])


def esc(s):
    return html.escape(str(s or ""))


def pill(sev):
    return (f'<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:{COLOR[sev]};color:#fff;'
            f'font:600 11px/16px Arial,sans-serif;text-transform:uppercase">{sev}</span>')


def worst_by_scope(report):
    w = {}
    for f in report["findings"]:
        if SEV[f["severity"]] > SEV[w.get(f["scope"], "ok")]:
            w[f["scope"]] = f["severity"]
    return w


def render(report, before, period, alert=False):
    s, b = report["summary"], (before or {}).get("summary", {})
    now_keys = {key(f) for f in report["findings"] if SEV[f["severity"]] >= 2}
    old_keys = {key(f) for f in (before or {}).get("findings", []) if SEV[f["severity"]] >= 2} if before else set()
    new = [f for f in report["findings"] if key(f) in now_keys - old_keys] if before else []
    fixed = sorted(old_keys - now_keys)
    top = [f for f in report["findings"] if SEV[f["severity"]] >= 3]
    when = report["generated_at"][:16].replace("T", " ")

    def delta(k):
        if not before:
            return ""
        d = s.get(k, 0) - b.get(k, 0)
        return "" if d == 0 else f'<div style="font:12px Arial;color:{"#B3261E" if d > 0 else "#2B8A3E"}">{"+" if d > 0 else ""}{d}</div>'

    tiles = "".join(
        f'<td style="padding:12px;text-align:center;border:1px solid #eee"><div style="font:700 26px Arial;color:{COLOR[k]}">{s.get(k, 0)}</div>'
        f'<div style="font:12px Arial;color:#555;text-transform:capitalize">{k}</div>{delta(k)}</td>'
        for k in ("critical", "high", "medium", "low"))

    def rows(items, limit=40):
        out = ""
        for f in items[:limit]:
            out += (f'<tr><td style="padding:8px 6px;border-bottom:1px solid #eee;vertical-align:top">{pill(f["severity"])}</td>'
                    f'<td style="padding:8px 6px;border-bottom:1px solid #eee;font:13px/18px Arial;color:{INK}">'
                    f'<b>{esc(f["scope"])}</b><br>{esc(f["title"])}'
                    + (f'<div style="color:#666;font-size:12px;margin-top:3px">Fix: {esc(f["fix"])}</div>' if f.get("fix") else "")
                    + "</td></tr>")
        if len(items) > limit:
            out += f'<tr><td colspan=2 style="padding:8px 6px;font:12px Arial;color:#666">…and {len(items) - limit} more on the dashboard.</td></tr>'
        return out

    w = worst_by_scope(report)
    sites = sorted(set(list(report.get("sites", {})) + [d for d in report.get("domains", {})]))
    site_rows = "".join(
        f'<tr><td style="padding:4px 6px;font:12px Arial;color:{INK}">{esc(d)}</td><td style="padding:4px 6px">{pill(w.get(d, "ok"))}</td></tr>'
        for d in sorted(sites, key=lambda d: (-SEV[w.get(d, "ok")], d)))

    if alert:
        title, intro = "New admin access on the server", "The agent saw changes to who can administer the server:"
        body = "<ul>" + "".join(f'<li style="font:14px Arial;margin:4px 0">{esc(c)}</li>' for c in report.get("admin_changes", [])) + "</ul>"
        body += '<p style="font:13px Arial;color:#555">If this was you (a new deploy key, say), nothing to do. If not: remove it, rotate root keys, read /var/log/auth.log.</p>'
    else:
        title = f"{'Weekly' if period == 'weekly' else 'Daily'} security report"
        intro = f"{len(sites)} domains and sites on {esc(report['server'])}, checked {when} UTC."
        body = f'<table width="100%" cellspacing=0 style="border-collapse:collapse;margin:8px 0 18px">{tiles}</table>'
        if before:
            body += (f'<p style="font:13px Arial;color:{INK}"><b>{len(new)}</b> new problems and <b>{len(fixed)}</b> fixed since '
                     f'{esc(before["generated_at"][:10])}.</p>')
            if new:
                body += f'<h3 style="font:700 15px Arial;color:{INK};margin:18px 0 6px">New since last report</h3><table width="100%" cellspacing=0>{rows(new, 20)}</table>'
            if fixed:
                body += (f'<h3 style="font:700 15px Arial;color:#2B8A3E;margin:18px 0 6px">Fixed</h3><ul style="font:13px Arial;color:{INK};margin:0;padding-left:18px">'
                         + "".join(f"<li>{esc(sc)}: {esc(t)}</li>" for sc, _, t in fixed[:20]) + "</ul>")
        body += f'<h3 style="font:700 15px Arial;color:{INK};margin:18px 0 6px">Fix first (critical and high)</h3><table width="100%" cellspacing=0>{rows(top)}</table>'
        sc = report.get("server_checks", {})
        if sc:
            body += (f'<h3 style="font:700 15px Arial;color:{INK};margin:18px 0 6px">Server</h3><p style="font:13px/20px Arial;color:{INK}">'
                     f'Disk {sc.get("disk_used_pct")}% · {sc.get("ssh_failed_24h")} failed SSH logins (24 h) · '
                     f'{sc.get("csf_permanent_blocks")}/{sc.get("csf_limit")} IPs blocked · mail: {sc.get("mail_sent_24h")} sent, '
                     f'{sc.get("mail_bounced_24h")} bounced · {sc.get("updates_security")} security updates pending</p>')
        if report.get("defenses"):
            body += '<p style="font:13px Arial;color:#555">Automatic actions: ' + esc("; ".join(report["defenses"])) + "</p>"
        if period == "weekly":
            body += f'<h3 style="font:700 15px Arial;color:{INK};margin:18px 0 6px">Every domain</h3><table cellspacing=0>{site_rows}</table>'

    page = f"""<!doctype html><html><body style="margin:0;background:{BLUSH}">
<table width="100%" cellspacing=0 style="background:{BLUSH}"><tr><td align="center" style="padding:24px 12px">
<table width="640" cellspacing=0 style="max-width:640px;width:100%;background:#fff">
<tr><td style="background:{INK};padding:22px 28px">
  <div style="font:700 13px Arial;color:#fff;letter-spacing:.08em">SHARET · SECURITY</div>
  <div style="font:700 22px Arial;color:#fff;margin-top:8px">{esc(title)}</div>
  <div style="font:13px Arial;color:#c9bfc1;margin-top:4px">{intro}</div></td></tr>
<tr><td style="padding:22px 28px">{body}
  <p style="margin:26px 0 6px"><a href="{DASHBOARD}" style="display:block;text-align:center;background:{RED};color:#fff;text-decoration:none;font:700 14px Arial;padding:13px">Open the security dashboard</a></p></td></tr>
<tr><td style="background:{INK};padding:14px 28px;font:11px Arial;color:#c9bfc1">Sent by the defense agent on {esc(report['server'])}. Reports: /var/lib/defense-agent/reports. Docs: ops/defense-agent/README.md in the scharge repo.</td></tr>
</table></td></tr></table></body></html>"""
    subject = (f"[Security] {title}: {s.get('critical', 0)} critical, {s.get('high', 0)} high"
               if not alert else "[Security] ALERT: new admin access on the server")
    text = f"{title}\n{intro}\n\n" + "\n".join(f"[{f['severity']}] {f['scope']}: {f['title']}" for f in top[:40]) + f"\n\nDashboard: {DASHBOARD}\n"
    return subject, page, text


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--period", choices=["daily", "weekly"], default="daily")
    ap.add_argument("--alert", action="store_true")
    ap.add_argument("--to", default=os.environ.get("DEFENSE_MAIL_TO", ""))
    ap.add_argument("--send", action="store_true")
    ap.add_argument("--report", help="render this report file instead of the latest")
    ap.add_argument("--out", help="write the HTML here")
    a = ap.parse_args()

    files = reports()
    report = load(a.report) if a.report else load(files[-1] if files else None)
    if not report:
        sys.exit("no report yet")
    before = None
    if not a.report and len(files) > 1:
        if a.period == "weekly":
            cutoff = (dt.datetime.fromisoformat(report["generated_at"]) - dt.timedelta(days=6, hours=12)).isoformat()
            older = [f for f in files[:-1] if (load(f) or {}).get("generated_at", "") <= cutoff]
            before = load(older[-1]) if older else load(files[0])
        else:
            before = load(files[-2])
    subject, page, text = render(report, before, a.period, a.alert)
    if a.out:
        open(a.out, "w").write(page)
    if not a.send:
        if not a.out:
            print(subject)
            print(text)
        return
    if not a.to:
        sys.exit("--to is required to send")
    msg = EmailMessage()
    msg["From"], msg["To"], msg["Subject"] = FROM, a.to, subject
    msg["Date"], msg["Message-ID"] = formatdate(localtime=True), make_msgid(domain="sharet.africa")
    msg.set_content(text)
    msg.add_alternative(page, subtype="html")
    r = subprocess.run(["/usr/sbin/sendmail", "-t", "-oi", "-f", "noreply@sharet.africa"], input=msg.as_bytes(), capture_output=True)
    if r.returncode:
        sys.exit("sendmail failed: " + r.stderr.decode(errors="ignore"))
    print("sent", subject, "to", a.to)


if __name__ == "__main__":
    main()
