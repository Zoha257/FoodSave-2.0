# FoodSave Security Checklist

Before pushing/deploying the project publicly:

- The Google Maps API key is no longer stored in source code.
- Rotate/restrict any Google Maps key that was previously committed to GitHub.
- Copy `.env.example` to `.env` locally and set real values there.
- Never commit `.env`.
- Set a long random `JWT_SECRET` before using the API outside development.
- Replace all seeded/demo passwords before production.
- Use HTTPS.
- Use a dedicated MySQL account instead of `root`.
- Keep database credentials out of Git.
- Restrict the Google Maps key to the APIs and HTTP referrers/IPs actually required.
- Review API CORS (`API_ALLOWED_ORIGINS`) for the real frontend origin.
- Disable verbose application/database errors in production.
- Review every state-changing endpoint for CSRF/authorization protection.
- Remove development/test data before production.
- Rotate any secret that was ever exposed in Git history.

## Important: Git history

Removing a key from the latest files does **not** make a previously committed key secret. If the old Google Maps key was pushed to GitHub, rotate it in Google Cloud Console.

If the repository is public, also consider removing the old key from Git history using a history-rewriting tool. Do this carefully because rewriting history affects collaborators and existing clones.
