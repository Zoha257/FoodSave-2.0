"""Optional FoodSave 2.0 Python inference API.

Development:
    python ai/api.py

Production deployments should put authentication, TLS, rate limiting and
network restrictions in front of this service.
"""
from __future__ import annotations
import os
from flask import Flask, jsonify, request
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT))
from predict_api import predict_risk

app=Flask(__name__)

@app.get("/health")
def health():
    return jsonify({"status":"ok","service":"foodsave-ai","model_version":os.getenv("FOODSAVE_AI_MODEL_VERSION","logistic-v1")})

@app.post("/predict")
def predict():
    payload=request.get_json(silent=True) or {}
    try:
        return jsonify(predict_risk(payload))
    except Exception as exc:
        return jsonify({"error":str(exc)}),500

if __name__=="__main__":
    app.run(host=os.getenv("FOODSAVE_AI_HOST","127.0.0.1"),port=int(os.getenv("FOODSAVE_AI_PORT","8000")),debug=False)
