"""Entry point FastAPI untuk service AI Shiftly.

File ini mengekspos endpoint yang dipanggil Laravel:
- /cluster untuk K-Means label employee
- /generate-schedules untuk kandidat jadwal berbasis GA
- /evaluate-candidates untuk skor profit Random Forest
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from app.schemas import (
    ClusterRequest,
    ClusterResponse,
    EvaluateCandidatesRequest,
    EvaluateCandidatesResponse,
    GenerateScheduleRequest,
    GenerateScheduleResponse,
)
from app.services.ga_engine import generate_candidates
from app.services.kmeans_service import cluster_employees
from app.services.rf_service import evaluate_candidates


app = FastAPI(
    title="Shiftly AI Service",
    description="K-Means, Genetic Algorithm, dan Random Forest service untuk Shiftly.",
    version="0.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1:8000", "http://127.0.0.1:8001"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/")
def root() -> dict[str, str]:
    return {"service": "Shiftly AI Service", "status": "running"}


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/cluster", response_model=ClusterResponse)
def cluster(request: ClusterRequest) -> ClusterResponse:
    try:
        n_clusters, clusters = cluster_employees(request.employees, request.n_clusters)
        return ClusterResponse(n_clusters=n_clusters, clusters=clusters)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error


@app.post("/generate-schedules", response_model=GenerateScheduleResponse)
def generate_schedules(request: GenerateScheduleRequest) -> GenerateScheduleResponse:
    try:
        candidates = generate_candidates(request)
        return GenerateScheduleResponse(candidates=candidates)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error


@app.post("/evaluate-candidates", response_model=EvaluateCandidatesResponse)
def evaluate(request: EvaluateCandidatesRequest) -> EvaluateCandidatesResponse:
    try:
        candidates = evaluate_candidates(request.candidates)
        return EvaluateCandidatesResponse(candidates=candidates)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error
