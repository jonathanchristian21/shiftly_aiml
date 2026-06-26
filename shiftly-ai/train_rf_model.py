"""
train_rf_model.py
=================
Script training Random Forest untuk memprediksi estimasi salary (biaya) jadwal rumah sakit.

ALUR KERJA (pipeline lengkap):
================================
  1. Load data Employee_Satisfaction_Index.csv
  2. Feature Engineering
       - Tambah kolom is_nightshift, has_certification, is_senior
       - Hitung estimated_daily_salary dengan aturan bisnis (nightshift 1.2x, sertifikasi 1.15x)
       - Tambah kolom night_to_morning_flag (simulasi pola shift berurutan)
  3. Persiapan fitur (X) dan target (y = estimated_daily_salary)
  4. Encode fitur kategorikal (education, Dept, dll.)
  5. Split data menggunakan K-Fold Cross Validation (k=5)
  6. Training Random Forest baseline dan evaluasi (MAE, RMSE, R²) per fold
  7. Hyperparameter Tuning dengan RandomizedSearchCV
  8. Evaluasi model tuned (MAE, RMSE, R²)
  9. Simpan model + scaler ke file .joblib → dipakai rf_service.py saat runtime

Cara menjalankan:
-----------------
  cd shiftly-ai
  python train_rf_model.py

Output:
-------
  models/rf_salary_model.joblib   ← model Random Forest terlatih
  models/rf_feature_names.joblib  ← daftar nama kolom fitur (penting untuk konsistensi prediksi)

Catatan untuk mahasiswa:
------------------------
  - K-Fold: data dibagi jadi k bagian. Setiap fold, 1 bagian jadi testing, sisanya training.
    Tujuannya: menghindari model "hafal" data training (overfitting).
  - MAE (Mean Absolute Error): rata-rata selisih prediksi vs aktual. Makin kecil makin baik.
  - RMSE (Root Mean Squared Error): seperti MAE tapi kesalahan besar dihukum lebih berat.
  - R² (R-squared): seberapa banyak variasi data bisa dijelaskan model. Makin dekat 1 makin baik.
  - RandomizedSearchCV: mencoba kombinasi hyperparameter secara acak untuk cari yang terbaik.
    Lebih efisien dari GridSearchCV (tidak coba semua kombinasi).
"""

from __future__ import annotations

import os
import warnings
import numpy as np
import pandas as pd
import joblib

from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import KFold, RandomizedSearchCV, cross_val_score
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

warnings.filterwarnings("ignore")  # Suppress warning tidak penting saat training


# ─────────────────────────────────────────────────────────────────────────────
# KONFIGURASI PATH
# ─────────────────────────────────────────────────────────────────────────────

# Path file CSV data pegawai
CSV_PATH = os.path.join(os.path.dirname(__file__), "Employee_Satisfaction_Index.csv")

# Folder output model yang sudah ditraining
MODEL_DIR = os.path.join(os.path.dirname(__file__), "models")
MODEL_PATH = os.path.join(MODEL_DIR, "rf_salary_model.joblib")
FEATURE_NAMES_PATH = os.path.join(MODEL_DIR, "rf_feature_names.joblib")

# Konstanta bisnis (sama dengan salary_calculator.py agar konsisten)
WORKING_DAYS_PER_MONTH  = 22
NIGHT_SHIFT_MULTIPLIER  = 1.20
CERTIFICATION_MULTIPLIER = 1.15
NIGHT_TO_MORNING_BONUS  = 0.10

# K-Fold: jumlah fold untuk cross validation
N_FOLDS = 5

# RandomizedSearchCV: jumlah kombinasi hyperparameter yang dicoba
N_ITER_SEARCH = 30


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 1: LOAD DATA
# ─────────────────────────────────────────────────────────────────────────────

def load_data(csv_path: str) -> pd.DataFrame:
    """
    Load dataset Employee_Satisfaction_Index.csv ke DataFrame.

    Kolom yang ada di CSV:
      emp_id, age, Dept, location, education, recruitment_type,
      job_level, rating, onsite, awards, certifications, salary, satisfied

    Return: DataFrame mentah (belum diproses)
    """
    print(f"[1/8] Memuat data dari: {csv_path}")
    df = pd.read_csv(csv_path)
    print(f"      → {len(df)} baris, {len(df.columns)} kolom")
    return df


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 2: FEATURE ENGINEERING
# ─────────────────────────────────────────────────────────────────────────────

def feature_engineering(df: pd.DataFrame) -> pd.DataFrame:
    """
    Tambahkan kolom-kolom baru yang penting untuk prediksi salary jadwal.

    Kolom baru yang ditambahkan:
    ----------------------------
    is_nightshift         : bool – apakah baris ini representasi shift malam?
                            Kita simulasikan: pegawai senior (PG) + job_level tinggi
                            lebih sering dijadwalkan malam → probabilistik berdasarkan
                            quartil job_level.
    has_certification     : bool – certifications >= 1
    is_senior             : bool – education == 'PG'
    night_to_morning_flag : bool – simulasi pola malam→pagi (acak berbasis seed
                            agar reproducible). Di produksi, nilainya berasal dari
                            data assignment GA yang sesungguhnya.
    estimated_daily_salary: float – TARGET yang diprediksi model.
                            Dihitung dari salary/22 × multiplier berdasarkan aturan bisnis.

    Kenapa kita perlu feature engineering?
    ---------------------------------------
    Data CSV aslinya hanya punya 'salary' bulanan dan atribut pegawai.
    Model perlu belajar bahwa shift malam dan sertifikasi membuat biaya lebih tinggi.
    Kita MENSIMULASIKAN kolom-kolom ini berdasarkan data yang ada.
    Di produksi, data ini datang langsung dari output GA yang sudah berisi
    informasi shift nyata (lihat candidate_features() di rf_service.py).
    """
    print("[2/8] Feature engineering...")

    df = df.copy()

    # ── Kolom boolean dasar ───────────────────────────────────────────────────
    df["has_certification"] = (df["certifications"] >= 1).astype(int)
    df["is_senior"]         = (df["education"].str.upper() == "PG").astype(int)

    # ── Simulasi is_nightshift ────────────────────────────────────────────────
    # Asumsi: pegawai dengan job_level >= 4 (senior) lebih sering shift malam.
    # Ini representasi distribusi nyata di rumah sakit.
    rng = np.random.default_rng(seed=42)
    job_level_quartile_high = df["job_level"].quantile(0.60)
    prob_night = np.where(df["job_level"] >= job_level_quartile_high, 0.45, 0.20)
    df["is_nightshift"] = (rng.random(len(df)) < prob_night).astype(int)

    # ── Simulasi night_to_morning_flag ────────────────────────────────────────
    # Hanya berlaku jika is_nightshift = 1, dan probabilitas transisi = 30%
    df["night_to_morning_flag"] = 0
    night_mask = df["is_nightshift"] == 1
    df.loc[night_mask, "night_to_morning_flag"] = (
        rng.random(night_mask.sum()) < 0.30
    ).astype(int)

    # ── Target: estimated_daily_salary ───────────────────────────────────────
    # Gaji harian dasar
    daily_base = df["salary"] / WORKING_DAYS_PER_MONTH

    # Terapkan multiplier sesuai aturan bisnis
    multiplier = np.ones(len(df))
    multiplier = np.where(df["is_nightshift"] == 1, multiplier * NIGHT_SHIFT_MULTIPLIER, multiplier)
    multiplier = np.where(df["has_certification"] == 1, multiplier * CERTIFICATION_MULTIPLIER, multiplier)
    multiplier = np.where(df["night_to_morning_flag"] == 1, multiplier * (1 + NIGHT_TO_MORNING_BONUS), multiplier)

    df["estimated_daily_salary"] = (daily_base * multiplier).round(2)

    print(f"      → Kolom baru: is_nightshift, has_certification, is_senior,")
    print(f"        night_to_morning_flag, estimated_daily_salary")
    print(f"      → Target (estimated_daily_salary): min={df['estimated_daily_salary'].min():.0f}, "
          f"max={df['estimated_daily_salary'].max():.0f}, "
          f"mean={df['estimated_daily_salary'].mean():.0f}")

    return df


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 3 & 4: PERSIAPAN FITUR DAN ENCODING
# ─────────────────────────────────────────────────────────────────────────────

def prepare_features(df: pd.DataFrame) -> tuple[pd.DataFrame, pd.Series, list[str]]:
    """
    Pilih kolom fitur (X) dan target (y), lalu encode kolom kategorikal.

    Fitur yang dipakai:
      - age, job_level, rating, certifications, awards, onsite, satisfied
      - is_nightshift, has_certification, is_senior, night_to_morning_flag
      - education (encoded), Dept (encoded), location (encoded), recruitment_type (encoded)

    Kolom yang di-encode: string → angka menggunakan LabelEncoder.
    LabelEncoder mengubah kategori ke angka integer (misal: "PG"→1, "UG"→0).

    Return:
    -------
    X            : DataFrame fitur
    y            : Series target (estimated_daily_salary)
    feature_names: list nama kolom fitur
    """
    print("[3/8] Menyiapkan fitur dan encoding kategorikal...")

    # Kolom kategorikal yang perlu di-encode
    categorical_cols = ["education", "Dept", "location", "recruitment_type"]

    df_encoded = df.copy()
    for col in categorical_cols:
        le = LabelEncoder()
        df_encoded[col + "_enc"] = le.fit_transform(df_encoded[col].astype(str))

    # Daftar fitur akhir yang masuk ke model
    feature_cols = [
        # Atribut numerik pegawai
        "age", "job_level", "rating", "certifications", "awards", "onsite", "satisfied",
        # Fitur rekayasa (engineered features)
        "is_nightshift", "has_certification", "is_senior", "night_to_morning_flag",
        # Atribut kategorikal (sudah di-encode)
        "education_enc", "Dept_enc", "location_enc", "recruitment_type_enc",
    ]

    X = df_encoded[feature_cols]
    y = df_encoded["estimated_daily_salary"]

    print(f"      → {len(feature_cols)} fitur: {feature_cols}")
    return X, y, feature_cols


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 5 & 6: K-FOLD CROSS VALIDATION (MODEL BASELINE)
# ─────────────────────────────────────────────────────────────────────────────

def evaluate_with_kfold(X: pd.DataFrame, y: pd.Series, n_folds: int = 5) -> RandomForestRegressor:
    """
    Training model baseline Random Forest dengan K-Fold Cross Validation.

    K-Fold bekerja seperti ini (contoh k=5):
    -----------------------------------------
    Data: [████ fold1 ████][████ fold2 ████][████ fold3 ████][████ fold4 ████][████ fold5 ████]

    Iterasi 1: Training=[fold2,3,4,5], Testing=[fold1]
    Iterasi 2: Training=[fold1,3,4,5], Testing=[fold2]
    ... dst.

    Lalu rata-ratakan MAE/RMSE/R² dari semua fold.
    Ini lebih fair daripada split satu kali (train/test split).

    Parameter:
    ----------
    n_folds : int
        Jumlah fold. Default 5 → data dibagi 5 bagian (80% train, 20% test per fold).

    Return:
    -------
    model : RandomForestRegressor yang ditraining di seluruh data (setelah evaluasi fold)
    """
    print(f"\n[4/8] K-Fold Cross Validation (k={n_folds}) — Model Baseline...")

    kf = KFold(n_splits=n_folds, shuffle=True, random_state=42)

    # Model baseline dengan hyperparameter default
    baseline_model = RandomForestRegressor(
        n_estimators=100,
        random_state=42,
        n_jobs=-1,   # pakai semua CPU core
    )

    mae_scores  = []
    rmse_scores = []
    r2_scores   = []

    for fold_idx, (train_idx, test_idx) in enumerate(kf.split(X), start=1):
        X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
        y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]

        baseline_model_fold = RandomForestRegressor(n_estimators=100, random_state=42, n_jobs=-1)
        baseline_model_fold.fit(X_train, y_train)
        y_pred = baseline_model_fold.predict(X_test)

        mae  = mean_absolute_error(y_test, y_pred)
        rmse = mean_squared_error(y_test, y_pred) ** 0.5
        r2   = r2_score(y_test, y_pred)

        mae_scores.append(mae)
        rmse_scores.append(rmse)
        r2_scores.append(r2)

        print(f"      Fold {fold_idx}: MAE={mae:.2f} | RMSE={rmse:.2f} | R²={r2:.4f}")

    print(f"\n      ── Rata-rata K-Fold Baseline ──")
    print(f"      MAE  : {np.mean(mae_scores):.2f}  (±{np.std(mae_scores):.2f})")
    print(f"      RMSE : {np.mean(rmse_scores):.2f}  (±{np.std(rmse_scores):.2f})")
    print(f"      R²   : {np.mean(r2_scores):.4f} (±{np.std(r2_scores):.4f})")

    # Training ulang di seluruh data untuk dipakai di langkah berikutnya
    baseline_model.fit(X, y)
    return baseline_model


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 7: HYPERPARAMETER TUNING — RandomizedSearchCV
# ─────────────────────────────────────────────────────────────────────────────

def hyperparameter_tuning(X: pd.DataFrame, y: pd.Series, n_iter: int = 30) -> RandomForestRegressor:
    """
    Cari hyperparameter terbaik menggunakan RandomizedSearchCV.

    Apa itu hyperparameter?
    -----------------------
    Hyperparameter adalah "pengaturan" model yang tidak dipelajari dari data,
    tapi harus kita tentukan sebelum training. Contoh:
      - n_estimators : berapa banyak pohon di dalam Random Forest
      - max_depth    : seberapa dalam setiap pohon boleh tumbuh
      - min_samples_split: minimal berapa data untuk membelah cabang pohon

    RandomizedSearchCV vs GridSearchCV:
    ------------------------------------
    GridSearchCV   : coba SEMUA kombinasi → lambat jika banyak pilihan
    RandomizedSearchCV : coba kombinasi ACAK sebanyak n_iter → lebih cepat,
                         hasil hampir sama bagusnya

    Evaluasi setiap kombinasi menggunakan K-Fold agar fair.

    Parameter:
    ----------
    n_iter : int
        Jumlah kombinasi hyperparameter yang dicoba secara acak.

    Return:
    -------
    best_model : RandomForestRegressor dengan hyperparameter terbaik,
                 sudah ditraining di seluruh data.
    """
    print(f"\n[5/8] Hyperparameter Tuning (RandomizedSearchCV, n_iter={n_iter})...")

    # Ruang pencarian hyperparameter
    param_distributions = {
        "n_estimators":      [50, 100, 150, 200, 300],       # jumlah pohon
        "max_depth":         [None, 5, 10, 15, 20, 30],      # kedalaman pohon (None=unlimited)
        "min_samples_split": [2, 4, 6, 8, 10],               # min sampel untuk split
        "min_samples_leaf":  [1, 2, 4, 6],                   # min sampel di daun
        "max_features":      ["sqrt", "log2", 0.5, 0.7],     # jumlah fitur per split
        "bootstrap":         [True, False],                   # apakah pakai bootstrap sampling
    }

    rf_base = RandomForestRegressor(random_state=42, n_jobs=-1)

    # RandomizedSearchCV: pakai K-Fold internal (cv=5) untuk validasi
    random_search = RandomizedSearchCV(
        estimator=rf_base,
        param_distributions=param_distributions,
        n_iter=n_iter,
        cv=5,
        scoring="neg_mean_absolute_error",  # maksimalkan negatif MAE = minimalisasi MAE
        random_state=42,
        n_jobs=-1,
        verbose=0,
    )

    random_search.fit(X, y)

    best_params = random_search.best_params_
    print(f"      → Hyperparameter terbaik:")
    for key, val in best_params.items():
        print(f"        {key}: {val}")

    best_model = random_search.best_estimator_
    return best_model


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 8: EVALUASI MODEL SETELAH TUNING
# ─────────────────────────────────────────────────────────────────────────────

def evaluate_tuned_model(model: RandomForestRegressor, X: pd.DataFrame, y: pd.Series) -> None:
    """
    Evaluasi ulang model yang sudah di-tuning menggunakan K-Fold.

    Membandingkan apakah tuning benar-benar meningkatkan performa
    dibanding model baseline.

    Metrik yang dihitung:
    ---------------------
    MAE  (Mean Absolute Error)       : rata-rata kesalahan prediksi dalam satuan Rupiah
    RMSE (Root Mean Squared Error)   : seperti MAE tapi lebih sensitif ke error besar
    R²   (Coefficient of Determination): 0 = model buruk, 1 = model sempurna
    """
    print(f"\n[6/8] Evaluasi Model Setelah Tuning (K-Fold k=5)...")

    kf = KFold(n_splits=5, shuffle=True, random_state=42)
    mae_scores, rmse_scores, r2_scores = [], [], []

    for fold_idx, (train_idx, test_idx) in enumerate(kf.split(X), start=1):
        X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
        y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]

        # Clone model dengan hyperparameter terbaik
        tuned_clone = RandomForestRegressor(
            **{k: v for k, v in model.get_params().items()},
        )
        tuned_clone.fit(X_train, y_train)
        y_pred = tuned_clone.predict(X_test)

        mae  = mean_absolute_error(y_test, y_pred)
        rmse = mean_squared_error(y_test, y_pred) ** 0.5
        r2   = r2_score(y_test, y_pred)

        mae_scores.append(mae)
        rmse_scores.append(rmse)
        r2_scores.append(r2)

        print(f"      Fold {fold_idx}: MAE={mae:.2f} | RMSE={rmse:.2f} | R²={r2:.4f}")

    print(f"\n      ── Rata-rata Model Tuned ──")
    print(f"      MAE  : {np.mean(mae_scores):.2f}  (±{np.std(mae_scores):.2f})")
    print(f"      RMSE : {np.mean(rmse_scores):.2f}  (±{np.std(rmse_scores):.2f})")
    print(f"      R²   : {np.mean(r2_scores):.4f} (±{np.std(r2_scores):.4f})")

    # Feature importance — kolom apa yang paling berpengaruh?
    print(f"\n      ── Feature Importance (Top 10) ──")
    importances = pd.Series(model.feature_importances_, index=X.columns)
    top10 = importances.sort_values(ascending=False).head(10)
    for feat, imp in top10.items():
        bar = "█" * int(imp * 40)
        print(f"      {feat:30s} {bar} ({imp:.4f})")


# ─────────────────────────────────────────────────────────────────────────────
# LANGKAH 9: SIMPAN MODEL
# ─────────────────────────────────────────────────────────────────────────────

def save_model(model: RandomForestRegressor, feature_names: list[str]) -> None:
    """
    Simpan model terlatih dan daftar nama fitur ke disk.

    Kenapa perlu simpan feature_names?
    ------------------------------------
    Saat prediksi di rf_service.py, input harus punya kolom yang SAMA PERSIS
    (nama dan urutan) dengan saat training. Menyimpan feature_names memastikan
    konsistensi ini.

    File output:
    ------------
    models/rf_salary_model.joblib   → model Random Forest
    models/rf_feature_names.joblib  → list nama kolom fitur
    """
    os.makedirs(MODEL_DIR, exist_ok=True)
    joblib.dump(model, MODEL_PATH)
    joblib.dump(feature_names, FEATURE_NAMES_PATH)

    size_kb = os.path.getsize(MODEL_PATH) / 1024
    print(f"\n[7/8] Model disimpan:")
    print(f"      → {MODEL_PATH} ({size_kb:.1f} KB)")
    print(f"      → {FEATURE_NAMES_PATH}")


# ─────────────────────────────────────────────────────────────────────────────
# MAIN — Jalankan semua langkah secara berurutan
# ─────────────────────────────────────────────────────────────────────────────

def main() -> None:
    print("=" * 60)
    print("  SHIFTLY — Training Random Forest Salary Predictor")
    print("=" * 60)

    # 1. Load data
    df = load_data(CSV_PATH)

    # 2. Feature engineering
    df = feature_engineering(df)

    # 3 & 4. Persiapan fitur + encoding
    X, y, feature_names = prepare_features(df)

    # 5 & 6. K-Fold baseline
    _ = evaluate_with_kfold(X, y, n_folds=N_FOLDS)

    # 7. Hyperparameter tuning
    best_model = hyperparameter_tuning(X, y, n_iter=N_ITER_SEARCH)

    # 8. Evaluasi setelah tuning
    evaluate_tuned_model(best_model, X, y)

    # 9. Simpan model
    save_model(best_model, feature_names)

    print(f"\n[8/8] ✓ Training selesai! Model siap dipakai oleh rf_service.py")
    print("=" * 60)


if __name__ == "__main__":
    main()
