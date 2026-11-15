# Pendientes de Vicente (ACCIONES TUYAS)

Lo que solo puede hacer Vicente, en orden. Claude Code sigue con otras tareas mientras tanto.
Marca `[x]` cuando lo termines y avísale a Claude Code.

## 1. Confirmar la configuración de Claude Code (F0-0)

El bloqueo de `.env` ya se comprobó en la sesión del 13-11. Falta solo esto:

- [ ] 1. En Claude Code escribe `/memory`. Deben aparecer las 3 reglas de `.claude/rules/`: `asistente-ia.md`, `frontend-ui.md` y `php-backend.md`.
- [ ] 2. Si no aparecen, cierra Claude Code, vuelve a abrirlo en esta carpeta y repite el paso 1. Si siguen sin aparecer, avísale a Claude Code.

## 2. Primer commit y repositorio privado (F0-1)

- [x] Hecho el 14-11 (repo privado en GitHub con `main` y `develop`). Ejecutar los comandos del bloque "COMMIT: F0-1" que entregó Claude Code (incluye crear el repo privado `gmgrepuestos-web` en GitHub y la rama `develop`).

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

## 7. Claves nuevas en producción (F0-2, URGENTE)

Por qué: el repo público de evidencias (`mnunezt14/GmgRepuestos`) tiene `GmgRepuestos.zip` con el `.env` real y un volcado con los hashes de los administradores; cualquiera puede descargarlos.
Hazlo en este orden: el sitio sigue funcionando entre un paso y otro (salvo un minuto en el paso B).

**A. Sacar el `.env` de `public_html`**

Hecho y probado el 14-11 (capturas de Vicente): el `.env` quedó junto a `public_html` y a `DO_NOT_UPLOAD_HERE`; la tienda carga (si el código no lo encontrara, mostraría "El sitio no está disponible"); `/.env` responde 403 Forbidden.
El 403 no indica que quede una copia: Apache responde 403 a cualquier nombre bloqueado aunque el archivo no exista (probado en local con `/.no-existe-xyz`). Para estar seguro, revisa que `public_html` no tenga un `.env` con "Mostrar archivos ocultos" activado.

- [x] 1. hPanel > Sitios web > Administrar (gmgrepuestos.cl) > Archivos > Administrador de archivos.
- [x] 2. Entra por `domains` > `gmgrepuestos.cl` > `public_html`. Si no ves `.env`, activa "Mostrar archivos ocultos" en la configuración del administrador.
- [x] 3. Clic derecho en `.env` > Mover. Destino: `domains/gmgrepuestos.cl/` (la carpeta que contiene a `public_html`). No lo muevas a la carpeta inicial `/home/...`: el código lo busca justo una carpeta arriba de `public_html`.
- [x] 4. Abre https://gmgrepuestos.cl : la tienda debe mostrar productos. Si dice "El sitio no está disponible", devuelve el `.env` a `public_html` (sigue protegido por `.htaccess`) y avísale a Claude Code.
- [x] 5. Abre https://gmgrepuestos.cl/.env : debe responder 404 o 403 (CP-47). Toma captura para la evidencia.

En los pasos siguientes, "editar el `.env`" es: Administrador de archivos > `domains/gmgrepuestos.cl/.env` > clic derecho > Editar > cambiar la línea > Guardar.

**B. Contraseña de la base de datos** (no se rota por ahora: decisión de Vicente del 14-11, no hay datos reales. Se retoma en F5-5, antes de operar con clientes)

- [ ] 1. hPanel > Bases de datos > Bases de datos MySQL. En la base del sitio, menú de tres puntos > Cambiar contraseña. Usa el generador de Hostinger y copia la contraseña.
- [ ] 2. De inmediato, edita el `.env`: `DB_PASS=<nueva>`. Entre el paso 1 y este el sitio no conecta, así que hazlo en menos de un minuto.
- [ ] 3. Recarga la tienda: debe mostrar productos.

**C. Clave de OpenAI (producción)**

- [x] 1. Hecho el 14-11. https://platform.openai.com > el proyecto que usa el sitio > API keys > Create new secret key. Cópiala.
- [x] 2. Hecho el 14-11. Edita el `.env`: `AI_API_KEY=<nueva>`.
- [ ] 3. Inicia sesión en https://gmgrepuestos.cl/admin y, en la misma pestaña del navegador, escribe al chat "pastillas de freno para Swift 2019". Con sesión de administrador la respuesta muestra `[DEBUG]` si hay error. Si dice `HTTP 401`, la clave quedó mal pegada. Cualquier otro error se arregla en la Fase 1; anótalo y pásaselo a Claude Code (es la tarea F1-1).
- [ ] 4. IMPORTANTE: vuelve a API keys y presiona "Revoke" en la clave antigua (la de fecha de creación más antigua). Sin esto la clave filtrada sigue funcionando.

**D. Mercado Pago (el código no se toca, pero el token se filtró)** (no se rota por ahora: decisión del 14-11; se retoma en F5-5)

- [ ] 1. https://www.mercadopago.cl/developers/panel/app > tu aplicación > Credenciales de producción. Usa la opción para renovar el Access Token. Si no aparece, crea una aplicación nueva y usa sus credenciales de producción (si la anterior tenía URL de notificaciones configurada, ponla igual en la nueva).
- [ ] 2. Edita el `.env`: `MP_ACCESS_TOKEN=<nuevo>` y, si cambió, `MP_PUBLIC_KEY=<nueva>`.
- [ ] 3. Prueba: agrega un repuesto al carrito, completa el checkout hasta que se abra la página de Mercado Pago y ciérrala sin pagar.

**E. Contraseña del correo (SMTP)** (no se rota por ahora: decisión del 14-11; se retoma en F5-5. Es la que más conviene adelantar: con ella se puede enviar spam desde el dominio aunque la tienda no tenga datos reales)

- [ ] 1. hPanel > Correos electrónicos > Cuentas de correo > en la cuenta que usa el sitio para enviar (la de `SMTP_USER`, normalmente Ventas@gmgrepuestos.cl) > tres puntos > Cambiar contraseña.
- [ ] 2. Edita el `.env`: `SMTP_PASS=<nueva>`.
- [ ] 3. Cambia también la contraseña en el celular u Outlook donde se lee ese correo. El envío desde el sitio se prueba en F3-9 y F4-6.

**F. Contraseñas de los administradores (rodolfo, vicente, martin)** (no se cambian por ahora: decisión del 14-11; se retoma en F5-5)

Probado en local el 14-11: con el hash nuevo la clave antigua deja de funcionar y la nueva entra al panel.

- [ ] 1. En la terminal de VS Code ejecuta:
  ```powershell
  php -r "echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;"
  ```
  Escribe la contraseña nueva y presiona Enter (se ve en pantalla, pero no queda en el historial). Copia la línea que empieza con `$2y$`.
- [ ] 2. phpMyAdmin de producción > base del sitio > pestaña SQL:
  ```sql
  UPDATE administradores SET password_hash = 'PEGA_AQUI_EL_HASH' WHERE usuario = 'vicente';
  ```
- [ ] 3. Repite 1 y 2 para `martin` y `rodolfo`, cada uno con su propia contraseña. Entrégalas por mensaje privado, no por el grupo.
- [ ] 4. Prueba: entra a https://gmgrepuestos.cl/admin con la contraseña nueva; la antigua debe fallar.

**G. Cierre**

- [ ] 1. Descarga el `.env` nuevo del servidor (clic derecho > Descargar) y reemplaza con él `C:\Users\santi\Documents\gmg-produccion.env`, que hoy tiene las claves antiguas. Nunca lo pongas dentro de la carpeta del proyecto.
- [ ] 2. Avísale a Claude Code: "clave de OpenAI rotada", con el resultado de C.3 y confirmando C.4.

## 8. Repositorio de evidencias `GmgRepuestos` (del equipo, lo revisa el profe)

Decisión de Vicente (14-11): no se reordena. Fases 1 y 2 ya las revisó el profe y están bien; el foco es terminar el desarrollo para la entrega 3. En GitHub no se cambió nada.

- [ ] 1. Borra la carpeta `C:\Users\santi\Desktop\GmgRepuestos-evidencias` (clic derecho > Eliminar). Es un clon local que Claude Code reordenó antes de esta decisión, sin commits ni `push`; GitHub sigue intacto. Claude Code no pudo borrarla porque la regla de seguridad bloquea ese borrado. No hagas `git add` ni `commit` dentro de esa carpeta. Si más adelante necesitas el repo, vuelve a clonarlo en el Escritorio (nunca dentro de `Gmgrepuestos`).
- [ ] 2. Opcional: el repo público sigue teniendo `Fase 2/Evidencias de proyecto/Codigo/GmgRepuestos.zip` (con el `.env` antiguo) y `u118470389_PruebaHpanel.sql` (hashes de los administradores). La clave de OpenAI ya se rotó; el resto se rota en F5-5.

## 9. Ejecutar `BD_v2/05_tablas_panel.sql` en producción (F0-3)

Hecho: la exportación del 14-11 (01:10 hora del servidor) muestra `limites_solicitudes` en producción.

Es seguro: probado en local contra una copia de la estructura de producción. Solo crea la tabla nueva `limites_solicitudes`; las 3 tablas que ya existen y los textos del panel "Asistente" no se tocan.

- [x] 1. phpMyAdmin de producción > selecciona la base del sitio (columna izquierda).
- [x] 2. Pestaña "Importar" > "Seleccionar archivo" > `C:\Users\santi\Desktop\Gmgrepuestos\BD_v2\05_tablas_panel.sql` > botón "Importar" (abajo).
- [x] 3. Debe terminar en verde. Si aparecen avisos de "Table already exists", es lo esperado.
- [x] 4. Pestaña SQL, ejecuta `SHOW TABLES LIKE 'limites_solicitudes';`: debe salir 1 fila.

## 10. Textos reales del asistente (F0-7)

En producción, horario, dirección ("Av. Providencia 1234"), teléfono ("+56 9 1234 5678") y correo ("contacto@gmgrepuestos.cl") son los textos de ejemplo del panel. Hoy el chat y la página Contacto se los dan a los clientes como si fueran reales.

- [ ] 1. Pídele a GMG su horario, dirección, teléfono y correo de contacto reales.
- [ ] 2. https://gmgrepuestos.cl/admin > menú "Asistente" > reemplaza esos 4 campos > Guardar. Si todavía no tienes un dato, deja el campo vacío: así el chat dice que no lo tiene y ofrece WhatsApp, en vez de dar uno falso.
- [ ] 3. Revisa https://gmgrepuestos.cl/contacto.php: debe mostrar los datos nuevos.

## 11. Estructura completa de la base de producción (para cerrar F0-3)

Hecho el 14-11: la única diferencia era `pedidos.metodo_entrega`, que ahora crea `BD_v2/06_metodo_entrega.sql` (en producción no hace falta ejecutarlo).

En producción existe la columna `pedidos.metodo_entrega` (la agregó un script 06 que nunca se guardó en `BD_v2/`) y una base nueva no la tiene. Con la estructura completa Claude Code arma el script que falta y revisa si hay más diferencias. No bloquea: Claude Code sigue con F0-5.

- [x] 1. phpMyAdmin de producción > selecciona la base del sitio > pestaña "Exportar".
- [x] 2. Método: "Personalizado". Formato: SQL.
- [x] 3. En "Tablas", deja todas marcadas, pero solo en la columna "Estructura" (desmarca la columna "Datos"). Así el archivo no trae pedidos, clientes ni contraseñas.
- [x] 4. Botón "Exportar" (abajo). Adjunta el archivo `.sql` en el chat de Claude Code.

## 12. Subir la Fase 0 a Hostinger (F0-4, F0-5 y F0-6)

**Incidente del 14-11 (no repetir):** se subió `subida_fase0.zip` y se usó "Extraer" en el Administrador de archivos. El resultado fue un `public_html` con solo los 11 archivos del zip y el sitio quedó caído (portada 403, login 500). Vicente lo restauró y Claude Code comprobó desde afuera que la tienda volvió (118 productos del Swift, CSS, JS e imágenes en 200), pero con la versión antigua. **No usar "Extraer" para subidas parciales:** los archivos se suben sueltos, carpeta por carpeta. El zip quedó obsoleto; `dist/sitio_completo.zip` (sitio completo desde el PC) queda solo para emergencias.

Archivos listos en `C:\Users\santi\Desktop\Gmgrepuestos\dist\subida_fase0\` (11, idénticos al proyecto). No hay SQL que ejecutar antes.

**Verificado por Claude Code desde afuera (14-11, después de la subida):** tienda, checkout, CSS, JS, imágenes, login y sugerencias en 200 (118 productos del Swift); `/admin/` redirige al login; versión del CSS fija en `login.php` y portada (archivos nuevos); `/backend/config.php`, `entorno.php`, `admin_ui.php`, `parciales/` y `/.htaccess` en 403; `docs/`, `BD_v2/`, `arquitectura/`, `tests/` y `CLAUDE.md` en 404 (no existen en el servidor; Hostinger responde 404 a lo que no existe aunque esté bloqueado, y CP-47 acepta 403 o 404). Vicente confirmó B.4 por captura (`.htaccess` 2.57 KiB y `login.php` 3.14 KiB). Falta C.2 y C.3 (panel con estilos y Códigos con CSRF).

**A. Quitar de producción lo que no debe estar ahí (`docs/`, `BD_v2/`, `arquitectura/`)**

`arquitectura/` hoy se puede abrir desde internet (`/arquitectura/index.html` responde 200). `docs/` y `BD_v2/` están bloqueadas, pero no pertenecen al sitio.

- [x] 1. En `public_html`, marca `docs`, `BD_v2` y `arquitectura` > botón Descargar > formato zip. Guarda el zip en Descargas (no dentro del proyecto): es la copia por si algo de ahí hace falta.
- [x] 2. Con las 3 marcadas > Eliminar.

**B. Subir los archivos sueltos, carpeta por carpeta (nada de zip)**

- [x] 1. En `public_html`: botón Subir > elige `dist\subida_fase0\.htaccess` y `dist\subida_fase0\login.php` (los 2 juntos) > acepta reemplazar.
- [x] 2. Entra a `admin`: Subir > elige los 5 archivos de `dist\subida_fase0\admin\` > acepta reemplazar.
- [x] 3. Entra a `backend`: Subir > elige los 4 archivos de `dist\subida_fase0\backend\` (`.htaccess`, `admin_ui.php`, `entorno.php`, `pagina_publica.php`) **juntos, en una sola subida**: `entorno.php` y `pagina_publica.php` deben quedar de la misma versión.
- [x] 4. (Confirmado por captura el 14-11: `.htaccess` 2.57 KiB y `login.php` 3.14 KiB en `public_html`.) Revisa los tamaños: `.htaccess` 2.57 KiB, `login.php` 3.14 KiB; en `admin`: `avisos.php` 6.29 KiB, `codigos.php` 10.13 KiB, `index.php` 24.77 KiB, `pedidos.php` 16.91 KiB, `productos.php` 21.73 KiB; en `backend`: `.htaccess` 891 B, `admin_ui.php` 3.34 KiB, `entorno.php` 4.28 KiB, `pagina_publica.php` 8.42 KiB.

**C. Probar en el sitio real**

- [x] 1. https://gmgrepuestos.cl : la tienda carga con su diseño normal.
- [ ] 2. https://gmgrepuestos.cl/admin : inicia sesión; el panel se ve con sus estilos.
- [ ] 3. Panel > Códigos > presiona "Desactivar" en BIENVENIDO10 y luego "Activar": las dos veces debe decir "Estado del código actualizado." (si dice "No se pudo comprobar la seguridad del formulario", avísale a Claude Code).
- [x] 4. Escríbele a Claude Code "subido": revisa desde afuera la tienda, la versión de `login.php` y que `/arquitectura/`, `/tests/`, `/docs/`, `/BD_v2/`, `/.git/config` y `/backend/config.php` respondan 403 (CP-47 en producción).

**Si algo falla:** si el sitio no carga después de B.3, vuelve a subir a `backend` los archivos `entorno.php` y `pagina_publica.php` juntos, y avísale a Claude Code.

## 13. Datos reales en producción (F0-7)

- [ ] 1. phpMyAdmin de producción > pestaña SQL:
  ```sql
  SELECT COUNT(*) AS activos, SUM(stock > 0) AS con_stock,
         SUM(codigo_oem IS NOT NULL AND codigo_oem <> '') AS con_oem
    FROM repuestos WHERE activo = 1;
  ```
  Pásale el resultado a Claude Code.
- [ ] 2. Si `con_stock` es 0 o muy bajo: pídele a GMG una planilla con dos columnas, `sku` y `stock` (y `codigo_oem` si lo tienen). Pásasela a Claude Code: arma el script de actualización y lo prueba en local antes.
- [ ] 3. Textos reales del asistente: punto 10.

## 14. Instalar las skills del proyecto (F0-8)

Son extensiones de terceros que pide el plan (Ponytail para el backend, Emil Kowalski para la interfaz). Node.js ya está instalado.

- [ ] 1. Ponytail: en Claude Code escribe `/plugin marketplace add DietrichGebert/ponytail` y luego `/plugin install ponytail@ponytail`; elige alcance "proyecto". Si en VS Code `/plugin` no existe (como pasó con `/add-dir`), abre una terminal en la carpeta del proyecto, escribe `claude`, ejecuta ahí los 2 comandos y sal con `/exit`.
- [ ] 2. Emil Kowalski: en la terminal, en la carpeta del proyecto:
  ```powershell
  npx skills@latest add emilkowalski/skills -a claude-code
  ```
  Elige `emil-design-eng`, `review-animations`, `improve-animations`, `find-animation-opportunities`, `break-ui` y `mobile-native`.
- [ ] 3. Verifica: al escribir `/` deben aparecer `/ponytail-help` y `/emil-design-eng`. Luego haz commit de lo que se agregue en `.claude/` (Claude Code te da el bloque).

## 15. Diagnóstico del chat en producción (F1-1) y prueba de la clave nueva (F0-2, C.3)

Cambio del 15-11: el chat nuevo ya pasó la prueba con la IA real en local (punto 17). Este diagnóstico ahora se hace **después de subir la Fase 1** (punto 17, paso C): sirve para confirmar que en producción responde y que la clave de producción funciona. Si antes de subir quieres ver el error del chat antiguo, puedes hacerlo igual.

- [ ] 1. Inicia sesión en https://gmgrepuestos.cl/admin. En la misma ventana abre la tienda, abre el chat y escribe "pastillas de freno para Swift 2019". Copia la respuesta completa (si hay error, trae una línea `[DEBUG]`).
- [ ] 2. hPanel > Avanzado > Registros de errores: copia las últimas líneas que digan `[GMG Chat]`.
- [ ] 3. phpMyAdmin > SQL:
  ```sql
  SELECT clave, CHAR_LENGTH(contenido) AS largo FROM base_conocimiento;
  ```
- [ ] 4. Pásale a Claude Code lo de 1, 2 y 3, y confirma que revocaste la clave antigua de OpenAI (punto 7, C.4).

## 16. Plan de Pruebas v2 (para completar F1-9 y para F5-1)

- [ ] 1. Busca `Plan_de_Pruebas_GMG_Repuestos_v2.docx` (en Drive, carpeta Capstone 2026) y déjalo en `C:\Users\santi\Desktop\Gmgrepuestos\docs\evidencias\`, o pásale la ruta a Claude Code.
- Con él, Claude Code agrega a `tests/chat_ia_real_test.php` los casos que faltan (EV-05, EV-08, EV-09 y CP-24 a CP-35, salvo CP-28 y CP-29) y registra los resultados con sus códigos oficiales.

## 17. Subir la Fase 1 a Hostinger (asistente IA nuevo)

Probado en local con la IA real el 15-11: 39 de 39 casos correctos, producto correcto en el top 3 en el 100%, 0 precios o SKU inventados, p90 de 3,9 s (`docs/evidencias/resultados_F1-9.md`).
Archivos listos en `C:\Users\santi\Desktop\Gmgrepuestos\dist\subida_fase1\` (9, idénticos al proyecto). **No hay SQL que ejecutar** (`limites_solicitudes` ya existe en producción). Mismo método seguro de la Fase 0: archivos sueltos por carpeta, nunca "Extraer".

**A. Antes de subir: revisar el `.env` de producción** (Administrador de archivos > `domains/gmgrepuestos.cl/.env` > Editar, solo mirar):

- [ ] 1. `AI_API_KEY` tiene la clave nueva de producción.
- [ ] 2. `WHATSAPP_NUMBER` tiene el número real de la tienda (569XXXXXXXX). Si no está, el chat ofrece `wa.me/56900000000`, que no existe. Si falta, agrégalo.
- [ ] 3. `APP_ENV` dice `produccion` (si dice `pruebas`, cámbialo).

**B. Subir (cada carpeta en una sola subida, aceptando reemplazar)**

- [ ] 1. `public_html/backend`: los 6 de `dist\subida_fase1\backend\` **juntos** (`.htaccess`, `bot_config.php`, `chat.php`, `chat_busqueda.php`, `chat_helpers.php`, `seguridad.php`). Van juntos porque se usan entre sí.
- [ ] 2. `public_html/backend/parciales`: `widgets_tienda.php`.
- [ ] 3. `public_html/js`: `chat.js`.
- [ ] 4. `public_html/css`: `chat.css`.
- [ ] 5. Revisa los tamaños: `backend/.htaccess` 919 B, `bot_config.php` 4.19 KiB, `chat.php` 9.44 KiB, `chat_busqueda.php` 12.38 KiB, `chat_helpers.php` 22.79 KiB, `seguridad.php` 4.33 KiB, `parciales/widgets_tienda.php` 12.47 KiB, `js/chat.js` 34.56 KiB, `css/chat.css` 15.39 KiB.

**C. Probar en el sitio real**

- [ ] 1. https://gmgrepuestos.cl carga normal. Recarga con Ctrl+F5 para que el navegador tome el `chat.js` nuevo.
- [ ] 2. Inicia sesión en /admin y, en la misma ventana, escribe al chat "pastillas de freno para Swift 2019". Debe responder con opciones y tarjetas. Si aparece `[DEBUG]`, copia esa línea y pásasela a Claude Code (esto cierra F1-1).
- [ ] 3. Escribe "wena, necesito las balatas pal swift 2019": debe entenderlo y mostrar opciones.
- [ ] 4. Escribe "agrega el segundo": debe agregarlo al carrito.
- [ ] 5. Si las tarjetas salen todas "Sin stock", es el stock real de producción en 0 (punto 13), no un error del chat.
- [ ] 6. Escríbele a Claude Code "subida fase 1": revisa desde afuera que `/backend/chat_busqueda.php` responda 403 y que el sitio siga en pie.

**Si algo falla:** vuelve a subir a `backend` los 6 archivos juntos; si sigue fallando, avísale a Claude Code (el respaldo de `public_html` de la Fase 0 sirve para volver atrás).
