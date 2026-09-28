"""FoodSave 2.0 research dashboard.

Run from the project root:
    streamlit run ai/dashboard.py
"""
from pathlib import Path
import json
import pandas as pd
import streamlit as st

ROOT=Path(__file__).resolve().parent
REPORT=ROOT/"reports"/"evaluation.json"
EXPLAIN=ROOT/"reports"/"model_explanations.json"

st.set_page_config(page_title="FoodSave 2.0 AI Research", page_icon="🥗", layout="wide")
st.title("FoodSave 2.0 — AI Research Dashboard")
st.caption("Expiry-risk prediction, evaluation and transparent decision support.")

if not REPORT.exists():
    st.warning("No evaluation report found. Run `python ai/src/train.py` first.")
    st.stop()

report=json.loads(REPORT.read_text(encoding="utf-8"))
explain=json.loads(EXPLAIN.read_text(encoding="utf-8")) if EXPLAIN.exists() else {}

if report.get("dataset_source") == "synthetic_prototype":
    st.warning("The current experiment uses synthetic prototype data because the operational dataset is too small for a defensible evaluation. Do not report these metrics as real-world impact.")

c1,c2,c3=st.columns(3)
c1.metric("Dataset size", report.get("dataset_size",0))
c2.metric("Selected model", report.get("selected_model","—"))
c3.metric("Dataset source", report.get("dataset_source","—"))

st.subheader("Model comparison")
rows=[]
for name,m in report.get("models",{}).items():
    rows.append({
        "Model":name,
        "Accuracy":m.get("accuracy"),
        "Precision":m.get("precision"),
        "Recall":m.get("recall"),
        "F1":m.get("f1"),
        "ROC-AUC":m.get("roc_auc"),
        "High-risk recall":m.get("high_risk_recall",m.get("recall"))
    })
st.dataframe(pd.DataFrame(rows), use_container_width=True)

selected=report.get("selected_model")
if selected:
    st.subheader(f"{selected} confusion matrix")
    cm=report["models"][selected].get("confusion_matrix",[[0,0],[0,0]])
    st.dataframe(pd.DataFrame(cm,index=["Actual 0","Actual 1"],columns=["Predicted 0","Predicted 1"]),use_container_width=False)

st.subheader("Feature effects")
if explain.get("features"):
    st.dataframe(pd.DataFrame(explain["features"]),use_container_width=True)

st.subheader("Target distribution")
target=report.get("target_distribution",{})
if target:
    st.bar_chart(pd.DataFrame({"count":[target.get("not_expired",0),target.get("expired",0)]},index=["Not expired","Expired"]))

st.subheader("Limitations")
for item in report.get("limitations",[]):
    st.write("•",item)
st.info("AI output is decision support. It does not replace human NGO/admin judgment or professional food-safety guidance.")
