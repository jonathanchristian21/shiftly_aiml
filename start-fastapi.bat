@echo off
echo Starting Shiftly FastAPI Service...
cd /d d:\laragon\www\Projects\shiftly-aiml\shiftly-ai
call .venv\Scripts\activate.bat
echo Virtual environment activated
python -B -m uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
pause
