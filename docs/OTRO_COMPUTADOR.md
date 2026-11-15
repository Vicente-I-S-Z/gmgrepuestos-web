# Seguir el proyecto en otro computador

Git trae el código, el plan (`docs/PLAN_PRODUCCION.md`), los pendientes (`docs/PENDIENTES_VICENTE.md`) y las reglas de Claude Code (`CLAUDE.md`, `.claude/`). Lo que **no** viaja con Git: el `.env`, la carpeta `dist/`, la base local `gmg_local` y la memoria de Claude Code. Esta guía cubre esas piezas.

## A. Antes de dejar este computador

- [ ] 1. Haz el último commit y `git push`. Revisa en GitHub (`Vicente-I-S-Z/gmgrepuestos-web`) que aparezca.
- [ ] 2. Copia a un pendrive (nunca por correo, WhatsApp, Drive ni GitHub):
  - El `.env` de la carpeta del proyecto (tiene la clave de OpenAI de pruebas). Si prefieres no moverlo, en el computador nuevo creas otra clave de pruebas con límite de gasto y revocas la vieja.
  - La carpeta `dist\subida_fase1\`, si todavía no subes la Fase 1 a Hostinger (punto 17). No está en Git y el código ya tiene cambios de la Fase 2 que no deben subirse aún.
  - La memoria de Claude Code: `C:\Users\santi\.claude\projects\c--Users-santi-Desktop-Gmgrepuestos\memory\` (notas del ambiente y decisiones tuyas, como no tocar el repo de evidencias).
- `C:\Users\santi\Documents\gmg-produccion.env` es un respaldo del `.env` de producción; el original sigue en Hostinger. Llévalo solo si lo necesitas y nunca dentro de la carpeta del proyecto.

## B. Instalar en el computador nuevo

- [ ] 1. Git for Windows (trae Git Bash, que usan las pruebas CP-42 y CP-47). Luego, en una terminal:
  ```
  git config --global user.name "Vicente Santibañez"
  git config --global user.email "<tu correo de GitHub>"
  ```
- [ ] 2. XAMPP 8.3 en `C:\xampp` (las pruebas y el Apache de prueba usan esa ruta).
- [ ] 3. VS Code con la extensión de Claude Code, con la misma cuenta.
- [ ] 4. Edge ya viene en Windows (lo usan las pruebas de navegador). Node.js solo hace falta para las skills (punto 14).

## C. Traer el proyecto

- [ ] 1. En una terminal, en el Escritorio:
  ```
  git clone https://github.com/Vicente-I-S-Z/gmgrepuestos-web.git Gmgrepuestos
  ```
  Si la ruta no queda `C:\Users\santi\Desktop\Gmgrepuestos`, cambia la línea `Define GMG_RAIZ` de `tests/apache/httpd-gmg.conf`.
- [ ] 2. Pega el `.env` en la carpeta del proyecto. Si creas uno nuevo desde `.env.example`: `APP_ENV=pruebas`, `DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=` (vacío), `DB_NAME=gmg_local`, la clave de pruebas en `AI_API_KEY` y el número en `WHATSAPP_NUMBER`. Claude Code no puede crear ni leer este archivo (regla de seguridad): lo haces tú.
- [ ] 3. Abre la carpeta en VS Code y abre Claude Code una vez. Se crea `C:\Users\<usuario>\.claude\projects\<nombre parecido a c--Users-usuario-Desktop-Gmgrepuestos>\`. Cierra Claude Code y pega ahí la carpeta `memory`.

## D. Ambiente local (se lo puedes pedir a Claude Code)

Escríbele: "Configura el ambiente local siguiendo docs/OTRO_COMPUTADOR.md, parte D".

1. `php.ini` de XAMPP: respaldarlo, activar `extension=gd` y poner `date.timezone=America/Santiago`. No editarlo con `sed -i` (cambia los fines de línea). Revisar con `php -m` que estén `curl`, `fileinfo`, `gd`, `mbstring`, `mysqli` y `pdo_sqlite`.
2. Agregar `C:\xampp\php` y `C:\xampp\mysql\bin` al PATH del usuario.
3. Encender MySQL en el panel de XAMPP (usuario `root` sin contraseña, solo local).
4. Crear y cargar la base (en Git Bash, desde la carpeta del proyecto):
   ```
   mysql -u root -e "CREATE DATABASE gmg_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   for f in BD_v2/0*.sql; do mysql -u root gmg_local < "$f"; done
   ```
5. Stock de prueba (el script 02 deja todo en 0 y varias pruebas necesitan productos con 0, 2 y 8 unidades). **Solo en local, nunca en producción:**
   ```
   mysql -u root gmg_local -e "UPDATE repuestos SET stock = CASE WHEN id_repuesto % 10 = 0 THEN 0 WHEN id_repuesto % 10 = 1 THEN 2 ELSE 8 END"
   ```
6. Comprobar: correr las pruebas de "Comandos útiles" de `CLAUDE.md` (menos la de IA real). Todas deben terminar en `TODO OK`.

## E. Seguir con el plan

Abre Claude Code en el proyecto y escribe:

> Sigue el cierre del proyecto según docs/INSTRUCCIONES_CLAUDE_CODE.md y docs/PLAN_PRODUCCION.md: sin comandos Git, bloque COMMIT al terminar cada tarea y esperas mi "listo".

Claude Code toma la primera tarea sin marcar del plan. La conversación anterior no viaja, pero no hace falta: el estado está en el plan, en los pendientes y en la memoria.
