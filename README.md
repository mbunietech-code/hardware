# Hardware Business Management System

Built from the 22 project documents in [`document/`](document/).

| Part | Folder | Technology |
|---|---|---|
| Web admin + REST API (`/api/v1`) | [`backend/`](backend/) | Laravel 13, PHP 8.4, MySQL / MariaDB, Blade + Tailwind + Alpine |
| Mobile app (offline-first) | [`mobile/`](mobile/) | Flutter 3.47, SQLite (sqflite), sync to MySQL through the API |

---

## Kwa ufupi (Swahili)

- **Web (Laravel)**: Super Admin anasimamia maduka, watumiaji, bidhaa, ripoti, mipangilio, audit trail na migogoro ya sync. Shop Admin anaweza pia kutumia web kwa duka lake tu.
- **App (Flutter)**: Shop Admin anafungua siku, anauza, ananunua, anarekodi matumizi, madeni, marekebisho ya stock, kisha anafunga siku. **Yote haya yanafanya kazi bila internet.** Kila rekodi inahifadhiwa kwanza kwenye SQLite ya simu.
- **Sync**: Internet ikirudi, app inatuma rekodi kwenda MySQL yenyewe, kila baada ya dakika 1, au ukibonyeza "Sync now". Kila rekodi ina `local_uuid`, kwa hiyo hata ikitumwa mara mbili haihesabiwi mara mbili.
- Rekodi ikigongana na hali ya server (mfano stock haitoshi, au siku imeshafungwa), **haifutwi wala haiandikwi juu**. Inasubiri Super Admin aamue kwenye **Sync monitor** (Accept / Reject).
- **Lugha (EN / SW)**: kwenye web kuna kitufe **EN | SW** juu ya kila ukurasa na kwenye ukurasa wa login. Kwenye app kiko kwenye Login, kwenye skrini ya Nyumbani (aikoni ya tafsiri) na kwenye **More → Lugha**. Lugha uliyochagua inakumbukwa.
- Mambo ambayo documents zinasema ni "Pending Decision" (formula ya faida, njia ya gharama ya stock, maana ya 60/40, n.k.) **hayajawekwa kwa nguvu kwenye code**. Yako kwenye **Settings**, na mwenye biashara anaweza kuyabadilisha wakati wowote.

### Kuanza (development)

```bash
./scripts/start-dev.sh
```

Fungua <http://localhost:8765> kisha ingia kwa:

| Role | Login | Password |
|---|---|---|
| Super Admin | `admin@hardware.test` (au `0700000000`) | `password` |
| Shop Admin (Main Shop) | `shop@hardware.test` (au `0711111111`) | `password` |
| Shop Admin (Branch 2) | `branch@hardware.test` | `password` |

**Badilisha password hizi kabla ya kutumia kwa kweli.**

---

## 1. What was built

### Web admin (Laravel)
- **Dashboard**: today's sales, purchases and expenses, receivables/payables, a 14-day sales chart, month profit (labelled provisional until approved), shop day status, low stock, alerts and recent sales.
- **Daily sessions**: open the day (with opening cash) and close it. Closing computes totals from the server data, plus expected cash vs counted cash, exceptions and notes. A Super Admin can reopen a closed day; the reopen is audited.
- **Sales**: line-item form with live totals, discounts, payment method, partial or credit payment (the unpaid balance becomes a customer debt automatically), a printable receipt, and void with reason (stock is returned and the action is audited).
- **Purchases**: increase stock, update weighted-average cost, and turn unpaid balances into supplier debts. Can be voided.
- **Expenses**, **Capital** (injection/withdrawal, kept separate from profit), and **Debts** (receivable/payable, due dates, repayments, overdue flags).
- **Products, categories, customers, suppliers**. **Stock** balances per shop, an append-only movement history, and adjustments (add/remove, or set a counted quantity) that keep before/after quantities.
- **Reports** (filters, print/PDF, CSV export): sales, purchases, stock, stock movements, expenses, debts, capital, daily closing, profit (by product/category/shop/day), 60/40 allocations, audit.
- **60/40 allocation**: preview a period, create a draft, then approve. The formula settings used are stored with each allocation, and overlapping approved periods are blocked.
- **Administration**: shops, users (role, shop, extra permissions), expense categories (choose which ones reduce profit), settings, devices (revoke a lost phone), and the sync monitor for conflict resolution.
- **Security**: hashed passwords, login rate limit, deactivated users blocked immediately, Shop Admin restricted to their own shop in every query and service, and an immutable audit log (model refuses update/delete) recording user, shop, source (web/api/sync), device and IP.

### Mobile app (Flutter)
- Login (needs internet the first time). The token is kept in secure storage.
- Home: day status, today's totals, quick actions, alerts, low stock, and today's activity, each with its sync status.
- **Sell**: product search, a cart with quantity/price/discount edits, then checkout with cash, mobile money, bank or credit, and an existing or new customer.
- **Purchase**, **Expense**, **Debts** (new debt, record payment), **Stock** (with adjustments), **Capital** (if permitted), **Open/Close day**.
- **Sync status** screen: Waiting / Problems / Synced, with error reasons, the payload, and a "Sync now" button.
- An online/offline badge with the pending count shows on every screen.
- Super Admin can switch shop.

## 2. How offline sync works (Doc 09)

1. Every action is written in **one SQLite transaction** to `sync_queue`, with a `local_uuid` generated before the first save. Local stock changes go to `stock_deltas`, so the app shows correct stock offline.
2. The app pushes the queue in order (`POST /api/v1/sync/push`) when connectivity returns, every minute, after each save, or on manual sync.
3. The server keeps a **receipt per (local_uuid, entity)**. A retry returns the original acknowledgement (`duplicate: true`) and never creates a second record.
4. Result per item:
   - `accepted`: the record gets a server id and reference; local stock deltas are cleared.
   - `rejected`: a validation error; the local stock effect is undone and the reason is shown.
   - `conflict`: e.g. insufficient server stock, or the day is already closed. It is held for Super Admin review and is **never silently overwritten**.
   - `retry`: a temporary server error; the record stays queued.
5. `GET /api/v1/sync/pull?since=…` refreshes the catalogue, stock, debts, sessions, settings and alerts.
6. Network failure or logout **never deletes local data**. Unsynced records stay attributed to their user.

## 3. Pending decisions → Settings (web: *Administration → Settings*)

| Open decision | Setting | Default |
|---|---|---|
| OD-001 Profit formula | `profit_formula` (gross / net) | net = sales − COGS − profit-reducing expenses |
| OD-002 Stock costing | `costing_method` (weighted average / latest / product cost) | weighted average |
| OD-003 60/40 labels | `allocation_primary_label`, `allocation_secondary_label`, `allocation_primary_percent` | "60% Allocation" / "40% Allocation", 60 |
| OD-004 Allocation timing | `allocation_timing` (stored with each allocation) | monthly |
| OD-005 Expenses reducing profit | per expense category "Reduces profit" | all on except "Owner drawings" |
| OD-008 Negative stock | `negative_stock_policy` (block / allow) | block |
| OD-009 Sync conflicts | held for Super Admin review | manual |
| OD-010 Tax/VAT | `tax_enabled` (flag only, not calculated) | off |
| Selling below cost | `sell_below_cost_policy` (allow / warn / block) | warn |

Until **"Owner has approved the profit rules"** is ticked, profit screens show a **PROVISIONAL** notice.

Still open, not implemented: returns/refunds workflow (OD-007; voiding with reason is available) and receipt printer hardware (OD-011; a printable web receipt exists).

## 3b. Languages (English / Swahili, OD-012)
- **Web**: English text is the key and the translations live in `backend/lang/sw.json` and `backend/lang/sw/validation.php`. The `EN | SW` switch sets a `locale` cookie, and `SetLocale` middleware applies it, including Swahili dates. The API follows the `Accept-Language` header, so mobile users get server errors in their language. Notifications are stored as templates and translated when shown.
- **Mobile**: `tr('English text')` from `mobile/lib/l10n/l10n.dart`, with translations in `mobile/lib/l10n/sw.dart`. The choice is saved in SQLite.
- To add a new string: wrap it in `__('...')` (web) or `tr('...')` (mobile) and add the Swahili line to the matching file. A missing translation simply shows the English text.


## 3c. Added later
- **Product import** (Products → Import): upload Excel/CSV, download the template. Rows are matched by `code` (existing products are updated). The `stock` column sets the counted stock for a chosen shop.
- **Report export**: every report downloads as **Excel (.xlsx)**, **PDF** or CSV.
- **Customer returns** (open a sale → "Rudisha bidhaa / Return items"): partial returns per item, back to stock or marked damaged, with the refund taken off the customer's credit first. Returns lower revenue and profit, and appear in "Hesabu ya leo" and in the **Returns** report. Shop Admins need the "Process returns" permission.
- **Backups**: `php artisan bms:backup` runs every night at 23:30 (keeps 14). Download them from **Administration → Backups**. Restore with:
  ```bash
  zcat hardware_bms_YYYY-MM-DD_HHMMSS.sql.gz | mysql -u hardware -p hardware_bms
  ```
- **Forgot password** on the login page sends a reset link by email. Emails are only written to `storage/logs/laravel.log` until you set real SMTP in `backend/.env`, for example with Gmail and an app password:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.gmail.com
  MAIL_PORT=587
  MAIL_USERNAME=you@gmail.com
  MAIL_PASSWORD=your-16-char-app-password
  MAIL_FROM_ADDRESS=you@gmail.com
  ```

## 4. Running and testing

```bash
# Backend tests (37 tests: business rules, sync, permissions, every web page)
cd backend && php artisan test
# Same tests against MySQL:
DB_CONNECTION=mysql DB_DATABASE=hardware_bms_test DB_USERNAME=hardware DB_PASSWORD=hardware_secret php artisan test

# Mobile tests (14 tests incl. an end-to-end SQLite → API → MySQL sync test when the server runs on :8765)
cd mobile && flutter test

# Optional demo data (2 weeks of activity in both shops)
cd backend && php artisan db:seed --class=DemoDataSeeder
# Reset everything to a clean seeded database
cd backend && php artisan migrate:fresh --seed
```

Tools were installed **without sudo** in `~/tools`: PHP 8.4 (`~/tools/bin/php`), Composer, MariaDB 11.4 (data in `~/tools/mysql-data`, config `~/tools/my.cnf`) and Flutter (`~/tools/flutter`). Add them to your PATH:

```bash
export PATH="$HOME/tools/bin:$HOME/tools/flutter/bin:$PATH"
```

Database: `hardware_bms`, user `hardware` / `hardware_secret` (see `backend/.env`).

### Building the Android app
The Android SDK is not installed on this machine yet. Installing it requires accepting Google's SDK licence, which only you should do. After installing Android Studio (or the command-line tools + JDK 17):

```bash
cd mobile
flutter doctor --android-licenses
flutter build apk --release
```

The APK will be in `mobile/build/app/outputs/flutter-apk/app-release.apk`. On the login screen, tap **Server** and enter your PC's address, e.g. `http://192.168.1.10:8765` (the Android emulator uses `http://10.0.2.2:8765`).

## 5. Going to production (Doc 20)
1. Use a real MySQL 8 / MariaDB server and set `DB_*`, `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL=https://…` in `backend/.env`.
2. Serve `backend/public` via Nginx/Apache with PHP-FPM, **HTTPS only**.
3. Run `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, then `php artisan config:cache route:cache view:cache`.
4. Cron: `* * * * * php /path/backend/artisan schedule:run` (debt and closing reminders).
5. Schedule automated MySQL backups and test restoring them.
6. Change the seeded passwords, or create real users and deactivate the demo ones.
7. Build the APK with your HTTPS server address as the default (`defaultServerUrl` in `mobile/lib/state/app_state.dart`).

## 6. API summary (`/api/v1`, Bearer token from `auth/login`, header `X-Device-Id`)
`auth/login|logout|refresh|me` · `dashboard` · `shops` · `users` (admin) · `settings` (admin) · `categories` · `expense-categories` · `products` · `customers` · `suppliers` · `sales` (+`/{id}/void`) · `purchases` (+void) · `expenses` (+void) · `capital` (+void) · `debts` (+`/{id}/payments`) · `stock` · `stock-adjustments` · `stock-movements` · `daily-sessions` (`current`, `open`, `/{id}/close`) · `sync/push|pull|status` · `reports/{type}` (`?format=csv`) · `notifications` · `audit-logs`.

Errors are JSON: `422` validation (`errors`), `422` business rule (`code`, e.g. `no_open_day`), `409` conflict (`insufficient_stock`, `day_closed`), `403` permission, `401` auth.
