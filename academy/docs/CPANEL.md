# Installing on cPanel (simple, no Terminal)

The site runs directly at your domain (e.g. `https://oxciracademy.com/`). No `/public` in the address and no Document Root change are needed.

You need: cPanel hosting with **PHP 8.3+** and MySQL, plus the file `academy-deploy.zip`.

## Step 1 — Create a database

cPanel › **MySQL® Databases**:

1. **Create New Database** (e.g. `academy`). cPanel shows the full name, e.g. `cpuser_academy`.
2. **Add New User** with a strong password.
3. **Add User To Database**, then tick **ALL PRIVILEGES**.

Write down the database name, user name and password (full names, with the prefix).

## Step 2 — Choose PHP 8.3

cPanel › **Select PHP Version** (or **MultiPHP Manager**): choose **8.3** or newer.

## Step 3 — Upload into `public_html`

cPanel › **File Manager** › open **`public_html`**:

1. Delete the hosting's default files if present (`index.html`, `default.htm`…). Keep `cgi-bin` and `.well-known`.
2. **Upload** `academy-deploy.zip` into `public_html`.
3. Right-click the zip › **Extract** › extract into `/public_html` itself, not into a sub-folder.
4. Delete the zip.

`public_html` should now contain `app`, `public`, `storage`, `vendor`, `.htaccess`… The `.htaccess` file makes the site work from `public_html` and blocks access to private files. Turn on *Settings › Show Hidden Files* to see it.

## Step 4 — Open your domain and fill one form

Visit `https://oxciracademy.com/`. The installer opens automatically:

1. Everything should be ✅.
2. Check that the site URL is `https://oxciracademy.com` (no `/public`).
3. Enter the academy name, your WhatsApp number, the database details from Step 1, and your admin email and password.
4. Click **تثبيت الآن (Install now)**.

The installer creates everything, then deletes itself. Click **تسجيل الدخول** and log in.

**Done.** In the dashboard, open **الإعدادات (Settings)** to add your logo and contact details, then create your first course.

---

## Good to know

- **HTTPS:** enable the free SSL certificate in cPanel › **SSL/TLS Status** (AutoSSL) and use `https://` in the site URL.
- **Video uploads:** videos are sent in small pieces that automatically fit your host's PHP limits, so large videos upload even with the default 2 MB limit. Raising `upload_max_filesize` to 32M in **MultiPHP INI Editor** just makes uploads faster.
- **Cleanup job (recommended):** cPanel › **Cron Jobs** › *Once Per Day*:
  ```
  cd ~/public_html && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Emails (account confirmation, password reset):** in File Manager edit `public_html/.env`, set `MAIL_MAILER=smtp` and fill in the `MAIL_*` lines with an email account from cPanel › **Email Accounts** › *Connect Devices*. Until then, you can turn off "require email confirmation before requesting a certificate" in Settings › WhatsApp & certificates.

## Troubleshooting

| Problem | Fix |
|---|---|
| A ❌ in the installer | Select PHP Version › enable the missing extension, or set folder permissions to 755 |
| "Cannot connect to the database" | Use the **full** names with the cPanel prefix, and check the user was added to the database |
| The hosting's default page still appears | Delete `index.html` / `default.htm` from `public_html` |
| Error 403 on every page | The `.htaccess` file is missing: extract the zip again with hidden files, or ask your host to enable `mod_rewrite` |
| Error 500 | Check PHP is 8.3+, then look at `public_html/storage/logs/` |
| You want to reinstall | Delete `public_html/.env` and empty the database, re-upload `public/install.php` from the zip, then open the site again |

## Alternative: separate folder (advanced)

You can also extract the zip into a folder outside `public_html` (e.g. `~/academy`) and set the domain's **Document Root** to `academy/public`. This keeps the code completely outside the web folder. Both setups are protected.

Prefer the command line? See [INSTALL.md](INSTALL.md) (`php artisan academy:install`).
