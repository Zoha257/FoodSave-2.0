# FoodSave AI Data

`foodsave_training_data_raw.csv` and `foodsave_features_v1.csv` are prototype exports currently included with the project.

The current operational sample is too small for a defensible machine-learning evaluation. Therefore `ai/src/train.py` uses a clearly labelled deterministic synthetic prototype dataset when the sample is insufficient.

Do not present synthetic observations or prototype metrics as real-world FoodSave impact.

As real operational history grows, export an anonymized feature dataset and rerun:

```bash
python ai/src/train.py
```

Record dataset provenance, date range, missing-data handling and target definition with every research experiment.
