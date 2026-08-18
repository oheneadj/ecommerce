# How To: Set Up Google Drive Backups

**Companion to:** `infrastructure-deployment.md` §2, `CLAUDE.md`
**Audience:** whoever is deploying/operating this app — one-time setup per deployment (per-client isolation means each installation gets its own Google Cloud project/service account, never a shared one).

---

## 0. How this fits together

Database + uploaded-files backups (`App\Jobs\RunBackupJob`) upload to a Google Drive folder via a Flysystem disk named `gdrive` (`config/filesystems.php`), authenticated with a **Google Cloud service account** — not a personal Google login. A service account works unattended: there's no consent screen to click through, and no token that silently expires, so the daily/weekly scheduled backup keeps running with nobody watching it.

Everything below is a one-time setup per deployment. Once done, backups are triggered from **Settings → Store Settings → Backups** (schedule) and **Settings → Backups** (manual run + history + restore) — see `infrastructure-deployment.md` §2 for how the feature itself behaves.

---

## 1. Create or select a Google Cloud project

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Top-left project dropdown → **New Project**
3. Name it something identifiable (e.g. `yourstore-backups`) and create it

---

## 2. Enable the Google Drive API

1. Left sidebar → **APIs & Services → Library**
2. Search **Google Drive API**
3. Click it → **Enable**

---

## 3. Create the service account and its key

1. **APIs & Services → Credentials**
2. **Create Credentials → Service account**
3. Give it a name (e.g. `backup-uploader`) and finish the wizard — no project-level IAM role is needed, since access is granted per-folder in Drive itself (step 4)
4. Open the new service account → **Keys** tab
5. **Add Key → Create new key → JSON** — this downloads a `.json` file

**This file is a credential, not a config file.** Never commit it to git, never put its raw contents in a chat/ticket/Slack message. Treat it exactly like a database password.

---

## 4. Create the destination Drive folder and share it with the service account

1. In regular Google Drive (drive.google.com), create a folder (e.g. "Store Backups") or pick an existing one
2. Right-click the folder → **Share**
3. Open the downloaded JSON key file and copy the `client_email` value — it looks like `backup-uploader@yourstore-backups.iam.gserviceaccount.com`
4. Paste that email into the share dialog, grant it **Editor** access
5. Copy the folder's ID from its URL: `drive.google.com/drive/folders/`**`THIS_PART`**

---

## 5. Set the environment variables

Upload the JSON key file to the server, **outside the public web root** — e.g. `storage/app/google-drive-service-account.json` (already covered by `.gitignore`; never commit it).

```env
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=/full/path/to/storage/app/google-drive-service-account.json
GOOGLE_DRIVE_FOLDER_ID=the-folder-id-from-step-4
BACKUP_ARCHIVE_PASSWORD=some-strong-password
```

`BACKUP_ARCHIVE_PASSWORD` isn't Google-specific, but it belongs here: it encrypts the backup zip. Strongly recommended — the database dump contains customer PII (names, phone numbers, addresses, order history).

---

## 6. Verify it worked

1. **Settings → Store Settings → Backups** — set the remote storage provider to Google Drive and save. If the credentials aren't actually in place, this save is rejected with "no credentials configured" rather than silently accepting a broken setup.
2. **Settings → Backups → Run backup now** — confirm a new `.zip` actually lands in the Drive folder before ever relying on the automatic schedule.
3. Turn on automatic backups (Daily or Weekly) once the manual run has been verified.

---

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| "No credentials configured" when saving the provider | `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` or `GOOGLE_DRIVE_FOLDER_ID` is empty, or the app hasn't picked up a changed `.env` (run `php artisan config:clear`) |
| Manual run fails immediately, Super Admin gets emailed right away | Credentials genuinely missing/invalid — this path is never retried, since retrying a bad credential doesn't fix it (see `infrastructure-deployment.md` §2) |
| Manual run fails only after a short delay | A transient connection failure — this *is* retried automatically (3 attempts, 30s apart) before anyone is notified; check the failure reason on the Backups history page |
| Backup succeeds but the folder in Drive stays empty | The service account almost certainly wasn't actually shared on the folder (step 4) — double-check the exact `client_email`, not a personal Google account |
| "Access denied" from the `mysql` CLI during a Restore | Unrelated to Google Drive — this app's restore path always uses the `mysql` connection block in `config/database.php`, regardless of which connection is active |
