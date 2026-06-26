"""
salary_calculator.py
====================
Modul perhitungan estimasi salary per assignment (per baris jadwal).

Logika bisnis sesuai kriteria:
  1. Shift Malam  → dikalikan 1.2x (regulasi lembur Indonesia)
  2. Sertifikasi  → dikalikan 1.15x (pegawai dengan sertifikasi lebih mahal)
  3. Malam → Pagi (shift malam diikuti pagi keesokan harinya) → tambah bonus insentif 10%

Fungsi utama yang dipakai modul lain:
  - compute_daily_salary(base_salary, shift, has_certification, prev_shift) -> float
  - compute_candidate_salary_features(candidate, employees_by_id) -> dict

Catatan untuk mahasiswa:
  - "base_salary" di sini adalah gaji bulanan dari data employee.
  - Kita konversi ke gaji harian dulu (÷ 22 hari kerja per bulan) sebelum
    menambahkan multiplier.
  - Output fungsi ini dipakai sebagai FITUR untuk Random Forest, bukan
    langsung sebagai gaji final yang dibayarkan.
"""

from __future__ import annotations

from app.schemas import ScheduleCandidate

# ── Konstanta bisnis ──────────────────────────────────────────────────────────

WORKING_DAYS_PER_MONTH = 22          # Asumsi standar hari kerja per bulan
NIGHT_SHIFT_MULTIPLIER  = 1.20       # 20% lebih mahal (regulasi lembur Indonesia)
CERTIFICATION_MULTIPLIER = 1.15      # 15% lebih mahal untuk pegawai bersertifikasi
NIGHT_TO_MORNING_BONUS   = 0.10      # Bonus 10% jika malam → pagi keesokan harinya


def compute_daily_salary(
    base_monthly_salary: float,
    shift: str,
    has_certification: bool,
    prev_shift: str | None = None,
) -> float:
    """
    Hitung estimasi gaji harian untuk satu assignment berdasarkan aturan bisnis.

    Parameter:
    ----------
    base_monthly_salary : float
        Gaji bulanan pegawai (dari kolom 'salary' di tabel employees).
    shift : str
        Shift hari ini: "Pagi", "Sore", "Malam", atau "Libur".
    has_certification : bool
        True jika pegawai punya sertifikasi (certifications >= 1).
    prev_shift : str | None
        Shift hari sebelumnya. Dipakai untuk deteksi pola Malam → Pagi.

    Return:
    -------
    float : estimasi gaji harian setelah semua multiplier diterapkan.

    Contoh:
    -------
    >>> compute_daily_salary(42000, "Malam", True, "Sore")
    # = (42000/22) * 1.20 * 1.15 = 2627.27 ...
    """
    # Shift Libur = tidak ada biaya kerja
    if shift == "Libur":
        return 0.0

    # Gaji harian dasar
    daily = base_monthly_salary / WORKING_DAYS_PER_MONTH

    # Aturan 1: Shift Malam → kalikan 1.2x
    if shift == "Malam":
        daily *= NIGHT_SHIFT_MULTIPLIER

    # Aturan 2: Punya sertifikasi → kalikan 1.15x
    if has_certification:
        daily *= CERTIFICATION_MULTIPLIER

    # Aturan 3: Shift sebelumnya Malam dan sekarang Pagi → tambah bonus 10%
    if prev_shift == "Malam" and shift == "Pagi":
        daily *= (1.0 + NIGHT_TO_MORNING_BONUS)

    return round(daily, 2)


def compute_candidate_salary_features(
    candidate: ScheduleCandidate,
    employees_by_id: dict,
) -> dict:
    """
    Hitung fitur-fitur turunan salary dari satu kandidat jadwal GA.

    Fungsi ini mengiterasi semua assignment dalam kandidat, kemudian
    menghitung total dan breakdown salary dengan memperhitungkan aturan bisnis.

    Parameter:
    ----------
    candidate : ScheduleCandidate
        Satu kandidat jadwal dari output GA.
    employees_by_id : dict
        Dictionary {employee_id: Employee} untuk lookup data pribadi pegawai.

    Return:
    -------
    dict berisi fitur salary yang akan jadi INPUT kolom Random Forest:
        - estimated_total_salary     : total salary semua assignment
        - night_shift_cost           : total biaya khusus shift malam
        - certification_premium_cost : total biaya tambahan akibat sertifikasi
        - night_to_morning_bonus_cost: total biaya bonus malam→pagi
        - avg_daily_salary           : rata-rata gaji harian per assignment aktif
        - night_shift_count          : jumlah assignment shift malam
        - certified_employee_count   : jumlah assignment oleh pegawai bersertifikasi
        - night_to_morning_count     : jumlah pola malam→pagi yang terjadi
    """
    # Kelompokkan assignment per employee agar bisa cek shift berurutan
    from collections import defaultdict
    assignments_per_employee: dict[int, list] = defaultdict(list)
    for assignment in candidate.assignments:
        assignments_per_employee[assignment.employee_id].append(assignment)

    # Urutkan assignment setiap employee berdasarkan tanggal
    for emp_id in assignments_per_employee:
        assignments_per_employee[emp_id].sort(key=lambda a: a.date)

    # Akumulasi fitur
    estimated_total_salary      = 0.0
    night_shift_cost            = 0.0
    certification_premium_cost  = 0.0
    night_to_morning_bonus_cost = 0.0
    night_shift_count           = 0
    certified_employee_count    = 0
    night_to_morning_count      = 0
    total_active_assignments    = 0

    for emp_id, assignments in assignments_per_employee.items():
        # Ambil data asli pegawai (fallback ke salary_snapshot jika tidak ada)
        employee = employees_by_id.get(emp_id)
        if employee is not None:
            base_salary      = float(employee.salary)
            has_certification = int(getattr(employee, "certifications", 0)) >= 1
        else:
            # Fallback: pakai data snapshot dari assignment pertama
            base_salary      = float(assignments[0].salary_snapshot) if assignments else 0.0
            has_certification = bool(assignments[0].is_senior_snapshot) if assignments else False

        daily_base = base_salary / WORKING_DAYS_PER_MONTH

        for i, assignment in enumerate(assignments):
            if assignment.shift == "Libur":
                continue

            prev_shift = assignments[i - 1].shift if i > 0 else None

            # Hitung komponen salary secara terpisah agar bisa dilaporkan
            day_cost = daily_base  # mulai dari gaji dasar harian

            # Komponen malam
            is_night = assignment.shift == "Malam"
            night_extra = 0.0
            if is_night:
                night_extra = daily_base * (NIGHT_SHIFT_MULTIPLIER - 1.0)
                day_cost   += night_extra
                night_shift_count += 1
                night_shift_cost  += (daily_base * NIGHT_SHIFT_MULTIPLIER)

            # Komponen sertifikasi (berlaku di semua shift aktif)
            cert_extra = 0.0
            if has_certification:
                base_for_cert = daily_base * (NIGHT_SHIFT_MULTIPLIER if is_night else 1.0)
                cert_extra = base_for_cert * (CERTIFICATION_MULTIPLIER - 1.0)
                day_cost  += cert_extra
                certified_employee_count        += 1
                certification_premium_cost      += cert_extra

            # Komponen bonus malam → pagi
            nm_bonus = 0.0
            if prev_shift == "Malam" and assignment.shift == "Pagi":
                nm_bonus = day_cost * NIGHT_TO_MORNING_BONUS
                day_cost += nm_bonus
                night_to_morning_count      += 1
                night_to_morning_bonus_cost += nm_bonus

            estimated_total_salary   += day_cost
            total_active_assignments += 1

    avg_daily_salary = (
        estimated_total_salary / total_active_assignments
        if total_active_assignments > 0
        else 0.0
    )

    return {
        "estimated_total_salary":      round(estimated_total_salary, 2),
        "night_shift_cost":            round(night_shift_cost, 2),
        "certification_premium_cost":  round(certification_premium_cost, 2),
        "night_to_morning_bonus_cost": round(night_to_morning_bonus_cost, 2),
        "avg_daily_salary":            round(avg_daily_salary, 2),
        "night_shift_count":           night_shift_count,
        "certified_employee_count":    certified_employee_count,
        "night_to_morning_count":      night_to_morning_count,
    }
