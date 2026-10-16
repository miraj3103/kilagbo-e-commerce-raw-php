# KILAGBO PROJECT WORKFLOW & DEPLOYMENT SAFETY

## Project status
Kilagbo is a LIVE production project that has been downloaded to Laragon for local development.

The development rule is:

> Local environment changes must never be uploaded blindly to production.

---

## 1. Files requiring special deployment review

Always manually review these before any live upload:

- `config/config.php`
- `.htaccess`

Local-only changes may be made to these files, but keep an untouched copy of the live/original version.

Suggested local backup names:

- `config/config.php.live-backup`
- `.htaccess.live-backup`

The backup files are ignored by Git.

---

## 2. Change labels to use during development

Every important change should be thought of as one of:

- `LOCAL ONLY`
- `SAFE FOR LIVE`
- `LIVE CONFIG REQUIRED`
- `DATABASE MIGRATION REQUIRED`

Do not mix local-environment changes with production business-logic changes without reviewing them separately.

---

## 3. Local environment

Expected local setup:

- Project: `D:\laragon\www\kilagbo`
- Local URL: `http://kilagbo.test`
- Local DB: `kilagbo`
- DB host: `localhost`
- DB user: `root`
- DB password: empty if using default Laragon configuration

---

## 4. Git / GitHub rules

### Do NOT commit runtime uploads
The `uploads/` directory contains product images, seller images, profile images, carousel images, and other runtime media.

These files are intentionally excluded from Git.

`uploads/index.php` should remain tracked because it is an application/security file.

### Do NOT commit downloaded project archives
Files such as `kilagbo.zip` are backup/source archives and must not be stored in normal Git history.

### Do NOT commit raw live DB dumps
A production `database.sql` can contain customer/business data, password hashes, access tokens, API credentials, and other sensitive information.

Keep live DB backups outside the Git repository.

If schema changes are needed, create a dedicated migration SQL containing only the required schema/data changes.

### Do NOT use Git LFS for runtime uploads
Runtime product/seller images belong in the server's upload storage, not in source-control history.

---

## 5. Existing tracked files warning

Adding a file to `.gitignore` does NOT remove it from commits that already contain it.

If `uploads/` or `kilagbo.zip` was already committed, remove it from the Git index/history before pushing.

Never delete the actual local upload files just to remove them from Git. Use `git rm --cached`, not a normal filesystem delete.

---

## 6. Possible duplicate project folder

The current Git output showed paths under both:

- `uploads/...`
- `kilagbo/uploads/...`

Before finalizing the repository, verify whether there is an accidental nested project directory:

`D:\laragon\www\kilagbo\kilagbo`

If it is merely a duplicate extraction/copy, it should not be committed as a second full project.

Do not delete it until its contents are compared with the real project root.

---

## 7. Database deployment safety

Before any live DB change:

1. Take a fresh production DB backup.
2. Keep the original local SQL dump unchanged.
3. Record every intended schema change.
4. Put production changes into a separate migration SQL.
5. Never overwrite the live DB using the full local database.
6. Run only reviewed migration statements in production.

---

## 8. Final live deployment checklist

Before uploading to production:

1. Back up current live files.
2. Back up the current live database.
3. Review Git diff/change list.
4. Review `config/config.php`.
5. Review `.htaccess`.
6. Exclude local-only files/settings.
7. Review any DB migration.
8. Upload intended source changes only.
9. Apply DB migration only if required.
10. Test:
   - Homepage
   - User login/registration
   - Admin login/dashboard
   - Product pages
   - Cart/checkout/order
   - Wholesale
   - Resell
   - Affiliate
   - Seller
   - Uploads
11. Keep a rollback copy ready.

---

## 9. Git push troubleshooting reminder

If GitHub reports a file larger than 100 MB, simply adding it to `.gitignore` is not enough if it already exists in commit history.

Clean the local commit/history first, then push again.

---

## Golden rule

> Never upload the entire local working folder to live without reviewing local-only configuration, `.htaccess`, database changes, runtime uploads, and secrets.
