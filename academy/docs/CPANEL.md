# Installing on cPanel (simple, no Terminal)

You need: cPanel hosting with **PHP 8.3+** and MySQL, plus the file `academy-deploy.zip`.

## Step 1 — Create a database (2 minutes)

cPanel › **MySQL® Databases**:

1. **Create New Database**, e.g. `academy`. cPanel shows the full name, e.g. `cpuser_academy`.
2. **Add New User** with a strong password. Note the full name, e.g. `cpuser_academy`.
3. **Add User To Database**, then tick **ALL PRIVILEGES**.

Write down the database name, user name and password.

## Step 2 — Choose PHP 8.3

cPanel › **Select PHP Version** (or **MultiPHP Manager**): choose **8.3** or newer for your domain.

## Step 3 — Upload and extract

cPanel › **File Manager**:

1. Open your home folder (the folder that *contains* `public_html`, not `public_html` itself).
2. Click **Upload**, upload `academy-deploy.zip`, and go back.
3. Right-click the zip › **Extract**, into a folder named `academy`.

## Step 4 — Point your domain to the `public` folder

cPanel › **Domains** › **Manage** next to your domain › set **Document Root** to:

```
academy/public
```

Save. (If you cannot change the main domain, create a subdomain such as `learn.yourdomain.ma` and give *it* this document root.)

## Step 5 — Open your site and fill one form

Visit your domain. The installer opens automatically:

1. It checks the server (everything should be ✅).
2. Enter the academy name, your WhatsApp number, the database details from Step 1, and your admin email and password.
3. Click **تثبيت الآن (Install now)**.

The installer creates everything, then deletes itself. Click **تسجيل الدخول** and log in.

**Done.** In the dashboard, open **الإعدادات (Settings)** to add your logo and contact details, then create your first course.

---

## Recommended extras (optional)

**Bigger video uploads:** cPanel › **MultiPHP INI Editor** › set `upload_max_filesize = 32M` and `post_max_size = 40M`. Videos upload in 5 MB pieces, so this is usually enough even for long videos.

**Cleanup job:** cPanel › **Cron Jobs** › *Once Per Day*:

```
cd ~/academy && php artisan schedule:run >> /dev/null 2>&1
```

**Emails (account confirmation, password reset):** edit `academy/.env` in File Manager and fill in the `MAIL_*` lines with your email account from cPanel › **Email Accounts** › *Connect Devices*. Set `MAIL_MAILER=smtp`. Until then, you can switch off "require email confirmation before requesting a certificate" in Settings › WhatsApp & certificates.

## Troubleshooting

| Problem | Fix |
|---|---|
| A ❌ in the installer checks | Select PHP Version › enable the missing extension, or set folder permissions to 755 |
| "Cannot connect to the database" | Use the **full** names with the cPanel prefix, and check the user was added to the database |
| Blank page or error 500 | Make sure the document root is `academy/public` and PHP is 8.3+. Details are in `academy/storage/logs/` |
| Course images don't appear | Ask your host to allow symbolic links (FollowSymLinks). Videos and files work anyway |
| You want to reinstall | Delete `academy/.env` and empty the database. Re-upload `public/install.php` from the zip, then open the site again |

Prefer the command line? See [INSTALL.md](INSTALL.md) (`php artisan academy:install`).
