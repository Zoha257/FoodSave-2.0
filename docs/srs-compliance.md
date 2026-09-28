# FoodSave 2.0 SRS Compliance

This document records the implementation status against the FoodSave 2.0 Software Requirements Specification.

## Implemented

- Role-based authentication for admin, donor, NGO, driver and volunteer workflows.
- Donation creation, editing, pickup requests, driver tasks, delivery tracking, notifications and reporting.
- Separate Python AI/research layer using pandas, NumPy, scikit-learn and joblib.
- Expiry-risk prediction with probability and Low/Medium/High risk bands.
- Rule-based, Logistic Regression and Random Forest baselines.
- Precision, recall, F1, ROC-AUC, high-risk recall and confusion matrices.
- PHP donor and JSON API donation paths with prediction persistence and audit logging.
- Human-readable AI outputs in NGO/admin workflows.
- Transparent weighted donation-to-NGO matching.
- PHP and optional Streamlit research dashboards.
- AI-service failure isolation: a donation remains usable when inference is unavailable.
- Reproducibility metadata written to `ai/reports/experiment_manifest.json` when training runs.

## Partial or Prototype-Only

- The current operational export is small, so training may use the explicitly labelled synthetic prototype dataset.
- The current target approximates `expired before successful pickup` with `donation_status = expired`; a future export should calculate the target from expiration and pickup timestamps.
- Temporal evaluation is available when operational timestamps and sufficient history exist, but current history may be too small for a defensible holdout.
- Matching uses transparent city/category/history/feasibility proxies. Real distance, route time and NGO capacity are future improvements.
- Feature ablation, calibration analysis, drift monitoring and record-level false-positive/false-negative review are not yet implemented.
- Experiment manifests are written as JSON; database storage in `ai_experiments` remains a future research-dashboard enhancement.

## Reproduction

Install the declared AI dependencies:

```text
py -3 -m pip install -r ai/requirements.txt
```

Train and generate reports:

```text
py -3 ai/src/train.py
```

Evaluate the saved model against the committed export:

```text
py -3 ai/src/evaluate.py
```

Synthetic outputs must be labelled as prototype results and must not be presented as measured real-world impact.

## Recent Compliance Fixes

- API controllers are now constructed after routing, so public auth endpoints are not blocked by protected controller initialization.
- Donation API filters now build SQL predicates safely instead of rewriting an existing `WHERE` clause.
- Legacy feature exports receive explicit unknown workflow-state values instead of failing evaluation.
- Operational exports derive pickup and volunteer state from existing anonymized IDs when those feature columns are absent.
- API-created donations now receive the same optional expiry-risk prediction and audit logging as browser-created donations.
