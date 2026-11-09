# Email: sending as noreply@sharet.africa

scharge (and the other sharet.africa projects on this server) send through the server's own mail server
(Postfix on 185.113.249.146), which already DKIM-signs mail from @sharet.africa with the selector `default`.
Gmail only accepts it once DNS says this server may send for sharet.africa.

## DNS records to add at the sharet.africa DNS host

1. **DKIM** (new record)
   - Type: `TXT`
   - Name / host: `default._domainkey` (full name `default._domainkey.sharet.africa`)
   - Value (one line):

     ```
v=DKIM1; h=sha256; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy2VSxtgIKIvA7fSiYNLUNOaltJIHr5dzfuqnUsrGG5NL60Pl1VfxZnlTncPNdFHWJnv97/YdyN2mrFyLltz/fzsuaraErZ3e2KyZKtki9kMP6G93/Jl7US7UU9+4Tp8uigzwnPF0NxVPariYTIhGNlx9+HsElZ0Vu9r0ZyrGpIo+t+nZvsmxAjcf+rNJpQU32cmEGWCjXVKn3R1pw2/oykdNwCZnUqwg9Q/MtWFyKsTm5rcjkrg5HTklTHJDOL3UKP5K5Gk2/YaJBGKcDDuGyR6c9p/2RZWXxniDViDz+dcS5zXO4P8jIx9x7AT/NFuX0CyrO8FOJhzCSUkiLHtKXQIDAQAB
     ```

2. **SPF** (edit the existing TXT record on `sharet.africa`; keep only one SPF record)
   - Now: `v=spf1 include:_spf.cloudoon.com ~all`
   - Change to: `v=spf1 ip4:185.113.249.146 include:_spf.cloudoon.com ~all`

DMARC (`_dmarc.sharet.africa`, p=quarantine) stays as it is. Reverse DNS of the server already resolves back to it.

## Then on the server (scharge .env)

```
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=noreply@sharet.africa
MAIL_FROM_NAME=scharge
```

Check: `dig +short TXT default._domainkey.sharet.africa` and `dig +short TXT sharet.africa` show the new values,
then send a test and look for `status=sent (250 ... gsmtp)` without "DMARC:Quarantine" in /var/log/mail.log.
When scharge moves to its own domain, repeat with that domain's DKIM key and SPF.
