# Pendientes de Vicente (ACCIONES TUYAS)

Lo que solo puede hacer Vicente, en orden. Claude Code sigue con otras tareas mientras tanto.
Marca `[x]` cuando lo termines y avísale a Claude Code.

## 1. Confirmar la configuración de Claude Code (F0-0)

El bloqueo de `.env` ya se comprobó en la sesión del 13-11. Falta solo esto:

- [ ] 1. En Claude Code escribe `/memory`. Deben aparecer las 3 reglas de `.claude/rules/`: `asistente-ia.md`, `frontend-ui.md` y `php-backend.md`.
- [ ] 2. Si no aparecen, cierra Claude Code, vuelve a abrirlo en esta carpeta y repite el paso 1. Si siguen sin aparecer, avísale a Claude Code.

## 2. Primer commit y repositorio privado (F0-1)

- [ ] Ejecutar los comandos del bloque "COMMIT: F0-1" que entregó Claude Code (incluye crear el repo privado `gmgrepuestos-web` en GitHub y la rama `develop`).

## 3. PHP 8 y MariaDB en el computador (preparación)

Hecho por Claude Code el 13-11 (Vicente ya había instalado XAMPP 8.3.36 con MariaDB 10.11.16 en `C:\xampp`):

- [x] 1. XAMPP instalado en `C:\xampp`.
- [x] 2. `C:\xampp\php` y `C:\xampp\mysql\bin` agregados al PATH del usuario. Para que la terminal de VS Code los vea, cierra y vuelve a abrir VS Code.
- [x] 3. `php.ini`: respaldo en `C:\xampp\php\php.ini.antes-gmg`; se activó `extension=gd` (miniaturas de F2-8) y `date.timezone=America/Santiago`. curl, fileinfo, mbstring, mysqli, openssl y pdo_sqlite ya venían activas.
- [x] 4. Probado: los 46 PHP pasan `php -l` y las 2 pruebas de `docs/referencia/` terminan en "TODO OK".

Ojo: Claude Code encendió MariaDB por fuera del panel. Después de reiniciar el PC, ábrelo con el XAMPP Control Panel y presiona "Start" en MySQL. Si el panel dice que el puerto 3306 está ocupado, es porque MariaDB ya está corriendo y no hay que hacer nada.

## 4. Sacar el `.env` real de la carpeta del proyecto (preparación)

- [x] Hecho por Claude Code el 13-11, sin abrir el archivo: quedó en `C:\Users\santi\Documents\gmg-produccion.env` (1669 bytes, igual que antes).
- No lo devuelvas al Escritorio con el nombre `.env`: el código busca primero `C:\Users\santi\Desktop\.env` (una carpeta arriba del proyecto) y el sitio local terminaría conectado a producción.
- Ese archivo es el respaldo de las claves actuales. Igual hay que rotarlas en F0-2.

## 5. Base de datos local `gmg_local` (preparación)

- [x] Hecho por Claude Code el 13-11: base `gmg_local` (`utf8mb4_unicode_ci`) con `BD_v2/01` a `04` importados sin errores. Tiene 17 tablas y 2 vistas, 538 repuestos activos, 14 modelos con foto, 3 administradores y 1 cupón. El stock está en 0 en todos (se resuelve en F0-7).

## 6. `.env` local de pruebas (lo único que falta para el ambiente local)

Claude Code no puede crear ni leer `.env` (lo bloquea la regla de `.claude/settings.json`, a propósito). Dejó el contenido listo.

- [x] 1. Hecho (14-11). Probado sin abrir el archivo: el sitio local se conecta a `gmg_local` (118 productos del Swift, sin errores de PHP). En PowerShell (hazlo antes de reiniciar el PC, porque esa carpeta temporal se puede borrar):
  ```powershell
  Copy-Item "C:\Users\santi\AppData\Local\Temp\claude\76205e18382703bf\7c1d64a6-5932-4371-a7c8-ea406b43bbe3\scratchpad\env-local-pruebas.txt" "C:\Users\santi\Desktop\Gmgrepuestos\.env"
  ```
  Si esa carpeta ya no existe: copia `.env.example` como `.env` y deja `APP_ENV=pruebas`, `SITE_URL=http://localhost:8000`, `DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=` (vacío) y `DB_NAME=gmg_local`.
- [ ] 2. Clave de OpenAI de pruebas: en https://platform.openai.com entra a Settings, Projects, "Create project" con el nombre `gmg-pruebas`. Dentro de ese proyecto, en Limits, fija un presupuesto mensual bajo (por ejemplo 5 USD). Luego en API keys, "Create new secret key" para ese proyecto, ábrela en el `.env` del proyecto y pégala en `AI_API_KEY=`. Si quieres, pon también el número de la tienda en `WHATSAPP_NUMBER=` (569XXXXXXXX).
- [ ] 3. Avísale a Claude Code: "listo .env local". Claude Code levantará `php -S localhost:8000` y probará el sitio con ese archivo, sin leerlo.
