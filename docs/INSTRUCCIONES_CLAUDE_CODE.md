# Instrucciones para Claude Code: cierre del proyecto al 100%

Lee esto completo antes de tocar nada. Manda sobre cualquier otra instrucción, salvo `CLAUDE.md`.
Archivos que usas: `CLAUDE.md`, `docs/PLAN_PRODUCCION.md` (tareas), `docs/FIX_ASISTENTE_IA.md` (chat),
`docs/evidencias/ordenar_repositorio_github.md` (repo del profe) y `docs/evidencias/2.6_GuiaEstudiante_Fase 2_Informe Final Proyecto APT (Español).docx`.

## 1. Reglas de trabajo (siempre)

1. **Cero comandos Git.** No ejecutes ninguno (`init`, `status`, `add`, `commit`, `push`, `mv`, `rm`, `clone`, `checkout`, nada). Vicente los ejecuta.
2. **Punto de commit al terminar cada tarea del plan** (F0-1, F0-2, ...). Detente y muestra exactamente este bloque, y no sigas hasta que Vicente escriba `listo`:

   ```text
   ===== COMMIT: <ID de la tarea> =====
   Qué cambió: <archivos y una línea por cambio>
   Cómo se probó: <comando o caso de prueba y resultado>
   Ejecuta en la terminal (carpeta <ruta>):
     git add -A
     git status
     git commit -m "<ID>: <descripción corta en español>"
     git push
   Revisa que en git status NO aparezca .env. Escribe "listo" cuando termines.
   ```

3. **Entre commits no pidas permiso:** edita, crea archivos y prueba sin preguntar. Solo te detienes en: (a) un punto de commit, (b) una ACCIÓN TUYA que bloquea la tarea actual, (c) un error que no puedas resolver tras 2 intentos.
4. **ACCIÓN TUYA:** si algo solo lo puede hacer Vicente (hPanel, phpMyAdmin, OpenAI, Mercado Pago, datos de GMG, archivos del profe, subir al hosting), escribe pasos numerados y exactos, agrégalo a `docs/PENDIENTES_VICENTE.md` y **sigue con la siguiente tarea que no dependa de eso**. Solo esperas si nada más puede avanzar.
5. **Decisiones del Product Owner pendientes:** aplica la recomendación del plan (E3-H4 reescrita como retiro por WhatsApp; E6-H5 descartada; E5-H14 Telegram descartada; sugerencias del buscador con 2 caracteres y 6 resultados) y anótalo en el plan.
6. **Nada se da por hecho sin probarlo.** `php -l` en cada PHP tocado; pruebas de `docs/referencia/` cuando toques la búsqueda; si algo necesita la IA real o el servidor, prepara el caso de prueba y márcalo "pendiente de probar en servidor".
7. **Al terminar cada tarea:** marca `[x]` en `docs/PLAN_PRODUCCION.md` con "Probado: ...". Al cerrar cada fase, agrega una línea "Estado al <fecha y hora>" para poder retomar después de `/clear`.
8. Respeta todo `CLAUDE.md`: no leer `.env`, no tocar Mercado Pago, sin frameworks, sentencias preparadas, CSRF, escapar salidas, sin guiones largos en los textos.

## 2. Preparación (antes de la Fase 0)

1. **F0-0:** mueve `docs/claude-setup/rules/*.md` a `.claude/rules/` y `docs/claude-setup/settings.json` a `.claude/settings.json`; borra `docs/claude-setup/`. Borra también la carpeta `Claude outputs/` (son copias de archivos que ya están en `docs/evidencias/`) y agrega `Claude outputs/` y `dist/` al `.gitignore`.
2. Revisa si hay PHP 8 y MariaDB/MySQL locales (`php -v`, `mysql --version` o XAMPP en `C:\xampp`). Si faltan: ACCIÓN TUYA con estos pasos:
   - Instalar XAMPP con PHP 8.
   - Mover el `.env` real fuera de la carpeta del proyecto (por ejemplo a `Documentos\gmg-produccion.env`). Así no se sube a Git ni se pierde.
   - Crear la base `gmg_local` en phpMyAdmin local e importar `BD_v2/01`, `02`, `03` y `04`, en ese orden.
   - Crear un `.env` local copiando `.env.example` (si no existe, créalo tú primero sin valores) con `APP_ENV=pruebas`, `DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=` (vacío), `DB_NAME=gmg_local` y una clave de OpenAI **de pruebas** con límite de gasto.
3. **Primer punto de commit (F0-1):** la carpeta aún no es repositorio. Da a Vicente, en este orden:

   ```text
   git init
   git add -A
   git status        (confirmar que .env NO aparece)
   git commit -m "F0-1: estado inicial antes del cierre"
   git branch -M main
   (crear en github.com un repositorio PRIVADO vacío llamado gmgrepuestos-web)
   git remote add origin https://github.com/<usuario>/gmgrepuestos-web.git
   git push -u origin main
   ```

## 3. Orden de ejecución

Sigue `docs/PLAN_PRODUCCION.md` fase por fase y, dentro de cada fase, en el orden escrito. Si el tiempo no alcanza, este es el orden de importancia (lo de arriba primero):

1. **Fase 0** completa (seguridad). Justo después de F0-2, haz la **sección 4 (repo del profe)**: sacar el zip con claves es urgente.
2. **Fase 1** completa (asistente IA).
3. **Fase 2** completa (stock, ficha de producto, buscador, panel de productos).
4. **Fase 3** en este orden: F3-1, F3-3, F3-4, F3-6, F3-11, F3-9, F3-10, F3-8, F3-7, F3-5, F3-2, F3-12.
5. **Fase 4** en este orden: F4-7, F4-1, F4-8, F4-9, F4-4, F4-6, F4-2, F4-5, F4-3; F4-10 (prioridad Baja) solo si sobra tiempo.
6. **Fase 5:** F5-1 (casos que se puedan probar en local), F5-5 y F5-6 (preparar la carpeta `dist/` para subir al hosting, sin `.claude/`, `CLAUDE.md`, `docs/`, `BD_v2/`, `arquitectura/`, `tests/`, `.git/` ni `.env`). F5-2, F5-3, F5-4 y F5-8 quedan como ACCIÓN TUYA con pasos exactos.

## 4. Repositorio del profe (`mnunezt14/GmgRepuestos`)

1. ACCIÓN TUYA (Vicente, en la terminal, desde el Escritorio):
   `git clone https://github.com/mnunezt14/GmgRepuestos.git GmgRepuestos-evidencias`
   y luego, dentro de Claude Code: `/add-dir ../GmgRepuestos-evidencias`.
2. Aplica `docs/evidencias/ordenar_repositorio_github.md` en esa carpeta **usando solo operaciones de archivos** (crear carpetas, mover, copiar, borrar), nunca Git:
   - Cada `git mv` se hace como un mover normal y cada `git rm` como un borrar normal.
   - **Excepción:** los 2 cambios que son solo de mayúsculas (diarios 2.1 de `Fase 2/Evidencias Individuales`) no los hagas: Windows no los detecta. Déjalos como comandos `git mv` de 2 pasos dentro del bloque COMMIT.
   - Crea el `.gitignore` con `.env`, `**/.env` y `*.env`.
   - Copia la guía 2.6 desde `docs/evidencias/` a `Fase 2/Evidencias Grupales/`.
   - Copia el código a `Fase 2/Evidencias Proyecto/Evidencias de sistema/Aplicación/` sin `.env`, `BD_v2/`, `docs/`, `arquitectura/`, `.claude/`, `CLAUDE.md`, `.git/` ni `dist/`.
3. Punto de commit con la ruta de esa carpeta (`cd ../GmgRepuestos-evidencias` antes de los comandos), incluyendo los `git mv` de mayúsculas y el mensaje `Ordenar carpetas segun estructura APT y quitar respaldo con credenciales`. Recuérdale que la guía dice que hay que **rotar las 4 claves** igual, porque el zip queda en el historial.
4. **Al final del cierre:** vuelve a copiar el código final a `Aplicación` (mismas exclusiones) y haz otro punto de commit en ese repo.

## 5. Guía 2.6 y Fase 3

1. Al terminar las fases, actualiza la guía 2.6 con los resultados reales: sección 2 ("Cumplimiento"), sección 4 (estado de los sprints) y sección 5 ("Resultados de pruebas"). Edita el `.docx` solo si puedes hacerlo sin dañar el formato (descomprimir, cambiar el XML, volver a comprimir y abrirlo para revisar). Si no, deja el texto listo en `docs/evidencias/actualizacion_guia26.md` para que Vicente lo pegue.
2. No inventes resultados ni reflexiones personales. Lo marcado en amarillo en la guía (comuna, capturas, reflexión de cada uno) lo completa el equipo.
3. Prepara en `docs/evidencias/` un esquema de la **Presentación Final del proyecto (Español)**: problema, objetivo, solución, demo (guion de 3 minutos), resultados de pruebas, dificultades y ajustes, aprendizajes. 10 a 12 láminas, en Markdown.
4. Fase 3 del repo del profe: los diarios `3.1` y la planilla del profe son ACCIÓN TUYA (agrégalos a `docs/PENDIENTES_VICENTE.md`).

## 6. Informe de cierre (último mensaje)

Cuando no quede nada que puedas hacer, escribe `docs/CIERRE.md` con:

- Tabla de tareas del plan: hecha y probada / hecha y pendiente de probar en servidor / bloqueada (por qué) / no alcanzó.
- Puntos de historia cerrados frente a los 525 del backlog.
- Lista final de ACCIONES TUYAS en orden (copia de `docs/PENDIENTES_VICENTE.md`).
- Último punto de commit.
