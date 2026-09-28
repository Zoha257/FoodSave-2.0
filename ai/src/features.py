"""Feature engineering helpers shared by FoodSave 2.0 training/inference."""
from __future__ import annotations
from datetime import datetime
import re

FEATURE_COLUMNS = [
    "category",
    "quantity_value",
    "days_until_expiration",
    "creation_hour",
    "creation_day_of_week",
    "pickup_requested",
    "volunteer_assigned",
]


def quantity_to_number(value) -> float:
    match = re.search(r"\d+(?:\.\d+)?", str(value or "0"))
    return float(match.group()) if match else 0.0


def days_until(expiration_date, reference_time=None) -> float:
    reference_time = reference_time or datetime.now()
    expiry = datetime.fromisoformat(str(expiration_date))
    return max(0.0, (expiry - reference_time).total_seconds() / 86400)


def donation_to_features(
    category,
    quantity,
    expiration_date,
    created_at=None,
    pickup_requested=0,
    volunteer_assigned=0,
):
    created = created_at or datetime.now()
    if isinstance(created, str):
        created = datetime.fromisoformat(created.replace("Z", "+00:00").replace(" ", "T"))
        created = created.replace(tzinfo=None)

    return {
        "category": str(category or "other"),
        "quantity_value": quantity_to_number(quantity),
        "days_until_expiration": days_until(expiration_date, created),
        "creation_hour": created.hour,
        "creation_day_of_week": created.weekday(),
        "pickup_requested": int(bool(pickup_requested)),
        "volunteer_assigned": int(bool(volunteer_assigned)),
    }
