# resources/data

Reference lists that rarely change and are read straight from these text files (no database, no migration).
Edit a file and the change shows on the next request (the loader caches by file modification time).

| File | Used for | Format |
|---|---|---|
| `banks-kenya.txt` | Bank dropdowns: payment channels, Integrations > Bank accounts, supplier and employee payouts, checkout "paying from" | `Name \| code` |
| `mobile-money.txt` | Mobile money options at checkout and for payouts; the M-Pesa provider name on Integrations > M-Pesa | `Name \| country \| methods` |
| `cities-kenya.txt` | Addresses, CRM territories (tier 1 = Nairobi, Mombasa, Kisumu, Nakuru, Eldoret) | `Name \| county \| tier` |
| `currencies.txt` | Invoice currencies | `Code \| name \| symbol` |
| `countries-africa.txt` | Sign-up and onboarding country list | `Name \| ISO \| currency` |
| `medical-insurers-kenya.txt` | Insurer names (kept from Breve; not used by scharge yet) | one name per line |

Read them in code with `App\Support\DataList`:

```php
DataList::get('banks-kenya');            // [['name' => 'ABSA Bank Kenya PLC', 'code' => null], ...]
DataList::names('banks-kenya');          // ['ABSA Bank Kenya PLC', ...]
DataList::get('cities-kenya', ['name', 'county', 'tier']);
```

Rules: `#` starts a comment, blank lines are skipped, columns are split on `|` and trimmed, empty columns become `null`.
