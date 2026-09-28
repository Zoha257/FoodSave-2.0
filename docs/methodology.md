# FoodSave 2.0 AI Methodology

## Research question

Can machine learning identify donations that are likely to expire before successful pickup early enough to support operational prioritization?

## Target

Binary target: `expired` vs `not expired`.

The target is derived from the final donation outcome for operational records. Prototype synthetic records use an explicitly labelled synthetic generator.

## Features

- category
- quantity_value
- days_until_expiration
- creation_hour
- creation_day_of_week
- pickup_requested
- volunteer_assigned

## Evaluation

The preferred evaluation is chronological to reduce temporal leakage. A deterministic prototype fallback is used only when the operational dataset is too small for a defensible split.

Models:
- rule-based baseline
- Logistic Regression
- Random Forest

Metrics:
- precision
- recall
- F1
- ROC-AUC
- high-risk recall
- confusion matrix

## Responsible interpretation

Risk probability is decision support. It is not a food-safety determination and must not trigger irreversible actions automatically.
