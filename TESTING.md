DOT SHIP — End-to-end Smoke Test

This repository includes a small end-to-end smoke test that exercises the booking → transit → out_for_delivery → delivery-code verification flow, including wrong-code locking and admin reissue.

Prerequisites
- PHP CLI (the same PHP version your app runs). Example on Windows with XAMPP:

```powershell
C:\xampp\php\php.exe -v
```

- A running MongoDB instance (local or remote) accessible via `MONGODB_URI`. If you don't have MongoDB, the app falls back to a JSON store (`storage/dotship-data.json`), but full integration requires MongoDB.
- Optional: `DOTSHIP_FORMSPREE_ENDPOINT` environment variable (Formspree) for real email delivery. If not set, the test will write to `storage/notifications.log`.

Files
- `tools/e2e_test.php`: The test script. Creates a temporary shipment, simulates admin progression, generates OTPs, attempts wrong codes to lock, reissues a code, verifies delivery with the correct code, and cleans up.

How to run
1. Ensure your environment variables are set if needed (optional):

```powershell
# Example (PowerShell)
$env:MONGODB_URI = 'mongodb://127.0.0.1:27017'
$env:DOTSHIP_FORMSPREE_ENDPOINT = 'https://formspree.io/f/xwpbardz'
```

2. Run the test from the repository root:

```powershell
C:\xampp\php\php.exe tools\e2e_test.php
```

Notes
- The test will attempt to insert and remove documents in the `shipments` and `otps` collections. It uses the same application helpers so behavior matches runtime.
- If MongoDB is not reachable, the script will fail with a clear error.
- The test will send (or attempt to send) emails using the configured `DOTSHIP_FORMSPREE_ENDPOINT`. To avoid external network calls, you can set `DOTSHIP_FORMSPREE_ENDPOINT` to empty string before running the test; the system will fall back to logging notifications.

Troubleshooting
- "Cannot access MongoDB/compat store": ensure MongoDB is running or your `MONGODB_URI` is correct.
- If emails don't arrive, inspect `storage/notifications.log` for the logged messages.

Want more?
- I can add a small GitHub Action workflow to run this test on push (requires a test MongoDB instance), or extend the script to produce a TAP-format report. Let me know which you prefer.
