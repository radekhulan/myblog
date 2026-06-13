@echo off
setlocal

:: Kontrola, zda byl zadán komentáø jako argument
set "msg=%~1"

:: Pokud ne, vyžádá si ho od uživatele
if "%msg%"=="" (
    set /p msg="Zadejte komentar ke commitu: "
)

:: Pokud je komentáø stále prázdný, ukonèí se
if "%msg%"=="" (
    echo Chyba: Komentar nesmi byt prazdny.
    exit /b 1
)

echo ^>^>^> Pridavam soubory...
git add .

echo ^>^>^> Provadim commit s popisem: "%msg%"
git commit -m "%msg%"

echo ^>^>^> Pushuji do origin main...
git push origin main

echo ^>^>^> Pushuji do production main (deploy)...
git push production main

echo.
echo ^>^>^> Hotovo!
