"""FoodSave 2.0 prediction service.

Reads one donation feature object from stdin and returns JSON.
The operational PHP application can call this script without knowing
anything about the ML implementation.
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import joblib
import pandas as pd

PROJECT_ROOT = Path(__file__).resolve().parent
MODEL_PATH = PROJECT_ROOT / "ai" / "models" / "foodsave_expiry_risk_model.pkl"
LEGACY_MODEL_PATH = PROJECT_ROOT / "ai" / "notebooks" / "foodsave_logistic_model.pkl"
MODEL_VERSION = os.getenv("FOODSAVE_AI_MODEL_VERSION", "logistic-v1")

FEATURES = [
    "category",
    "quantity_value",
    "days_until_expiration",
    "creation_hour",
    "creation_day_of_week",
    "pickup_requested",
    "volunteer_assigned",
]


def load_model():
    path = MODEL_PATH if MODEL_PATH.exists() else LEGACY_MODEL_PATH
    if not path.exists():
        raise FileNotFoundError(
            "FoodSave AI model not found. Run: python ai/src/train.py"
        )
    return joblib.load(path)


def normalize_input(data: dict) -> dict:
    result = {
        "category": str(data.get("category", "other")),
        "quantity_value": float(data.get("quantity_value", 1)),
        "days_until_expiration": max(0.0, float(data.get("days_until_expiration", 0))),
        "creation_hour": int(data.get("creation_hour", 12)),
        "creation_day_of_week": int(data.get("creation_day_of_week", 0)),
        "pickup_requested": int(data.get("pickup_requested", 0)),
        "volunteer_assigned": int(data.get("volunteer_assigned", 0)),
    }
    return result


def predict_risk(data: dict) -> dict:
    model = load_model()
    normalized = normalize_input(data)
    frame = pd.DataFrame([normalized], columns=FEATURES)

    probability = float(model.predict_proba(frame)[0][1])
    prediction = int(model.predict(frame)[0])

    # Product-facing risk bands are intentionally simple and explainable.
    if probability >= 0.70:
        risk_level = "HIGH"
    elif probability >= 0.40:
        risk_level = "MEDIUM"
    else:
        risk_level = "LOW"

    return {
        "prediction": prediction,
        "risk_probability": round(probability * 100, 2),
        "risk_level": risk_level,
        "model_version": MODEL_VERSION,
        "features": normalized,
    }


if __name__ == "__main__":
    try:
        raw = sys.stdin.read().strip()
        data = json.loads(raw) if raw else {}
        print(json.dumps(predict_risk(data)))
    except Exception as exc:
        print(json.dumps({"error": str(exc)}))
        sys.exit(1)
