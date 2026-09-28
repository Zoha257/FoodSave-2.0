"""Reproducible FoodSave 2.0 expiry-risk training pipeline.

The script prefers FoodSave operational features. If the local dataset is too
small to support a defensible train/test split, it creates a clearly labelled
synthetic prototype dataset so the software remains runnable. Synthetic data
must never be reported as real-world evidence.
"""
from __future__ import annotations

import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

ROOT = Path(__file__).resolve().parents[2]
DATA_DIR = ROOT / "ai" / "data"
MODEL_DIR = ROOT / "ai" / "models"
REPORT_DIR = ROOT / "ai" / "reports"
MODEL_DIR.mkdir(parents=True, exist_ok=True)
REPORT_DIR.mkdir(parents=True, exist_ok=True)

RAW_FEATURES = DATA_DIR / "foodsave_features_v1.csv"
RANDOM_STATE = 42

FEATURES = [
    "category",
    "quantity_value",
    "days_until_expiration",
    "creation_hour",
    "creation_day_of_week",
    "pickup_requested",
    "volunteer_assigned",
]
NUMERIC = FEATURES[1:]
CATEGORICAL = ["category"]


def make_synthetic_dataset(n: int = 500) -> pd.DataFrame:
    """Create prototype-only data when real history is insufficient."""
    rng = np.random.default_rng(RANDOM_STATE)
    categories = np.array(
        ["vegetables", "fruits", "dairy", "meat", "grains", "prepared_food", "other"]
    )
    category = rng.choice(categories, n)
    quantity = np.round(rng.uniform(0.5, 40, n), 1)
    days = rng.integers(0, 16, n)
    hour = rng.integers(0, 24, n)
    dow = rng.integers(0, 7, n)
    pickup = rng.binomial(1, 0.45, n)
    volunteer = rng.binomial(1, 0.35, n)

    # Prototype generator: expiry risk increases as shelf-life shrinks and
    # decreases when pickup/volunteer support is already present.
    logit = (
        2.3
        - 0.43 * days
        - 0.025 * quantity
        - 0.90 * pickup
        - 0.65 * volunteer
        + 0.20 * ((hour >= 18) & (hour <= 23))
        + 0.35 * np.isin(category, ["prepared_food", "meat"])
        + rng.normal(0, 0.65, n)
    )
    probability = 1 / (1 + np.exp(-logit))
    expired = rng.binomial(1, probability)

    return pd.DataFrame(
        {
            "category": category,
            "quantity_value": quantity,
            "days_until_expiration": days,
            "creation_hour": hour,
            "creation_day_of_week": dow,
            "pickup_requested": pickup,
            "volunteer_assigned": volunteer,
            "expired": expired,
            "data_source": "synthetic_prototype",
        }
    )


def load_dataset():
    if not RAW_FEATURES.exists():
        return make_synthetic_dataset(), "synthetic_prototype"

    df = pd.read_csv(RAW_FEATURES)
    # Backfill workflow-state features for exports created before the AI
    # fields were added. The IDs are anonymized operational indicators.
    if "pickup_requested" not in df:
        df["pickup_requested"] = df.get("pickup_request_id", pd.Series(index=df.index)).notna().astype(int)
    if "volunteer_assigned" not in df:
        df["volunteer_assigned"] = df.get("volunteer_id", pd.Series(index=df.index)).notna().astype(int)
    required = set(FEATURES + ["donation_status"])
    if not required.issubset(df.columns):
        return make_synthetic_dataset(), "synthetic_prototype"

    df = df.copy()
    df["expired"] = df["donation_status"].astype(str).str.lower().eq("expired").astype(int)

    for col in NUMERIC:
        df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)
    df["category"] = df["category"].fillna("other").astype(str)

    # A tiny dataset with one class cannot support evaluation.
    if len(df) < 30 or df["expired"].nunique() < 2 or df["expired"].value_counts().min() < 5:
        return make_synthetic_dataset(), "synthetic_prototype"

    return df, "foodsave_operational_features"


def metrics(y_true, y_pred, y_prob):
    result = {
        "accuracy": round(float(accuracy_score(y_true, y_pred)), 4),
        "precision": round(float(precision_score(y_true, y_pred, zero_division=0)), 4),
        "recall": round(float(recall_score(y_true, y_pred, zero_division=0)), 4),
        "f1": round(float(f1_score(y_true, y_pred, zero_division=0)), 4),
        "roc_auc": None,
    }
    if len(np.unique(y_true)) == 2:
        result["roc_auc"] = round(float(roc_auc_score(y_true, y_prob)), 4)
    return result


def rule_baseline(x):
    # Explicit, auditable baseline. It is a decision-support baseline, not
    # a food-safety rule.
    return (
        (x["days_until_expiration"] <= 1)
        | ((x["days_until_expiration"] <= 2) & (x["pickup_requested"] == 0))
    ).astype(int).to_numpy()


def main():
    df, source = load_dataset()

    # For operational data, prefer chronological evaluation to avoid temporal leakage.
    if "donation_created_at" in df.columns:
        df["_time"] = pd.to_datetime(df["donation_created_at"], errors="coerce")
        df = df.sort_values("_time", na_position="last")

    split = int(len(df) * 0.75)
    split = min(max(split, 1), len(df) - 1)
    train_df = df.iloc[:split].copy()
    test_df = df.iloc[split:].copy()

    X_train = train_df[FEATURES]
    y_train = train_df["expired"].astype(int)
    X_test = test_df[FEATURES]
    y_test = test_df["expired"].astype(int)

    # If a chronological split leaves a single class in either side, use a
    # stratified deterministic split only for prototype data.
    if y_train.nunique() < 2 or y_test.nunique() < 2:
        df = make_synthetic_dataset()
        source = "synthetic_prototype"
        split = int(len(df) * 0.75)
        train_df, test_df = df.iloc[:split].copy(), df.iloc[split:].copy()
        X_train, y_train = train_df[FEATURES], train_df["expired"].astype(int)
        X_test, y_test = test_df[FEATURES], test_df["expired"].astype(int)

    preprocessor = ColumnTransformer(
        [
            ("category", OneHotEncoder(handle_unknown="ignore"), CATEGORICAL),
            ("numeric", StandardScaler(), NUMERIC),
        ]
    )

    logistic = Pipeline(
        [
            ("preprocessor", preprocessor),
            ("model", LogisticRegression(max_iter=1000, class_weight="balanced", random_state=RANDOM_STATE)),
        ]
    )
    forest = Pipeline(
        [
            ("preprocessor", preprocessor),
            ("model", RandomForestClassifier(
                n_estimators=250, max_depth=8, class_weight="balanced",
                random_state=RANDOM_STATE
            )),
        ]
    )

    models = {"Logistic Regression": logistic, "Random Forest": forest}
    evaluations = {}

    baseline_pred = rule_baseline(X_test)
    baseline_prob = baseline_pred.astype(float)
    evaluations["Rule-Based Baseline"] = {
        **metrics(y_test, baseline_pred, baseline_prob),
        "confusion_matrix": confusion_matrix(y_test, baseline_pred).tolist(),
    }

    for name, model in models.items():
        model.fit(X_train, y_train)
        pred = model.predict(X_test)
        prob = model.predict_proba(X_test)[:, 1]
        evaluations[name] = {
            **metrics(y_test, pred, prob),
            "high_risk_recall": round(float(recall_score(y_test, pred, zero_division=0)), 4),
            "confusion_matrix": confusion_matrix(y_test, pred).tolist(),
        }

    # The initial integration requires probability output; Logistic Regression
    # is the default because its probabilities are directly available and its
    # high-risk recall is explicitly reported.
    selected = logistic
    joblib.dump(selected, MODEL_DIR / "foodsave_expiry_risk_model.pkl")

    # Save human-readable feature effects for the research dashboard.
    names = selected.named_steps["preprocessor"].get_feature_names_out()
    coefs = selected.named_steps["model"].coef_[0]
    importance = [
        {"feature": str(name).replace("category__category_", "category: ").replace("numeric__", ""),
         "coefficient": round(float(coef), 6),
         "absolute_importance": round(float(abs(coef)), 6)}
        for name, coef in sorted(zip(names, coefs), key=lambda x: abs(x[1]), reverse=True)
    ]

    (REPORT_DIR / "model_explanations.json").write_text(
        json.dumps({
            "model": "Logistic Regression",
            "model_version": "logistic-v1",
            "interpretation": "Positive coefficients increase predicted expiry-risk probability; negative coefficients decrease it.",
            "features": importance,
        }, indent=2),
        encoding="utf-8",
    )

    report = {
        "dataset_source": source,
        "dataset_size": int(len(df)),
        "feature_columns": FEATURES,
        "target": "expired before successful pickup, approximated by donation_status=expired in the current export",
        "random_state": RANDOM_STATE,
        "train_size": int(len(train_df)),
        "test_size": int(len(test_df)),
        "target_distribution": {
            "not_expired": int((df["expired"] == 0).sum()),
            "expired": int((df["expired"] == 1).sum()),
        },
        "evaluation_method": "75/25 chronological split when operational timestamps are available; deterministic prototype fallback otherwise.",
        "selected_model": "Logistic Regression",
        "models": evaluations,
        "limitations": [
            "Prototype metrics are not real-world impact evidence.",
            "Synthetic fallback is used when operational history is insufficient.",
            "Risk thresholds are decision-support bands and are not food-safety rules.",
        ],
    }
    (REPORT_DIR / "evaluation.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    (REPORT_DIR / "experiment_manifest.json").write_text(json.dumps({
        "experiment_version": "expiry-risk-v1",
        "dataset_source": source,
        "dataset_file": str(RAW_FEATURES.relative_to(ROOT)),
        "target_definition": report["target"],
        "feature_columns": FEATURES,
        "evaluation_method": report["evaluation_method"],
        "random_state": RANDOM_STATE,
        "limitations": report["limitations"],
    }, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
