# Plan a producción: GMG Repuestos

Origen: auditoría técnica del 13-11-2026 (código local + SRS + Product Backlog + Plan de Pruebas v2).
Meta: cerrar los Sprints 5 a 9 y dejar el sitio listo para producción antes del 18-12-2026.
Fuera de alcance: pasarela Mercado Pago (E4-H1, E4-H2, E4-H5, INC-09, INC-11).

## Cómo usar este plan

- Una tarea por sesión de Claude Code. Marca `[x]` al terminar y agrega una línea "Probado: ...".
- Cada tarea cita las historias (E*-H*), requisitos (RF/RNF), incidentes (INC-*) y casos de prueba (CP-*, EV-*) que cierra.
- "Listo cuando" es el criterio de aceptación. Si no se puede cumplir, deja la tarea abierta y anota por qué.
- Al cerrar cada fase: correr `/ponytail-review` sobre el diff y entregar los comandos Git a Vicente.

## Estado de partida (13-11-2026)

| Bloque | Puntos | Situación |
| --- | --- | --- |
| Sprints 1 a 4 | 146 | 14 historias hechas, 7 en proceso (falta evidencia o acción) |
| Sprints 5 a 9 en código, solo validar | 40 | E1-H3, E1-H4, E3-H1, E3-H5, E5-H6, E5-H10, E5-H11, E7-H2, E3-H9, E3-H10, E6-H6, E8-H9 |
| Sprints 5 a 9 a medias | 195 | Ver fases 1 a 4 |
| Sprints 5 a 9 sin construir | 113 | Ver fases 2 a 5 |
| Fuera de alcance (Mercado Pago) | 21 | E4-H1, E4-H2, E4-H5 |
| Decisión del Product Owner | 10 | E3-H4, E6-H5 |

---

## Fase 0: higiene y seguridad crítica (16 y 17-11)

- [x] **F0-0 Configuración de Claude Code.** Mover `docs/claude-setup/rules/*.md` a `.claude/rules/` y `docs/claude-setup/settings.json` a `.claude/settings.json`; luego borrar `docs/claude-setup/`.
  Listo cuando: `/memory` muestra las 3 reglas y leer `.env` queda bloqueado.
  Probado (13-11): `settings.json` es JSON válido con los 3 `deny`; las 3 reglas tienen su encabezado `paths`; `docs/claude-setup/` y `Claude outputs/` ya no existen (la guía 2.6 era idéntica por MD5 y el `.md` era una versión anterior a la de `docs/evidencias/`); `.gitignore` incluye `Claude outputs/` y `dist/`. Bloqueo de `.env` confirmado: un `find` que solo nombraba la ruta `.env` fue rechazado por la regla `deny` (sin abrir el archivo). Falta que Vicente vea las 3 reglas en `/memory` (`docs/PENDIENTES_VICENTE.md`, punto 1).
- [x] **F0-1 Repositorio Git** (E10-H11, RNF-048). Entregar a Vicente los comandos para `git init`, primer commit y un repo privado NUEVO en GitHub con ramas `main` y `develop`. El repo `mnunezt14/GmgRepuestos` es el de evidencias para el profe (público): no se desarrolla ahí. Agregar `CLAUDE.local.md` y `.claude/settings.local.json` al `.gitignore`.
  Listo cuando: el repo existe, `.env` no aparece en `git status`.
  Probado (14-11, sin ejecutar Git, leyendo `.git/`): repo privado `gmgrepuestos-web` en GitHub con `main` y `develop` locales y remotas; el índice tiene 207 archivos (los mismos contados antes del commit), incluye `.htaccess` y `.env.example`, y no incluye `.env`.
- [ ] **F0-2 Secretos** (E10-H4, INC-03, RNF-007, RNF-034). URGENTE: el repo público `mnunezt14/GmgRepuestos` tiene `GmgRepuestos.zip` con el `.env` real y un volcado SQL con hashes de administradores; sacarlos del repo y rotar las claves. Crear `.env.example` con las mismas claves y valores vacíos. Vicente mueve el `.env` real una carpeta arriba de `public_html` (el código ya lo busca ahí) y rota: contraseña de la BD, token de Mercado Pago, clave de OpenAI y contraseña SMTP.
  Listo cuando: el sitio funciona con claves nuevas y `/.env` responde 403 o 404 (CP-47).
  Avance (13-11): `.env.example` creado en la preparación con las 15 claves que lee el código (`gmgEnv`), sin valores. El `.htaccess` de la raíz ya bloquea `.env` y `.example`.
  Parte local hecha y probada (14-11): `.env` real fuera del proyecto (`Documents\gmg-produccion.env`); 0 claves escritas en el código. CP-47 en local con Apache 2.4.65 real (`tests/apache/httpd-gmg.conf`, 127.0.0.1:8080): un archivo que empieza con punto (misma regla que `.env`), `*.env`, `.env.example`, `.htaccess`, `.gitignore`, `BD_v2/`, `docs/`, `CLAUDE.md` y los PHP internos de `backend/` responden 403; la tienda, el login y las sugerencias responden 200. Rotación de la clave de un administrador probada en local (hash nuevo con `password_hash`, la clave antigua falla y la nueva entra al panel).
  En producción (14-11, capturas de Vicente): `.env` movido a `domains/gmgrepuestos.cl/` (fuera de `public_html`); la tienda sigue cargando y `https://gmgrepuestos.cl/.env` responde 403 (CP-47 exitoso). Apache da 403 a los nombres bloqueados aunque el archivo no exista, así que el 403 no indica que quede una copia.
  Decisión del Product Owner (14-11): por ahora solo se rota la clave de OpenAI (hecho el 14-11 en el `.env` del servidor), porque la base, Mercado Pago y el correo no tienen datos reales. La contraseña de la base, el token de Mercado Pago, la contraseña SMTP y las de los 3 administradores se rotan en F5-5, antes de operar con clientes. Riesgo aceptado: la contraseña SMTP filtrada permite enviar correo desde el dominio.
  Se marca `[x]` cuando Vicente confirme que revocó la clave antigua de OpenAI (C.4) y que el chat responde sin `HTTP 401` (C.3).
  Ojo: `BD_v2/03_datos_iniciales.sql` copia los mismos hashes de administrador que tiene el volcado público; además de las 4 claves, hay que cambiar las contraseñas de `rodolfo`, `vicente` y `martin`.
- [x] **F0-3 Script de tablas del panel** (INC-12, E5-H9, E4-H4, E10-H7). Crear `BD_v2/05_tablas_panel.sql` con `base_conocimiento`, `intentos_pago` y `auditoria`. Primero pedir a Vicente el `CREATE TABLE` exportado desde phpMyAdmin de producción; si no existen, deducir columnas leyendo `backend/conocimiento.php`, `admin/conocimiento.php`, `backend/pagos.php`, `admin/pagos.php` y `backend/auditoria.php`. Incluir `limites_solicitudes` (ver `docs/FIX_ASISTENTE_IA.md`).
  Listo cuando: una base vacía cargada con 01 a 05 levanta el sitio y el panel sin errores en el log.
  Probado (14-11): estructura tomada de la exportación de producción de Vicente. (1) Base nueva con 01 a 05: sin errores, 21 tablas. (2) 05 ejecutado 2 veces: sin errores ni cambios. (3) Base con la estructura exportada de producción: 05 solo crea `limites_solicitudes` y no toca los textos existentes. (4) `SHOW CREATE TABLE` de las 3 tablas idéntico al de producción. (5) Sitio con esa base nueva: 12 páginas públicas y 8 del panel responden 200; los botones rápidos del chat (despacho y pagos) responden con `base_conocimiento`; guardar un texto en el panel escribe en `base_conocimiento` y `auditoria`; el mismo POST sin token CSRF se rechaza; registro de PHP vacío. Aplicado también a `gmg_local`.
  En producción: ejecutar 05 (ACCIÓN TUYA, `docs/PENDIENTES_VICENTE.md` punto 9). Solo agrega `limites_solicitudes`.
  Reabierta (14-11): con pedidos en la base, `admin/pedidos.php:124` deja `Unknown column 'metodo_entrega'` en el log. Esa columna existe en producción (la agregó un `06_sprint1a4_tablas_nuevas.sql` que nunca llegó a `BD_v2/`) y falta en 01 a 05; `checkout.php` y `mi-pedido.php` la toleran, `admin/pedidos.php` solo avisa. Falta: script nuevo con la definición exacta de producción y comparar el esquema completo por si el 06 perdido agregó algo más (ACCIÓN TUYA, `docs/PENDIENTES_VICENTE.md` punto 11). La prueba de F0-4 lo detecta.
  Cerrada (14-11) con `BD_v2/06_metodo_entrega.sql` (definición y posición de producción, `ADD COLUMN IF NOT EXISTS`). Probado: (1) estructura exportada de producción (solo estructura, sin datos) contra una base nueva con todos los scripts de `BD_v2`: columnas, índices, llaves foráneas y vistas idénticos (las diferencias de comentario en las 6 tablas con CHECK son de la exportación de phpMyAdmin, que los omite); (2) 06 ejecutado 2 veces y sobre la estructura de producción: sin errores ni cambios; (3) `bash tests/cp42_csrf_panel.sh` termina en TODO OK (40 de 40, log vacío, con un pedido en la base); (4) `mi-pedido.php` muestra "Entrega: Coordinación por WhatsApp". Aplicado a `gmg_local`. En producción ya estaban 05 (ejecutado por Vicente) y la columna: no hay que ejecutar nada.
- [x] **F0-4 CSRF en el panel** (INC-04, E10-H2, E6-H1, E6-H4, E6-H7, E6-H10). En `admin/productos.php`, `pedidos.php`, `codigos.php` y `avisos.php`: cargar `backend/admin_ui.php` al inicio, rechazar todo `POST` sin `gmgCsrfValido()` y agregar `gmgCsrfCampo()` a cada `<form method="post">`.
  Listo cuando: CP-42 exitoso (un POST desde otra página no cambia nada).
  Probado (14-11) con `bash tests/cp42_csrf_panel.sh` (base temporal desde `BD_v2`, sesión de administrador, 9 acciones de las 6 páginas con POST). Antes del cambio: 13 fallas (crear y borrar códigos, aprobar pedidos, cambiar precios, borrar productos y marcar avisos desde otra página). Después: las 9 acciones rechazan el POST sin token y con token falso, y funcionan con el token del panel (27 de 27); los 55 formularios POST renderizados llevan su campo; las 6 páginas muestran el aviso al rechazar. CP-42 exitoso. Única falla de la prueba: el aviso de `metodo_entrega` (ver F0-3), ajeno a CSRF; con el script 06 la prueba termina en TODO OK.
- [x] **F0-5 `.htaccess`** (CP-47). Bloquear `arquitectura`, `tests` y `.claude` junto a `BD_v2` y `docs`; quitar `parches_opcionales` y la entrada `arquitectura` de `backend/.htaccess`.
  Línea base medida el 14-11 con Apache local: `/arquitectura/`, `/tests/apache/httpd-gmg.conf`, `/.claude/settings.json`, `/.git/HEAD` y `/.git/config` responden 200. Agregar también `.git` (existe desde F0-1; en el hosting no debería subirse, pero el bloqueo no cuesta nada). Probar con `tests/apache/httpd-gmg.conf`.
  Probado (14-11) con `bash tests/cp47_htaccess.sh` (Apache 2.4.65 real con los `.htaccess` del proyecto, 55 rutas). Antes: 10 fallas (`arquitectura/`, `tests/`, `.claude/settings.json`, `.git/HEAD`, `.git/config` y `%2Egit/config` abiertos). Después: TODO OK; archivos ocultos, `*.env`, `CLAUDE.md`, `BD_v2/`, `docs/` (también `Docs/`), `arquitectura/`, `tests/`, `.claude/`, `.git/` y los 16 PHP internos de `backend/` responden 403; tienda, login, CSS, JS, imágenes y los endpoints públicos responden 200; `/admin/` redirige al login y una ruta inexistente da 404. Pendiente de probar en servidor después de subir el `.htaccess` (lista de prueba en el bloque de subida de la Fase 0).
- [x] **F0-6 Caché del CSS del panel** (E10-H8). Reemplazar `?v=<?php echo time(); ?>` (12 lugares en `admin/` y `login.php`) por la función `gmgVersionArchivo()` que ya usan las páginas públicas (`backend/pagina_publica.php`).
  Hecho (14-11): los 12 usos (`admin/codigos.php`, `index.php`, `pedidos.php`, `productos.php`, `backend/admin_ui.php` y `login.php`) usan `gmgVersionArchivo()`. La función se movió de `backend/pagina_publica.php` a `backend/entorno.php`, que `config.php` carga en todas las páginas (así el panel y el login no cargan `modelos.php` ni `conocimiento.php` solo por ella). Probado: `php -l` en los 8 archivos; 0 usos de `time()` como versión; en el login, las 8 pantallas del panel y la portada la versión es el `filemtime` del CSS y no cambia entre visitas; al modificar `admin.css` la versión cambia sola; `tests/cp42_csrf_panel.sh` y `tests/cp47_htaccess.sh` en TODO OK; registro de PHP vacío.
- [ ] **F0-7 Datos reales** (EV-*, E2-H6). Con Vicente: confirmar en producción `SELECT COUNT(*), SUM(stock > 0) FROM repuestos WHERE activo = 1;`. Si todo está en 0, cargar stock real (aunque sea con un `UPDATE` por SKU) y los `codigo_oem` que GMG entregue. Cargar textos reales en el panel "Asistente".
  Visto en la exportación del 14-11: en producción, horario, dirección ("Av. Providencia 1234"), teléfono ("+56 9 1234 5678") y correo son los textos de ejemplo del panel (`gmgTemasConocimiento()`), y el chat y la página Contacto los muestran como reales. Reemplazarlos por los de GMG o dejarlos vacíos (vacío = el chat ofrece WhatsApp).
  Solo en local (15-11): `gmg_local` tiene stock DE PRUEBA para probar el chat (id terminado en 0 = 0 unidades, en 1 = 2, el resto 8; 431 disponibles, 54 últimas unidades, 53 sin stock). No es el stock real. Para volver al estado del script: `UPDATE repuestos SET stock = 0;`.
- [ ] **F0-8 Skills instaladas** (ver sección "Skills" al final). Verificar con `/ponytail-help` y que `/emil-design-eng` aparezca al escribir `/`.

**Puerta de salida:** CP-42 y CP-47 exitosos, claves rotadas, Git funcionando.

**Estado al 14-11-2026 22:30:** código de la Fase 0 terminado y en GitHub (`gmgrepuestos-web`, commits F0-1 a F0-6). Hechas y probadas en local: F0-0, F0-1, F0-3, F0-4 (CP-42 con `tests/cp42_csrf_panel.sh`), F0-5 (CP-47 con `tests/cp47_htaccess.sh`) y F0-6. Puerta de salida: CP-42 y CP-47 exitosos en local; Git funcionando; clave de OpenAI rotada (el resto, por decisión del PO, en F5-5). Abiertas, todas ACCIÓN TUYA en `docs/PENDIENTES_VICENTE.md`: F0-2 (revocar la clave antigua y probar el chat, punto 15), subida a Hostinger de los 11 archivos de `dist/subida_fase0/`, retiro de `docs/`, `BD_v2/` y `arquitectura/` de producción y CP-47 en producción (punto 12), F0-7 datos reales (puntos 10 y 13), F0-8 skills (punto 14). `/ponytail-review` no se pudo correr: la skill aún no está instalada (F0-8). Siguiente: Fase 1 desde F1-2 (F1-1 espera el diagnóstico del punto 15).
Incidente 14-11 (subida de la Fase 0): "Extraer" el zip en el Administrador de archivos dejó `public_html` solo con los 11 archivos y el sitio cayó (portada 403, login 500). Vicente restauró la versión anterior; verificado desde afuera: tienda, CSS, JS, imágenes y login en 200 (118 productos del Swift). Se vio además que producción tenía `docs/`, `BD_v2/` y `arquitectura/` dentro de `public_html` (esta última abierta: `/arquitectura/index.html` en 200). Nuevo método de subida: archivos sueltos por carpeta, nunca "Extraer" para subidas parciales (punto 12). Para F5-6, preparar la subida completa con el mismo cuidado y probar la vuelta atrás.
Fase 0 en producción (14-11, verificado desde afuera después de la segunda subida): sitio completo en 200 (118 productos del Swift), `login.php` y portada con versión de CSS fija (archivos de F0-6 activos), `docs/`, `BD_v2/` y `arquitectura/` retiradas de `public_html`; CP-47 exitoso en producción (rutas internas que existen en 403, las que no existen en 404). `.htaccess` nuevo confirmado en `public_html` (2.57 KiB). Falta que Vicente pruebe Códigos en el panel (CSRF), punto 12 C.3.

Decisión del Product Owner (14-11): la sección 4 de `docs/INSTRUCCIONES_CLAUDE_CODE.md` (ordenar el repo de evidencias `mnunezt14/GmgRepuestos`) queda descartada. El repo es del equipo, el profe ya revisó Fases 1 y 2 y están bien. Prioridad única: completar el desarrollo (Fases 0 a 5 de este plan) para la entrega 3. En GitHub no se cambió nada; el clon local reordenado lo borra Vicente (`docs/PENDIENTES_VICENTE.md`, punto 8).

---

## Fase 1: asistente IA (18 a 22-11)

Especificación completa y código probado en `docs/FIX_ASISTENTE_IA.md`.

- [ ] **F1-1 Diagnóstico en producción.** Seguir el "Paso 0" del fix y anotar aquí el error real que muestra `[DEBUG]`.
  Cambio del 15-11: como el chat nuevo ya está probado con la IA real (F1-9), el diagnóstico se hace después de subir la Fase 1 (`docs/PENDIENTES_VICENTE.md` punto 17, paso C) para confirmar que en producción responde con la clave de producción.
- [x] **F1-2 Módulo de búsqueda.** Mover las funciones de `docs/referencia/chat_busqueda_ref.php` a `backend/chat_busqueda.php` (agregarlo a `backend/.htaccess`) y las pruebas a `tests/`.
  Listo cuando: `php tests/chat_busqueda_test.php` y la prueba con SQLite terminan en "TODO OK".
  Hecho (14-11): 13 funciones (búsqueda, disponibilidad, historial, texto de respuesta, IDs mostrados) en `backend/chat_busqueda.php`, idénticas a la referencia y bloqueadas en `backend/.htaccess`. `gmgLimiteSuperado()` va a `backend/seguridad.php` (la usarán chat, checkout, códigos, avisos y sugerencias en F1-8 y F3-3), con resguardo si falta la tabla (log y deja pasar). `chatLlamarResponsesAPI()` queda en `docs/referencia/` hasta F1-5 porque choca con la versión antigua de `backend/chat_helpers.php`. Pruebas movidas a `tests/` y una nueva contra MariaDB (`tests/chat_busqueda_mariadb_test.php`, en transacción que se deshace), que cierra el "falta repetir en MariaDB" del fix.
  Probado: `chat_busqueda_test.php` TODO OK (39), con SQLite TODO OK (12 de 12 en primer lugar), con MariaDB TODO OK (28: 12 de 12 búsquedas con tildes reales, historial en orden con productos numerados, IDs mostrados por conversación, límite 15/min por acción e IP con hash y reinicio de ventana, `gmg_local` sin cambios); regresión `cp47_htaccess.sh` TODO OK (56, incluye `/backend/chat_busqueda.php` en 403) y `cp42_csrf_panel.sh` TODO OK (40). Rutas de prueba actualizadas en `CLAUDE.md`, `.claude/rules/asistente-ia.md`, `docs/FIX_ASISTENTE_IA.md` e instrucciones. Aún no lo usa el chat (eso es F1-3).
- [x] **F1-3 Nueva `buscar_productos`** (E5-H3, INC-06, RNF-010, RNF-030). `chatEjecutarBusquedaProductos` usa `chatConsultaBusqueda()` (estricta y luego flexible), trae el detalle de los IDs con `gmgSqlProductos()` y agrega `disponibilidad`. Si el modelo no existe, devuelve un `aviso` con la lista de modelos.
  Hecho (14-11) en `backend/chat_helpers.php`: carga `chat_busqueda.php`; `chatEjecutarBusquedaProductos()` según el fix 4.4 (sentencias preparadas, AND y luego OR, orden por relevancia y stock, detalle con `gmgSqlProductos()` en el mismo orden, `disponibilidad` por fila); `chatValidarBusqueda($args, $conexion)` agrega el aviso de modelo inexistente con la lista de `chatModelosCortos()` (y sugiere reintentar con el modelo base, ej. "Swift Sport"); se borró `chatCondicionPalabrasLike()` (armaba SQL con `real_escape_string`); la descripción de la herramienta menciona `disponibilidad` y el `aviso`, y `texto` pide solo la pieza.
  Probado: `php tests/chat_herramienta_test.php` TODO OK (30: validación de datos faltantes y de modelo, 12 búsquedas con el esperado primero y las 13 columnas, disponibilidad según stock, tope de precio, intento de inyección y UNION sin efecto, base sin cambios); botones rápidos de `backend/chat.php` responden con el módulo cargado (log vacío); regresión de las otras 5 pruebas en TODO OK. Pendiente de probar con la IA real (F1-9).
- [x] **F1-4 Historial desde la base** (E5-H8, E5-H15, INC-05). Leer con `chatHistorialDesdeBD()` antes de guardar el mensaje nuevo; guardar solo respuestas exitosas; `js/chat.js` deja de enviar `historial` (lo sigue usando solo para pintar la conversación).
  Hecho (14-11): `backend/chat.php` ya no lee `historial` del formulario ni del JSON; lo lee de la base con `chatHistorialDesdeBD()` antes de guardar el mensaje del cliente; no guarda la respuesta del bot si la IA falló o no hay clave. `chatArmarMensajesBase()` acepta el formato de la base (`role`/`content`, solo `user` y `assistant`). `js/chat.js` dejó de enviar `historial` (sigue en `localStorage` para pintar). Para probar sin gastar tokens: `AI_API_URL` configurable en el `.env` solo con `APP_ENV=pruebas` (en producción siempre OpenAI; verificado) y una API falsa en `tests/fake_openai.php`.
  Probado: `php tests/chat_historial_test.php` TODO OK (16, de punta a punta con `backend/chat.php` y la API falsa: CP-36 historial falso por formulario y por JSON no llega a la IA; historial en orden sin duplicar el mensaje actual; `buscar_productos` real con disponibilidad; CP-29 la respuesta anterior llega con sus productos numerados e id; un error de la IA no se guarda ni aparece después; log vacío). `node --check js/chat.js` y `php -l` sin errores; regresión de las otras 6 pruebas en TODO OK. Pendiente con la IA real: F1-9.
- [x] **F1-5 Llamada a la API** (E5-H16, E5-H17, INC-01, RNF-012, RNF-014, RNF-015). Usar `chatLlamarResponsesAPI()` de referencia con presupuesto de 25 s; máximo 2 rondas de herramientas; borrar el respaldo sin herramientas, `AI_SYSTEM_PROMPT_FALLBACK`, la carga del catálogo completo en `chat.php` y `chatFiltrarProductosPorTexto`.
  Hecho (14 y 15-11): `chatLlamarResponsesAPI()` de la referencia en `backend/chat_helpers.php` (idéntica; `docs/referencia/` quedó vacía y se borró); `chatConsultarIA()` sin respaldo; `chatResolverToolCalls()` iterativo con máximo 2 rondas, reenvía todo el `output`, texto final con `chatTextoRespuesta()` y trata como falla el texto vacío o `status` "incomplete"; borrados `chatErrorSinRespaldo()`, `chatFiltrarProductosPorTexto()`, `chatExtraerTextoDeRespuesta()` y `AI_SYSTEM_PROMPT_FALLBACK`; `backend/chat.php` ya no carga el catálogo y pasa `microtime(true) + 25`. Como `[ADD_TO_CART]` usaba ese catálogo, se adelantó la regla de servidor de F1-7: solo productos de `chatIdsMostrados()` más los de este turno (`chatProductosPorIds()`, ids a entero, solo activos).
  Probado: `php tests/chat_api_test.php` TODO OK (23, con la API falsa): petición con `reasoning` solo `effort: low`, `verbosity: low`, 1500 tokens y la herramienta; ninguna petición sobre 30 KB (la más grande 10 KB); la ronda reenvía `reasoning` y `function_call`; `[ADD_TO_CART]` agrega solo lo mostrado en esa conversación; si la IA insiste se corta en 3 peticiones (2 rondas) con salida a WhatsApp y sin guardar; 500 se reintenta 1 vez sin respaldo; 429 se reintenta y responde; "incomplete" no se muestra a medias; con la API demorando 30 s el cliente recibe respuesta a los 25,6 s. Pruebas del chat ahora comparten `tests/chat_entorno.php`. Regresión: las 8 pruebas en TODO OK (244). Pendiente con la IA real: F1-9.
- [x] **F1-6 Prompt nuevo** (E5-H2, E5-H15, RNF-011, RNF-016). Reemplazar `AI_SYSTEM_PROMPT` por el texto del fix.
  Hecho (15-11) en `backend/bot_config.php`: texto idéntico al del fix 4.5 (comparado línea a línea) más una sección FOTOS de una línea (agregado de Claude Code para no perder E5-H13: identificar la pieza, leer el código grabado y buscarlo en `sku_o_codigo_oem`, o pedir otra foto). Sale la "REGLA CRÍTICA DE CONTEXTO" (causa 6). Se mantiene lo que agrega `gmgContextoNegocioParaIA()`.
  Probado: `php tests/chat_api_test.php` TODO OK (34; el mensaje de sistema que recibe la API incluye el prompt nuevo, la base de conocimiento con sus textos, la regla de modelo y año y la lista de modelos, y no la regla antigua); `chat_historial_test.php` TODO OK. El efecto en las respuestas ("wena", "balatas pal swift") se mide con la IA real en F1-9.
- [x] **F1-7 Carrito desde el chat** (E5-H5, E5-H6, RF-040). `[ADD_TO_CART]` acepta solo IDs de `chatIdsMostrados()` más los de este turno. Tarjetas con disponibilidad y botón "Ver ficha" (enlaza a `producto.php` cuando exista); quitar el `onclick` en línea y usar `data-*` con `addEventListener`.
  La regla de `[ADD_TO_CART]` ya quedó hecha y probada en F1-5 (`tests/chat_api_test.php`).
  Interfaz hecha (15-11) en `js/chat.js` y `css/chat.css`: tarjetas con disponibilidad (la del servidor o, en historiales guardados antes, calculada con el stock), "Añadir" deshabilitado con "Sin stock", "Ver ficha" solo con rutas del sitio (`backend/chat.php` agrega `url_ficha` cuando exista `producto.php`); todo dato escapado (antes `src` iba sin escapar y el `onclick` permitía inyectar código con un apóstrofo en el nombre); un solo listener por `data-accion` y la foto genérica por evento `error` capturado (sin `onclick` ni `onerror` en línea, listo para la CSP de F3-5); botones de 44 px con contraste 4,5:1 y foco visible. También lo que pedía el fix 4.6: `sesion_id` con `crypto.randomUUID()`, `fetch` cortado a los 30 s con aviso y enlace a WhatsApp (`data-whatsapp` en `backend/parciales/widgets_tienda.php`, generado con `gmgEnlaceWhatsApp()`), y animación de escritura que respeta `prefers-reduced-motion`.
  Probado: `php tests/chat_tarjetas_test.php` TODO OK (15, en Edge sin ventana con la tienda real: 6 tarjetas restauradas con su disponibilidad, botones y ficha; sin código en línea; nombre, foto, id y ficha maliciosos sin efecto; el agotado no se agrega; `addToCartFromChat` recibe nombre intacto y precio numérico; con movimiento reducido el texto sale completo; a los 30 s sin respuesta se avisa con el WhatsApp de la tienda). Capturas a 360, 768 y 1366 px revisadas (a 360 el diseño de 2 columnas cortaba el nombre a media palabra; se corrigió pasando los botones a su propia fila). Regresión de las pruebas del chat, CP-42 y CP-47 en TODO OK.
- [x] **F1-8 Abuso y archivos** (INC-02, E10-H5, E5-H13, RNF-036). `gmgLimiteSuperado($conexion, 'chat', 15, 60)` además del límite por sesión. Fotos: máximo 3, 4 MB cada una, solo `image/jpeg`, `image/png` o `image/webp` según `finfo`; eliminar la entrada `image_base64`.
  Hecho (15-11): `backend/chat.php` usa `gmgLimiteSuperado()` con `CHAT_RATE_LIMIT_MAX`/`WINDOW` (15 en 60 s) además del límite por sesión; las fotos pasan por `chatLeerImagenes()` (nueva, en `chat_helpers.php`: `is_uploaded_file`, 4 MB, `finfo` y máximo 3; si una falla se rechaza el mensaje con un aviso claro); un envío que supera `post_max_size` también avisa; se eliminaron `image` e `image_base64`; `message`, `sesion_id` y `atajo` solo como texto (un arreglo rompía la página con error 500) y `message` hasta 1.000 caracteres. En el navegador: `accept` solo JPG, PNG y WebP y aviso antes de enviar si la foto no sirve.
  Probado: `php tests/chat_abuso_test.php` TODO OK (16: JPG, PNG y WebP llegan a la IA con su tipo real; 4 fotos, más de 4 MB, PHP disfrazado de .jpg, GIF y mezcla válida con falsa se rechazan sin llegar a la IA; `image_base64` e `image` se ignoran; `message[]` no rompe; 1.001 caracteres se rechaza y 1.000 con tilde pasa; el envío 16 del minuto se bloquea aunque cambie la sesión; se guarda el hash de la IP); `chat_tarjetas_test.php` TODO OK (19, con el aviso de fotos en Edge); regresión de las 10 pruebas en TODO OK (290).
  Anotado para F3-7: `limites_solicitudes` guarda una fila por IP y no se limpia; borrar las filas viejas en el mismo cron de las conversaciones.
- [ ] **F1-9 Pruebas con la IA real.** CP-24 a CP-36 y EV-01 a EV-10, 3 veces cada una. Registrar resultados en el Plan de Pruebas.
  Avance (15-11, con autorización de Vicente para gastar la clave de pruebas): `php tests/chat_ia_real_test.php --confirmo-gasto` con 13 casos × 3 (EV-01, 02, 03, 04, 06, 07, 10, CP-28, 29, 36 y 3 extra: coloquial, sin modelo ni año, modelo inexistente). **Puerta de salida cumplida:** 39 de 39 correctos, producto correcto en el top 3 en 24 de 24 (100%), 0 precios o SKU inventados, p90 3,9 s (máximo 4,2 s), log vacío. Informe con todas las conversaciones: `docs/evidencias/resultados_F1-9.md`.
  Hallazgo de la primera corrida (también 39 de 39 en lo automático, pero revisado a mano): la IA imitaba la línea interna "[Productos mostrados en esta respuesta: ...]" y el cliente la veía, y usaba guiones largos. Corregido en `chatLimpiarRespuesta()` (servidor) y en el prompt; caso agregado a `tests/chat_api_test.php` (37) y la revisión al script real; segunda corrida sin ninguno de los dos.
  Falta para cerrarla: EV-05, EV-08, EV-09 y CP-24 a CP-35 (salvo CP-28 y CP-29), cuyo texto está en el Plan de Pruebas v2 (punto 16 de los pendientes), y registrar los resultados en ese documento.

**Puerta de salida:** producto correcto entre los 3 primeros en 80% o más del set EV (meta 100%), 0 precios o stock inventados, p90 bajo 15 s.

**Estado al 15-11-2026 01:00:** código de la Fase 1 terminado (F1-2 a F1-8 hechas y probadas) y puerta de salida cumplida con la IA real en local (F1-9: 39 de 39, 100% en top 3, 0 inventados, p90 3,9 s). Pendiente: subir la Fase 1 a Hostinger con `dist/subida_fase1/` (9 archivos, sin SQL; punto 17 de los pendientes), F1-1 después de subir, y completar F1-9 con los casos del Plan de Pruebas v2 (punto 16). Pruebas automáticas del proyecto: 11 en `tests/` (10 sin costo y la de IA real con `--confirmo-gasto`). Siguiente: Fase 2.

---

## Fase 2: catálogo, stock y ficha (23 a 29-11)

- [x] **F2-1 Disponibilidad en la tienda** (E2-H6, E2-H1, RF-013). Tarjetas con "Disponible", "Últimas unidades" (stock 3 o menos) o "Sin stock"; botón de compra deshabilitado con stock 0. Reutilizar `chatDisponibilidad()`.
  Hecho (15-11) en `index.php`: etiqueta con `chatDisponibilidad()` (carga `backend/chat_busqueda.php`), la tarjeta lleva `data-producto-id`, y sin stock queda un solo botón deshabilitado "Sin stock" (sin "Comprar ahora"; F3-11 le sumará el aviso de reposición). `.gmg-disponibilidad` pasó de `css/chat.css` a `css/style.css` (la comparten tienda y chat) y se agregó el estilo deshabilitado de `.btn-add-cart`. Nueva `tests/vista_ancho.html` para ver una página a 360 px reales (Edge sin ventana no baja de unos 500 px y recortaba la captura).
  Probado: `php tests/tienda_disponibilidad_test.php` TODO OK (5: en `?modelo=swift`, `?q=filtro` y `?categoria=Frenos`, las 252 tarjetas tienen la etiqueta que corresponde al stock de la base y sus botones; aparecen los 3 estados; log vacío); capturas a 360 px (en iframe) y 1366 px revisadas; regresión `chat_tarjetas_test` (19) y `cp47_htaccess` (56) en TODO OK.
- [x] **F2-2 Stock en carrito y checkout** (E3-H2, E3-H6, INC-10, RF-020, RF-024). El carrito no deja superar el stock. En `checkout.php`, antes de crear el pedido, validar stock dentro de la transacción (`SELECT ... FOR UPDATE`). No tocar la parte de Mercado Pago.
  Listo cuando: CP-15 exitoso.
  Hecho (15-11): `checkout.php` lee el stock con `FOR UPDATE` al inicio de la transacción del pedido; si no alcanza, deshace y responde qué producto falta y cuántos quedan (`sin_stock`); si la consulta falla, no deja pasar. La parte de Mercado Pago quedó idéntica (comparada contra la copia previa, sin Git). En el navegador: los botones de la tienda y del chat (también `[ADD_TO_CART]`) pasan `stock`; `cart.js` no deja superarlo al agregar, al mover desde "Guardado para después" ni desde el chat, avisa "Solo quedan N unidades", y si el checkout informa `sin_stock` el carrito toma el stock real. Un ítem sin dato de stock (carritos guardados antes) no tiene tope: decide el servidor.
  Probado: `php tests/checkout_stock_test.php` TODO OK (10; CP-15: 6 de 5, sin stock, 3+3 en líneas separadas y mezcla se rechazan sin crear pedido ni cliente y nombrando solo lo que falta; con stock el pedido se registra; bloqueo real: con otra transacción reteniendo la fila el checkout espera 2,1 s y al liberarse ve el stock nuevo y no vende la última unidad dos veces; el sitio de la prueba corre con un proxy inexistente para no llegar a Mercado Pago); `php tests/carrito_stock_test.php` TODO OK (9, en Edge con la tienda real); regresión de las 13 pruebas en TODO OK (317).
  **Antes de subir la Fase 2 a producción:** confirmar el stock real (F0-7, punto 13 de los pendientes). Con stock 0 en producción, F2-1 y F2-2 mostrarían todo "Sin stock" y el checkout rechazaría toda compra.
- [ ] **F2-3 Descuento de stock** (E4-H3, RF-033). Al pasar un pedido a "aprobado" en `admin/pedidos.php`, descontar stock una sola vez (idempotente) y auditarlo.
  Ojo (de F2-2): el pedido pendiente no reserva stock, así que dos pedidos pueden competir por la última unidad. Al aprobar hay que volver a revisar el stock con `FOR UPDATE` y no dejarlo negativo (avisar al administrador en vez de aprobar).
- [ ] **F2-4 Ficha de producto** (E2-H5, RF-012, E2-H11, E8-H1, E8-H4, E8-H5, E8-H7, E8-H9). Crear `producto.php?id=`: fotos, descripción, SKU, compatibilidad, precio, disponibilidad, "Agregar al carrito", "Preguntar al asistente" (abre el chat con el producto como contexto), hasta 4 relacionados, migas de pan, title y description propios, JSON-LD `Product`/`Offer` y `og:image`.
- [ ] **F2-5 Buscador por palabras** (E2-H3, E2-H7, INC-07, RNF-005). `index.php` y `backend/buscar_sugerencias.php` usan las mismas funciones de normalización del chat (todas las palabras, plurales, sinónimos) con sentencias preparadas. Decidir con Vicente si se alinea el SRS (3 caracteres, 8 resultados) o el código (2 y 6).
  Listo cuando: CP-03, CP-04, CP-05 y CP-06 exitosos.
- [ ] **F2-6 Paginación** (E2-H1, RF-008). 24 repuestos por página.
- [ ] **F2-7 Teléfono chileno** (E3-H3, RF-021). Validar `+56 9` en cliente y servidor.
- [ ] **F2-8 Panel de productos** (E6-H2, E6-H3, RF-051, RF-052). Subir hasta 3 imágenes JPG, PNG o WebP de 2 MB con nombre aleatorio y miniatura (reutilizar `gmgCatalogoGuardarFoto` de `admin/catalogo.php`); editar stock y precio desde el listado, con CSRF y auditoría.
  Listo cuando: CP-40, CP-41 y CP-46 exitosos.

**Puerta de salida:** CP-01 a CP-15 (sin los de Mercado Pago) exitosos.

---

## Fase 3: panel, seguridad y chat (30-11 a 04-12)

- [ ] **F3-1 Login seguro** (E1-H1, E1-H2, INC-08, RNF-002, RNF-003). `session_set_cookie_params` (HttpOnly, Secure, SameSite=Lax) en `backend/auth.php`; bloqueo de 15 min tras 5 intentos fallidos por usuario o IP; expiración por 30 min de inactividad; `logout.php` borra la cookie.
  Listo cuando: CP-37 y CP-38 exitosos.
- [ ] **F3-2 Restablecer contraseña** (E1-H5, RF-005). Token de un uso (guardar solo su hash), 30 min de vigencia, correo con enlace.
- [ ] **F3-3 Límites por IP** (E10-H3, RNF-035). `gmgLimiteSuperado` en `validar_codigo.php`, `registrar_aviso_stock.php`, `buscar_sugerencias.php` y `checkout.php`. Turnstile opcional (RNF-041, prioridad baja).
- [ ] **F3-4 SQL preparado** (E10-H2, RNF-030). `index.php`, `admin/pedidos.php`, `backend/relacionados.php`, `backend/datos.php` (`gmgCondicionModelo`).
- [ ] **F3-5 Cabeceras** (RNF-031, RNF-038). CSP básica en `backend/seguridad.php` (revisar que Google Fonts, wa.me y Mercado Pago sigan funcionando).
  Visto en F2-2: `js/cart.js` (`renderCartItems` y `renderGuardados`) arma el HTML con `innerHTML` sin escapar `item.nombre` ni `item.imagen` (vienen del `localStorage`) y usa `onerror` en línea; las fotos de `index.php` también traen `onerror` en línea. Escapar con `escapeHTML()` y pasar esos `onerror` a un listener antes de poner una CSP sin `'unsafe-inline'`.
- [ ] **F3-6 WhatsApp** (E5-H7, E7-H4, E3-H11, RF-042, RF-065, RF-029). Enlace con resumen (modelo, año, repuesto) desde el chat; botón visible en la tienda con mensaje según la página; "Finalizar por WhatsApp" en el carrito con productos y total.
- [ ] **F3-7 Privacidad del chat** (E5-H18, RNF-017). Aviso al abrir el chat; borrar conversaciones de más de 12 meses (consulta que se pueda programar con cron de Hostinger).
- [ ] **F3-8 Configurar el chatbot** (E6-H9, RF-058). Bienvenida y número de WhatsApp editables desde el panel "Asistente".
- [ ] **F3-9 Correo de reposición** (E7-H3, RF-064). Al guardar stock mayor a 0 en un producto con avisos pendientes, enviar correo a los contactos que son correo y marcarlos notificados.
- [ ] **F3-10 Búsquedas sin resultado** (E2-H10, RF-017). Registrar el término en una tabla nueva; mostrar alternativas, chat y WhatsApp.
- [ ] **F3-11 Aviso de reposición en la tienda** (E2-H9, RF-016). Formulario en la tarjeta y la ficha cuando hay stock 0.
- [ ] **F3-12 (Baja) Estado del pedido en el chat** (E5-H12, RF-047). Herramienta `consultar_pedido` con número más correo o RUT; mismo mensaje si no coincide.

**Puerta de salida:** CP-22, CP-23, CP-31, CP-34, CP-37, CP-38 y CP-42 exitosos.

---

## Fase 4: SEO, panel avanzado, rendimiento y accesibilidad (05 a 10-12)

- [ ] **F4-1 sitemap y robots** (E8-H2, RF-067). `sitemap.php` (servido como `sitemap.xml` por `.htaccess`) y `robots.txt` que excluya `/admin`, `/backend` y el checkout.
- [ ] **F4-2 URLs legibles** (E8-H3, RF-068). `/repuestos/{modelo}/{slug}` reescrito a `producto.php`, con 301 desde `producto.php?id=`.
- [ ] **F4-3 Google Analytics 4** (E8-H6, RF-071). Eventos `view_item`, `add_to_cart`, `begin_checkout` y del chat (apertura, búsqueda, WhatsApp). Necesita el ID de medición de GMG.
- [ ] **F4-4 Filtros y orden** (E2-H8, RF-015). Precio, disponibilidad y orden por precio o nombre.
- [ ] **F4-5 Importar CSV** (E6-H8, RF-057). Con el formato de `BD_v2/catalogo_repuestos.csv`; informe de filas con error; todo en una transacción.
- [ ] **F4-6 Correos de pedido** (E3-H8, E7-H1, RF-026, RF-062). Correo al cliente y aviso interno cuando el pedido pasa a aprobado (no al crearse pendiente). Enviar el aviso de pedido nuevo después de responder al navegador.
- [ ] **F4-7 Rendimiento** (E10-H8, RNF-020, RNF-043). Fuente con `<link rel="preconnect">` y 3 grosores; fotos de modelos en WebP; `loading="lazy"`; compresión y caché en `.htaccess`; listado del catálogo sin 6 subconsultas por fila.
  Listo cuando: Lighthouse móvil con LCP de 2,5 s o menos en portada y catálogo.
- [ ] **F4-8 Accesibilidad** (E9-H5, RNF-026). Botón del chat como `<button>`; foco visible; contraste 4,5:1; etiquetas en formularios; animación de escritura del chat respeta `prefers-reduced-motion`.
  Ya hecho en F1-7: la animación de escritura respeta `prefers-reduced-motion` (probado en `tests/chat_tarjetas_test.php`).
- [ ] **F4-9 Datos personales** (E10-H6, RNF-037). Canal concreto para pedir acceso, rectificación o eliminación (correo o formulario) en la política y en el checkout.
- [ ] **F4-10 (Baja, solo si sobra tiempo)** E1-H6 cuentas, E1-H7 roles, E6-H11 banners, E6-H12 reportes CSV. E5-H14 Telegram: proponer descartarla.

**Puerta de salida:** CP-49 y CP-50 exitosos; Lighthouse dentro de meta.

---

## Fase 5: pruebas, operación y entrega (11 a 18-12)

- [ ] **F5-1 Plan de Pruebas v2 completo.** 100% de los casos Crítica y Alta exitosos (sin los de Mercado Pago); valores límite VL-01 a VL-21.
- [ ] **F5-2 Carga** con Grafana k6 en el ambiente de pruebas (sección 6 del plan de pruebas).
- [ ] **F5-3 OWASP ZAP** (escaneo base) sin hallazgos de riesgo alto.
- [ ] **F5-4 Operación** (E7-H5, E10-H9, E10-H10, E10-H1, E9-H2). SPF, DKIM y DMARC; respaldo diario y prueba de restauración documentada; monitor de disponibilidad; capturas de HTTPS y de los 5 navegadores.
- [ ] **F5-5 Producción limpia.** Borrar datos DEMO; usuario de BD con permisos mínimos; `APP_ENV=produccion`; `CHAT_DEBUG` en `false`.
  Además (decisión del 14-11 en F0-2): rotar la contraseña de la base, el token de Mercado Pago, la contraseña SMTP y las contraseñas de `rodolfo`, `vicente` y `martin`, que están publicadas en el repo de evidencias (zip y volcado de Fase 2). Pasos en `docs/PENDIENTES_VICENTE.md`, punto 7 (B, D, E y F).
- [ ] **F5-6 Despliegue.** Subir solo lo público (excluir `.claude/`, `CLAUDE.md`, `docs/`, `BD_v2/`, `arquitectura/`, `tests/`, `.git/`). Probar la vuelta atrás.
- [ ] **F5-7 Documentación.** Actualizar estados del Product Backlog, completar Responsable y A/R en las validaciones, actualizar el DAS 4+1 y el manual del panel.
- [ ] **F5-8 UAT** con GMG Repuestos y acta firmada.

---

## Decisiones del Product Owner (antes de la Fase 2)

Aplicadas el 14-11 con la recomendación del plan (`docs/INSTRUCCIONES_CLAUDE_CODE.md` regla 5; Vicente no objetó al presentárselas):

- [x] E3-H4: reescribir como "retiro y coordinación por WhatsApp" (GMG no hace envíos) o descartarla. **Decisión: se reescribe** como retiro y coordinación por WhatsApp.
- [x] E6-H5: marcarla "Descartada" (pantalla eliminada a propósito) o cubrirla con un filtro de respuestas con valoración negativa. **Decisión: Descartada.**
- [x] E5-H14 (Telegram, 8 pts, Baja): descartar o mover a "alcance ampliado". **Decisión: Descartada.**
- [x] Prioridad Baja de la Fase 4 (E1-H6, E1-H7, E6-H11, E6-H12): entran solo si sobra tiempo. **Decisión: así se aplica (F4-10).**
- [x] Sugerencias del buscador: 2 caracteres y 6 resultados (código y plan de pruebas) o 3 y 8 (SRS). **Decisión: 2 caracteres y 6 resultados**; el SRS se alinea en F5-7.

## Hallazgos fuera de tarea (anotados, no corregidos)

- `backend/entorno.php` línea 72: el mensaje del log cita `docs/procedimiento_de_despliegue.md`, que no existe. Corregir cuando se escriba la guía de despliegue (F5-6).
- `backend/auditoria.php` (comentario y línea 55) y `backend/pagos.php` (líneas 11, 124 y 139) mandan a ejecutar `BD_v2/06_sprint1a4_tablas_nuevas.sql` y un "script 07" (candado opcional de `auditoria`), que no existen: esas tablas ahora están en `05_tablas_panel.sql`. Cambiar el texto en `auditoria.php` la próxima vez que se toque; `pagos.php` solo si Mercado Pago entra al alcance. No se sabe si producción tiene el candado (trigger); se puede revisar con `SHOW TRIGGERS LIKE 'auditoria';`.
- Ambiente local listo (13-11): XAMPP 8.3.36 con PHP 8.3 y MariaDB 10.11.16 en `C:\xampp`, base `gmg_local` cargada con 01 a 04. Probado con `php -S localhost:8000`: las 14 páginas y endpoints públicos responden 200 sin errores de PHP y `/admin/` redirige al login. El único error del log es la tabla `base_conocimiento`, que no existe (15 avisos desde `backend/conocimiento.php:46`): confirma INC-12 y se resuelve en F0-3. Falta que Vicente copie el `.env` local (`docs/PENDIENTES_VICENTE.md`, punto 6).
- La regla `deny` de `.env` también impide a Claude Code escribirlo; por eso el `.env` local lo copia Vicente.

## Skills

- Ponytail (plugin): `/plugin marketplace add DietrichGebert/ponytail` y luego `/plugin install ponytail@ponytail`, alcance "proyecto".
- Emil Kowalski (skills): en la terminal de la carpeta del proyecto, `npx skills@latest add emilkowalski/skills -a claude-code`, eligiendo `emil-design-eng`, `review-animations`, `improve-animations`, `find-animation-opportunities`, `break-ui` y `mobile-native`.
