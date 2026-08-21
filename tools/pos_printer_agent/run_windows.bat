@echo off
cd /d %~dp0
if exist .venv\Scripts\python.exe (
  .venv\Scripts\python.exe agent.py --config config.json
) else (
  python agent.py --config config.json
)
