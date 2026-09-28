# Changelog



## Project cleanup and security update

- Removed the hardcoded Google Maps API key.
- Added `.env` support and `.env.example`.
- Added `.gitignore`.
- Moved JWT secret configuration to environment variables.
- Removed weak JWT fallback secret.
- Improved PDO configuration and error handling.
- Fixed uninitialized PDO usage in NGO opportunity creation.
- Fixed uninitialized PDO usage in volunteer opportunity applications.
- Fixed broken volunteer dashboard links.
- Fixed NGO volunteer-opportunity redirect to the existing dashboard.
- Made API routing less dependent on a hardcoded project folder name.
- Made API CORS origins configurable.
- Added `SECURITY.md`.
- Updated `README.md` to describe the current project and both volunteer/driver workflows.


## Requirements alignment fixes
- Added canonical driver delivery tasks and driver GPS tracking tables.
- Fixed Driver class and REST DriverController.
- Added NGO driver assignment flow.
- Added automatic delivery-task creation when a donor approves a pickup.
- Added admin add/edit account management, including driver accounts.
- Removed fabricated driver dashboard/demo metrics.
- Fixed Google Maps helper JavaScript.
- Root login now redirects to the correct role dashboard.
