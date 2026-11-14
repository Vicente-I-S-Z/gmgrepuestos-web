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
- [ ] **F0-3 Script de tablas del panel** (INC-12, E5-H9, E4-H4, E10-H7). Crear `BD_v2/05_tablas_panel.sql` con `base_conocimiento`, `intentos_pago` y `auditoria`. Primero pedir a Vicente el `CREATE TABLE` exportado desde phpMyAdmin de producción; si no existen, deducir columnas leyendo `backend/conocimiento.php`, `admin/conocimiento.php`, `backend/pagos.php`, `admin/pagos.php` y `backend/auditoria.php`. Incluir `limites_solicitudes` (ver `docs/FIX_ASISTENTE_IA.md`).
  Listo cuando: una base vacía cargada con 01 a 05 levanta el sitio y el panel sin errores en el log.
- [ ] **F0-4 CSRF en el panel** (INC-04, E10-H2, E6-H1, E6-H4, E6-H7, E6-H10). En `admin/productos.php`, `pedidos.php`, `codigos.php` y `avisos.php`: cargar `backend/admin_ui.php` al inicio, rechazar todo `POST` sin `gmgCsrfValido()` y agregar `gmgCsrfCampo()` a cada `<form method="post">`.
  Listo cuando: CP-42 exitoso (un POST desde otra página no cambia nada).
- [ ] **F0-5 `.htaccess`** (CP-47). Bloquear `arquitectura`, `tests` y `.claude` junto a `BD_v2` y `docs`; quitar `parches_opcionales` y la entrada `arquitectura` de `backend/.htaccess`.
  Línea base medida el 14-11 con Apache local: `/arquitectura/`, `/tests/apache/httpd-gmg.conf`, `/.claude/settings.json`, `/.git/HEAD` y `/.git/config` responden 200. Agregar también `.git` (existe desde F0-1; en el hosting no debería subirse, pero el bloqueo no cuesta nada). Probar con `tests/apache/httpd-gmg.conf`.
- [ ] **F0-6 Caché del CSS del panel** (E10-H8). Reemplazar `?v=<?php echo time(); ?>` (12 lugares en `admin/` y `login.php`) por la función `gmgVersionArchivo()` que ya usan las páginas públicas (`backend/pagina_publica.php`).
- [ ] **F0-7 Datos reales** (EV-*, E2-H6). Con Vicente: confirmar en producción `SELECT COUNT(*), SUM(stock > 0) FROM repuestos WHERE activo = 1;`. Si todo está en 0, cargar stock real (aunque sea con un `UPDATE` por SKU) y los `codigo_oem` que GMG entregue. Cargar textos reales en el panel "Asistente".
- [ ] **F0-8 Skills instaladas** (ver sección "Skills" al final). Verificar con `/ponytail-help` y que `/emil-design-eng` aparezca al escribir `/`.

**Puerta de salida:** CP-42 y CP-47 exitosos, claves rotadas, Git funcionando.

---

## Fase 1: asistente IA (18 a 22-11)

Especificación completa y código probado en `docs/FIX_ASISTENTE_IA.md`.

- [ ] **F1-1 Diagnóstico en producción.** Seguir el "Paso 0" del fix y anotar aquí el error real que muestra `[DEBUG]`.
- [ ] **F1-2 Módulo de búsqueda.** Mover las funciones de `docs/referencia/chat_busqueda_ref.php` a `backend/chat_busqueda.php` (agregarlo a `backend/.htaccess`) y las pruebas a `tests/`.
  Listo cuando: `php tests/chat_busqueda_test.php` y la prueba con SQLite terminan en "TODO OK".
- [ ] **F1-3 Nueva `buscar_productos`** (E5-H3, INC-06, RNF-010, RNF-030). `chatEjecutarBusquedaProductos` usa `chatConsultaBusqueda()` (estricta y luego flexible), trae el detalle de los IDs con `gmgSqlProductos()` y agrega `disponibilidad`. Si el modelo no existe, devuelve un `aviso` con la lista de modelos.
- [ ] **F1-4 Historial desde la base** (E5-H8, E5-H15, INC-05). Leer con `chatHistorialDesdeBD()` antes de guardar el mensaje nuevo; guardar solo respuestas exitosas; `js/chat.js` deja de enviar `historial` (lo sigue usando solo para pintar la conversación).
- [ ] **F1-5 Llamada a la API** (E5-H16, E5-H17, INC-01, RNF-012, RNF-014, RNF-015). Usar `chatLlamarResponsesAPI()` de referencia con presupuesto de 25 s; máximo 2 rondas de herramientas; borrar el respaldo sin herramientas, `AI_SYSTEM_PROMPT_FALLBACK`, la carga del catálogo completo en `chat.php` y `chatFiltrarProductosPorTexto`.
- [ ] **F1-6 Prompt nuevo** (E5-H2, E5-H15, RNF-011, RNF-016). Reemplazar `AI_SYSTEM_PROMPT` por el texto del fix.
- [ ] **F1-7 Carrito desde el chat** (E5-H5, E5-H6, RF-040). `[ADD_TO_CART]` acepta solo IDs de `chatIdsMostrados()` más los de este turno. Tarjetas con disponibilidad y botón "Ver ficha" (enlaza a `producto.php` cuando exista); quitar el `onclick` en línea y usar `data-*` con `addEventListener`.
- [ ] **F1-8 Abuso y archivos** (INC-02, E10-H5, E5-H13, RNF-036). `gmgLimiteSuperado($conexion, 'chat', 15, 60)` además del límite por sesión. Fotos: máximo 3, 4 MB cada una, solo `image/jpeg`, `image/png` o `image/webp` según `finfo`; eliminar la entrada `image_base64`.
- [ ] **F1-9 Pruebas con la IA real.** CP-24 a CP-36 y EV-01 a EV-10, 3 veces cada una. Registrar resultados en el Plan de Pruebas.

**Puerta de salida:** producto correcto entre los 3 primeros en 80% o más del set EV (meta 100%), 0 precios o stock inventados, p90 bajo 15 s.

---

## Fase 2: catálogo, stock y ficha (23 a 29-11)

- [ ] **F2-1 Disponibilidad en la tienda** (E2-H6, E2-H1, RF-013). Tarjetas con "Disponible", "Últimas unidades" (stock 3 o menos) o "Sin stock"; botón de compra deshabilitado con stock 0. Reutilizar `chatDisponibilidad()`.
- [ ] **F2-2 Stock en carrito y checkout** (E3-H2, E3-H6, INC-10, RF-020, RF-024). El carrito no deja superar el stock. En `checkout.php`, antes de crear el pedido, validar stock dentro de la transacción (`SELECT ... FOR UPDATE`). No tocar la parte de Mercado Pago.
  Listo cuando: CP-15 exitoso.
- [ ] **F2-3 Descuento de stock** (E4-H3, RF-033). Al pasar un pedido a "aprobado" en `admin/pedidos.php`, descontar stock una sola vez (idempotente) y auditarlo.
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
  Además (decisión del 14-11 en F0-2): rotar la contraseña de la base, el token de Mercado Pago, la contraseña SMTP y las contraseñas de `rodolfo`, `vicente` y `martin`, que están filtradas en el historial del repo del profe. Pasos en `docs/PENDIENTES_VICENTE.md`, punto 7 (B, D, E y F).
- [ ] **F5-6 Despliegue.** Subir solo lo público (excluir `.claude/`, `CLAUDE.md`, `docs/`, `BD_v2/`, `arquitectura/`, `tests/`, `.git/`). Probar la vuelta atrás.
- [ ] **F5-7 Documentación.** Actualizar estados del Product Backlog, completar Responsable y A/R en las validaciones, actualizar el DAS 4+1 y el manual del panel.
- [ ] **F5-8 UAT** con GMG Repuestos y acta firmada.

---

## Decisiones del Product Owner (antes de la Fase 2)

- [ ] E3-H4: reescribir como "retiro y coordinación por WhatsApp" (GMG no hace envíos) o descartarla.
- [ ] E6-H5: marcarla "Descartada" (pantalla eliminada a propósito) o cubrirla con un filtro de respuestas con valoración negativa.
- [ ] E5-H14 (Telegram, 8 pts, Baja): descartar o mover a "alcance ampliado".
- [ ] Prioridad Baja de la Fase 4 (E1-H6, E1-H7, E6-H11, E6-H12): entran solo si sobra tiempo.
- [ ] Sugerencias del buscador: 2 caracteres y 6 resultados (código y plan de pruebas) o 3 y 8 (SRS).

## Hallazgos fuera de tarea (anotados, no corregidos)

- `backend/entorno.php` línea 72: el mensaje del log cita `docs/procedimiento_de_despliegue.md`, que no existe. Corregir cuando se escriba la guía de despliegue (F5-6).
- Ambiente local listo (13-11): XAMPP 8.3.36 con PHP 8.3 y MariaDB 10.11.16 en `C:\xampp`, base `gmg_local` cargada con 01 a 04. Probado con `php -S localhost:8000`: las 14 páginas y endpoints públicos responden 200 sin errores de PHP y `/admin/` redirige al login. El único error del log es la tabla `base_conocimiento`, que no existe (15 avisos desde `backend/conocimiento.php:46`): confirma INC-12 y se resuelve en F0-3. Falta que Vicente copie el `.env` local (`docs/PENDIENTES_VICENTE.md`, punto 6).
- La regla `deny` de `.env` también impide a Claude Code escribirlo; por eso el `.env` local lo copia Vicente.

## Skills

- Ponytail (plugin): `/plugin marketplace add DietrichGebert/ponytail` y luego `/plugin install ponytail@ponytail`, alcance "proyecto".
- Emil Kowalski (skills): en la terminal de la carpeta del proyecto, `npx skills@latest add emilkowalski/skills -a claude-code`, eligiendo `emil-design-eng`, `review-animations`, `improve-animations`, `find-animation-opportunities`, `break-ui` y `mobile-native`.
