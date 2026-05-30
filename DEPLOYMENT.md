# Deployment Guide

## Important

This project is a PHP application, so it cannot run on GitHub Pages.
For a free viva/demo deployment, use a free PHP hosting provider and keep the code on GitHub.

## Recommended free setup

1. Upload this repository to GitHub.
2. Deploy the app to a free PHP host that supports file uploads and PHP execution.
3. Keep the built-in JSON fallback storage for the demo environment.

## Local config

- Copy `.env.example` to `.env` for local development.
- If you do not have MongoDB running, the app can still use the built-in JSON-compatible storage for the web UI.

## Live demo on free hosting

1. Upload the project files to your host's web root, usually `public_html`.
2. Make sure `storage/` is writable.
3. Open the site in the browser and use the demo accounts.

## GitHub upload

```bash
git init
git add .
git commit -m "Initial DOT SHIP project"
git branch -M main
git remote add origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```

## Notes

- GitHub is for source code and history.
- The live site should be hosted on a free PHP host, not GitHub Pages.
- If you want real MongoDB later, use MongoDB Atlas and update `MONGODB_URI`.
