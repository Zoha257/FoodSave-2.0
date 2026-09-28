# FoodSave 2.0

**AI-Assisted Food Redistribution and Expiry Prevention Platform**

FoodSave 2.0 extends the existing FoodSave PHP/MySQL food-redistribution application with a separate Python AI/research layer. The operational platform remains responsible for users, donations, pickup workflows, drivers, volunteers and notifications; Python is responsible for dataset preparation, model training/evaluation and expiry-risk inference.

> **Research status:** the included prototype evaluation can fall back to synthetic data when the operational history is too small. Synthetic results are explicitly labelled and must not be presented as real-world impact.

## What is included

### Operational platform
- Role-based authentication for Admin, Donor, NGO, Driver and Volunteer.
- Donation creation and management.
- NGO donation browsing and pickup requests.
- Driver assignment, delivery tasks and tracking.
- Volunteer opportunities and delivery support.
- Notifications, reports, feedback and food-safety guidance.
- Map/delivery functionality already present in the FoodSave foundation.

### FoodSave 2.0 AI layer
- Expiry-risk prediction with probability + Low/Medium/High risk.
- Reproducible feature engineering.
- Rule-based baseline.
- Logistic Regression baseline.
- Random Forest baseline.
- Time-aware evaluation when operational timestamps are available.
- Precision, recall, F1, ROC-AUC and high-risk recall.
- Confusion matrices.
- Model explanation data for Logistic Regression.
- Prediction audit log in MySQL.
- Transparent weighted donation–NGO matching.
- PHP research dashboard.
- Optional Streamlit research dashboard.
- Optional Flask inference API.
- Human-in-the-loop design: AI supports decisions; it does not make irreversible food-safety decisions.

## Architecture

```text
FoodSave 2.0
│
├── PHP / HTML / CSS / JavaScript
│   ├── admin/
│   ├── donor/
│   ├── ngo/
│   ├── driver/
│   ├── volunteer/
│   └── api/
│
├── MySQL
│   ├── operational tables
│   ├── ai_prediction_logs
│   └── ai_experiments
│
└── Python AI / Research
    ├── ai/data/
    ├── ai/notebooks/
    ├── ai/models/
    ├── ai/reports/
    ├── ai/src/features.py
    ├── ai/src/train.py
    ├── ai/src/evaluate.py
    ├── ai/src/predict.py
    ├── ai/dashboard.py
    └── ai/api.py
```

## Installation

### 1. Existing PHP application
Use the PHP/MySQL setup you already use for FoodSave. Import `database/foodsave_db.sql` for a fresh database.

For an existing FoodSave database, run:

```text
database/foodsave_2_0_ai_update.sql
```

This adds the AI prediction fields and audit tables without requiring a rewrite of the operational system.

### 2. Python environment

From the FoodSave project root:

```bash
python -m venv .venv
```

Windows:

```bash
.venv\Scripts\activate
```

Install AI dependencies:

```bash
pip install -r ai/requirements.txt
```

### 3. Train the prototype model

```bash
python ai/src/train.py
```

The script creates:

```text
ai/models/foodsave_expiry_risk_model.pkl
ai/reports/evaluation.json
ai/reports/model_explanations.json
```

If the operational dataset has insufficient examples of both classes, the script uses a deterministic synthetic prototype dataset. The report records this as `synthetic_prototype`.

### 4. PHP → Python prediction bridge

The donor workflow calls:

```text
predict_api.py
```

It uses `FOODSAVE_PYTHON` from `.env` when provided, otherwise `python` from PATH.

Example:

```env
FOODSAVE_PYTHON=python
```

On Windows, an absolute path can be used if Apache/PHP cannot find Python:

```env
FOODSAVE_PYTHON=C:\Python311\python.exe
```

The core donation transaction does not fail if AI inference is unavailable. The donation remains usable and the failure is logged.

### 5. Research dashboard

PHP dashboard:

```text
Admin → AI Research
```

Optional Streamlit dashboard:

```bash
streamlit run ai/dashboard.py
```

### 6. Optional Python inference API

```bash
python ai/api.py
```

Endpoints:

```text
GET  /health
POST /predict
```

For a production deployment, place authentication, TLS, rate limiting and network restrictions in front of this service.

## AI methodology

The initial prediction target is:

> whether a donation is likely to expire before successful pickup.

Candidate features include:
- food category
- quantity
- remaining shelf-life
- creation hour
- day of week
- pickup-request state
- volunteer-assignment state

The initial model comparison contains:
1. Rule-based baseline
2. Logistic Regression
3. Random Forest

The project reports:
- accuracy
- precision
- recall
- F1
- ROC-AUC where applicable
- high-risk recall
- confusion matrix

The current integration uses Logistic Regression because it provides probability output and is straightforward to explain. The model should be retrained and reevaluated as real FoodSave history grows.

## Smart donation–NGO matching

Admin → **Smart Matching** ranks eligible NGOs using an explicit weighted score:

- 30% location fit
- 20% category relevance
- 15% historical completion rate
- 15% pickup feasibility
- 20% expiry urgency

This is intentionally transparent and replaceable. It is not an autonomous assignment system.

## Authentication UI

The login and registration UI has been redesigned around a simple professional palette rather than glassmorphism:

| Token | Value | Use |
|---|---|---|
| Background | `#F4FBF7` | Page background |
| Surface | `#FFFFFF` | Login/registration cards |
| Primary | `#166534` | Main actions |
| Primary dark | `#14532D` | Hover/focus |
| Accent | `#22C55E` | Success/highlight |
| Accent soft | `#DCFCE7` | Soft highlight |
| Text | `#0F172A` | Headings/body |
| Muted | `#475569` | Secondary text |
| Border | `#DCEFE3` | Card/input borders |
| Error | `#B91C1C` | Validation errors |
| Warning | `#92400E` | Warnings |

The authentication surfaces are opaque white, have no backdrop blur, and use high-contrast labels, inputs and links.

## Security

- Never commit `.env` or credentials.
- Use environment variables for database credentials, API keys and Python paths.
- Keep AI prediction outputs auditable.
- Minimize personal data in research datasets.
- Do not expose model or admin endpoints publicly without authentication.
- Review CSRF, session-cookie, password-reset and authorization settings before production deployment.
- Rotate any credential that has ever been committed to a public repository.

## Recommended market-ready roadmap

### Product
- Add a proper NGO capacity field instead of using pending-request count as a proxy.
- Add real geospatial distance/routing to matching.
- Add mobile-first delivery screens for drivers.
- Add organization verification and document workflows.
- Add notification preferences and delivery-time estimates.
- Add accessibility testing and keyboard/screen-reader support.

### AI / research
- Replace synthetic prototype data with a sufficiently large anonymized operational dataset.
- Use temporal holdout evaluation once enough history exists.
- Track calibration and probability reliability.
- Perform subgroup/error analysis for geography and organization type where ethically and legally appropriate.
- Add experiment versioning and model registry.
- Add feature ablation studies.
- Compare later models only after the baseline is stable.
- Measure operational outcomes such as pickup completion and time-to-pickup separately from model metrics.

### Production engineering
- Move the AI inference process from PHP subprocess calls to the optional Python API/service when traffic grows.
- Add automated tests and CI.
- Add structured application logging and monitoring.
- Add database migrations with explicit versioning.
- Add backups and restore testing.
- Add dependency pinning and vulnerability scanning.
- Use HTTPS, secure cookies, CSP and other production security headers.
- Separate development/demo credentials from production credentials.

### Academic / scholarship evidence
For a stronger research profile, keep:
- public GitHub repository
- dataset provenance document
- reproducible training command
- model comparison
- error analysis
- research methodology
- technical report/preprint
- demo/dashboard
- poster/presentation
- faculty/mentor review where possible

## Important limitation

A machine-learning model cannot prove that FoodSave reduces real-world food waste until it is evaluated on sufficient representative operational data and, ideally, through a carefully designed field or quasi-experimental study. Current prototype results should therefore be described as experimental evidence, not impact claims.
