"""Evaluate a saved FoodSave model on a CSV feature dataset.

This utility is intentionally separate from training so model evaluation can
be rerun without changing the trained artifact.
"""
from __future__ import annotations
import json
from pathlib import Path
import joblib
import pandas as pd
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score, confusion_matrix

from features import FEATURE_COLUMNS

ROOT=Path(__file__).resolve().parents[2]
MODEL=ROOT/"ai/models/foodsave_expiry_risk_model.pkl"
DATA=ROOT/"ai/data/foodsave_features_v1.csv"

def main():
    model=joblib.load(MODEL)
    df=pd.read_csv(DATA)
    features = FEATURE_COLUMNS
    # Older exports do not contain workflow-state features yet. Treat the
    # missing state as unknown rather than making evaluation fail.
    for c in ("pickup_requested", "volunteer_assigned"):
        if c not in df:
            df[c] = 0
    for c in features[1:]:
        df[c]=pd.to_numeric(df[c],errors="coerce").fillna(0)
    df["category"]=df["category"].fillna("other").astype(str)
    y=df["donation_status"].astype(str).str.lower().eq("expired").astype(int)
    pred=model.predict(df[features])
    prob=model.predict_proba(df[features])[:,1]
    out={
      "accuracy": accuracy_score(y,pred),
      "precision": precision_score(y,pred,zero_division=0),
      "recall": recall_score(y,pred,zero_division=0),
      "f1": f1_score(y,pred,zero_division=0),
      "roc_auc": roc_auc_score(y,prob) if y.nunique()==2 else None,
      "confusion_matrix": confusion_matrix(y,pred).tolist()
    }
    print(json.dumps(out,indent=2))

if __name__=="__main__":
    main()
