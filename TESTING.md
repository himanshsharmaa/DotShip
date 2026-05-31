DOT SHIP — End-to-end Smoke Test

This repository includes a small end-to-end smoke test that exercises the booking → transit → out_for_delivery → delivery-code verification flow, including wrong-code locking and admin reissue.

Prerequisites
- PHP CLI (the same PHP version your app runs). Example on Windows with XAMPP:

```powershell
C:\xampp\php\php.exe -v
```

- No external database server is required. The app uses SQLite (`storage/dotship.sqlite`) through PHP PDO.
- Optional: `DOTSHIP_FORMSPREE_ENDPOINT` environment variable (Formspree) for real email delivery. If not set, the test will write to `storage/notifications.log`.

Files
- `tools/e2e_test.php`: The test script. Creates a temporary shipment, simulates admin progression, generates OTPs, attempts wrong codes to lock, reissues a code, verifies delivery with the correct code, and cleans up.

How to run
1. Ensure your environment variables are set if needed (optional):

```powershell
# Example (PowerShell)
$env:DOTSHIP_SQLITE_PATH = "$PWD\storage\dotship.sqlite"
$env:DOTSHIP_FORMSPREE_ENDPOINT = 'https://formspree.io/f/xwpbardz'
```

2. Run the test from the repository root:

```powershell
C:\xampp\php\php.exe tools\e2e_test.php
```

Notes
- The test will insert, update, and remove records in the `shipments` and `otps` collections through the same application helpers used at runtime.
- The test will send (or attempt to send) emails using the configured `DOTSHIP_FORMSPREE_ENDPOINT`. To avoid external network calls, you can set `DOTSHIP_FORMSPREE_ENDPOINT` to empty string before running the test; the system will fall back to logging notifications.

Troubleshooting
- "Cannot access storage": ensure `storage/` is writable and the SQLite file path is correct.
- If emails don't arrive, inspect `storage/notifications.log` for the logged messages.

Want more?
- I can add a small GitHub Action workflow to run this test on push, or extend the script to produce a TAP-format report. Let me know which you prefer.
