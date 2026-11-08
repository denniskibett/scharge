# Meter readings API: water and electricity, manual and smart

*Added 2026-11-09 on `claude/crm`.*

Base URL: `https://scharge.sharet.africa/api/v1`. Bodies are JSON (photos can also be sent as multipart). Every call is scoped to the key's company.

**Schema**, hand-applied after the properties rename:

```
mysql scha_main < database/schema/2026_11_09_meters.sql
```

It adds two tables, `meters` and `meter_readings`.

## Who calls it

| Caller | Auth | Gets |
|---|---|---|
| **Meter reader app** (phone) | `POST /auth/token` with email, password and device_name. The account must have a company and be a meter_reader, property_manager, admin, maintenance or security user. | A personal key `sk_live_…` with scopes `read` and `meters`. Sign out with `DELETE /auth/token`. |
| **Smart meter** (reports itself) | `Authorization: Bearer mtr_…`, the device token from `POST /meters/{id}/device-token` (shown once) | Can push its own readings only |
| **Vendor or IoT gateway**, back office | A company API key with the `meters` scope (Settings › API keys) | All of the company's meters, through the batch endpoint |

## Endpoints

| Method | Path | Scope | What |
|---|---|---|---|
| POST | `/auth/token` | none | Reader sign-in; 5 a minute |
| DELETE | `/auth/token` | key | Sign out |
| GET | `/meters` | read | List. Filters: `utility`, `kind`, `property_id`, `unit_id`, `due=1` (manual meters not read this month: the reader's route) |
| POST | `/meters` | meters | Register a meter: `unit_id` (or `property_id` for a common-area meter), `utility` (water or electricity), `kind` (manual or smart), `serial_number`, plus optional `billing` (postpaid or prepaid), `make`, `model`, `multiplier`, `rate_per_unit`, `initial_reading` |
| GET | `/meters/{id}` | read | One meter with its last reading |
| GET | `/meters/{id}/readings` | read | History; `limit` up to 500 |
| POST | `/meters/{id}/readings` | meters | `reading`, plus optional `read_at`, `client_ref`, `note`, and `photo` (multipart) or `photo_base64` |
| POST | `/readings/batch` | meters | Up to 500 items, each with `{meter_id \| serial_number + utility, reading, read_at, client_ref}`. Each item is reported separately. |
| POST | `/meters/{id}/device-token` | meters | Issue a smart-meter token. A new token disables the old one. |
| POST | `/smart/readings` | device token | `{reading, read_at?, client_ref?}` or `{readings:[…]}` (buffered); 240 a minute |

## Rules

- **Offline-safe.** Send a `client_ref` for each reading, such as the app's local id or the device's message id. Re-sending the same one returns the original reading (`200` with `Idempotent-Replay: true`).
- **Consumption** is measured against the latest earlier reading, so out-of-order syncs work, and is multiplied by the meter's `multiplier`. The charge is consumption × rate:
  - **Water:** the meter's own rate, else the unit's custom rate, else the property's water rate.
  - **Electricity:** the meter's `rate_per_unit`.
- **Flags.** A flagged reading is stored but not billed, and does not move the meter's last reading.
  - `lower_than_previous`: the reading went backwards.
  - `unusual_consumption`: daily use is more than 3× the meter's 90-day average.
- **Water billing:**
  - A water reading taken by a person (reader app or API) becomes that month's `water_readings` row, with the same rules as the web form. There is one per unit per month; later readings in the same month are kept but not billed again.
  - Smart water meters report continuously. `meters:post-water` runs on the 1st at 06:00 Nairobi and bills each one's last reading of the previous month.
- **Electricity** readings record consumption and charge. They are not added to invoices yet; that is the next step, together with prepaid token handling.

## Example

```bash
# reader signs in
curl -X POST $BASE/auth/token -H 'Content-Type: application/json' \
  -d '{"email":"otieno@example.com","password":"…","device_name":"Otieno Pixel"}'
# → {"token":"sk_live_…","scopes":["read","meters"]}

# today's route
curl "$BASE/meters?due=1&utility=water" -H "Authorization: Bearer sk_live_…"

# record with a photo
curl -X POST $BASE/meters/12/readings -H "Authorization: Bearer sk_live_…" \
  -F reading=412.0 -F client_ref=otieno-0042 -F photo=@meter.jpg
# → 201 {"consumption":12.3,"charge":1845,"status":"accepted","water_reading_id":3301,…}

# smart meter
curl -X POST $BASE/smart/readings -H "Authorization: Bearer mtr_…" \
  -H 'Content-Type: application/json' -d '{"reading":1421.6,"client_ref":"msg-88231"}'
```

## Verified (2026-11-09, scratch copy of live data with all three SQL files)

25 of 25 checks passed:

- **Sign-in:** a reader signs in; a wrong password gets 401; a tenant is refused.
- **Registering meters:** a duplicate serial or another company's unit gets 422.
- **Water:** a reading creates the monthly billing row and updates the unit's cached readings. A replayed `client_ref` creates no duplicate. A lower reading is flagged and not billed. Photos are stored.
- **Electricity:** the charge is correct.
- **Batch:** it reports an unknown meter. A spike is flagged and does not move the meter's last reading.
- **Smart meters:** single and buffered pushes work, a repeated message id is ignored, and a bad token gets 401. Smart water readings are not billed one by one, and the month-end post bills the last reading.
- **Scopes:** a key without the `meters` scope gets 403.
- **Listing and sign-out:** listing works; sign-out revokes the key.
