# Guía paso a paso: Hostinger y tareas que hace una persona

Para: quien sube el sitio y cierra lo que el código no puede hacer solo (claves, base de datos, pruebas en el servidor, cliente).
Estado al 15-11-2026: el código está terminado y probado en el computador (44 de 56 tareas del plan; 38 pruebas automáticas sin costo en "TODO OK", más la de la IA real). Lo que queda está aquí, **en el orden en que conviene hacerlo**.

Marca cada casilla al terminar. Si algo no calza con lo que dice la guía, detente y avisa antes de seguir.

---

## 0. Antes de empezar

Necesitas acceso a:

- hPanel de Hostinger (gmgrepuestos.cl): Administrador de archivos, Bases de datos (phpMyAdmin), Correos, Avanzado.
- La carpeta del proyecto en el computador: `C:\Users\santi\Desktop\Gmgrepuestos`.
- Para la parte 7: la cuenta de OpenAI del proyecto, el panel de Mercado Pago y el correo `Ventas@gmgrepuestos.cl`.

Reglas que no se rompen:

1. **Nunca subas un zip para usar "Extraer"** en el Administrador de archivos. El 14-11 eso dejó el sitio caído. Los archivos se suben sueltos, una carpeta a la vez.
2. **El archivo `.env` (claves) nunca se copia, se muestra ni se sube a GitHub.** Solo se edita en el servidor, línea por línea.
3. Haz el respaldo (parte 1) antes de tocar nada.

Tiempo total estimado: 1 hora para las partes 1 a 6; el resto se puede hacer otro día.

---

## 1. Respaldo (10 min)

- [ ] 1. hPanel > Sitios web > gmgrepuestos.cl > Administrador de archivos > entra a `public_html` > selecciona todo > Descargar (zip). Guárdalo como `respaldo_public_html_FECHA.zip` en Descargas (no dentro del proyecto).
- [ ] 2. hPanel > Bases de datos > phpMyAdmin > la base del sitio > pestaña **Exportar** > Rápido > Continuar. Guárdalo como `respaldo_bd_FECHA.sql`.

Con estos dos archivos se puede volver atrás (ver la parte 10).

---

## 2. Cómo debe quedar ordenado Hostinger

```text
domains/gmgrepuestos.cl/
├── .env                 <- claves del sitio (AQUÍ, una carpeta arriba de public_html; nunca dentro)
├── DO_NOT_UPLOAD_HERE   <- archivo de Hostinger, no tocar
└── public_html/         <- SOLO lo que está en dist\subida_final\ del proyecto
    ├── .htaccess, index.php, producto.php, checkout.php, recuperar.php, sitemap.php, robots.txt, ...
    ├── admin/           (12 archivos)
    ├── backend/         (27 archivos, incluye su propio .htaccess)
    │   └── parciales/   (2 archivos)
    ├── css/             (4)
    ├── js/              (5)
    └── img/             (31 fotos de modelos, en JPG y WebP)
        ├── repuestos/   (35 fotos de muestra + las que suba el panel; permiso 755)
        └── modelos/     (fotos de modelos que suba el panel)
```

**No deben estar en `public_html`:** `docs/`, `BD_v2/`, `arquitectura/`, `tests/`, `dist/`, `.claude/`, `.git/`, `CLAUDE.md`, `skills-lock.json` ni ningún `.zip`, `.sql` o `.env`.

- [ ] 1. Revisa `public_html` (con "Mostrar archivos ocultos" activado). Si aparece algo de la lista de arriba, descárgalo como respaldo y bórralo del servidor.
- [ ] 2. Revisa que `.env` esté en `domains/gmgrepuestos.cl/` y **no** dentro de `public_html`.

---

## 3. Base de datos: 3 scripts nuevos (10 min)

Van **antes** de subir los archivos: el código nuevo los necesita. Se pueden repetir sin riesgo si no sabes si ya se ejecutaron.

En phpMyAdmin > la base del sitio > pestaña **Importar** > Seleccionar archivo > Importar, uno por uno y en este orden (los archivos están en `C:\Users\santi\Desktop\Gmgrepuestos\BD_v2\`):

- [ ] 1. `07_stock_descontado.sql` (aprobar un pedido descuenta el stock una sola vez).
- [ ] 2. `08_busquedas_sin_resultado.sql` (registra lo que buscan y no encuentran).
- [ ] 3. `09_cuentas_banners.sql` (correo y rol de los usuarios del panel, restablecer contraseña y banners).
- [ ] 4. Pestaña **SQL**, pega y ejecuta. Deben salir 4 resultados con 1 fila cada uno:

  ```sql
  SHOW COLUMNS FROM pedidos LIKE 'stock_descontado_en';
  SHOW TABLES LIKE 'busquedas_sin_resultado';
  SHOW COLUMNS FROM administradores LIKE 'rol';
  SHOW TABLES LIKE 'banners';
  ```

- [ ] 5. Stock (muy importante para la demo): `SELECT COUNT(*), SUM(stock > 0) FROM repuestos WHERE activo = 1;`
  Si el segundo número es **0**, todo saldrá "Sin stock" y nadie podrá comprar. Se arregla en la parte 6.

---

## 4. Revisar el `.env` del servidor (5 min)

Administrador de archivos > `domains/gmgrepuestos.cl/.env` > clic derecho > **Editar**. Revisa (sin copiar el archivo a ningún lado):

- [ ] `APP_ENV=produccion`
- [ ] `SITE_URL=https://gmgrepuestos.cl` (sin barra al final)
- [ ] `AI_API_KEY=` tiene la clave nueva de OpenAI (la creada el 14-11)
- [ ] `WHATSAPP_NUMBER=569XXXXXXXX` con el celular real de la tienda (también se puede poner después en el panel, parte 6)
- [ ] (Opcional) `GA4_ID=G-XXXXXXX` si GMG ya tiene su ID de Google Analytics. Sin esa línea, Analytics no se carga.

Guarda solo si cambiaste algo.

---

## 5. Subir los archivos (20 min)

Origen: `C:\Users\santi\Desktop\Gmgrepuestos\dist\subida_final\` (133 archivos, ya probados).
En cada carpeta del servidor: botón **Subir** > elige **todos** los archivos de la carpeta equivalente > aceptar **reemplazar**.

- [ ] 1. `public_html/backend`: los **27** archivos de `subida_final\backend\` en una sola subida (incluye `.htaccess`). Van juntos porque se usan entre sí.
- [ ] 2. `public_html/backend/parciales`: los 2.
- [ ] 3. `public_html/admin`: los 12.
- [ ] 4. `public_html/css`: los 4. `public_html/js`: los 5.
- [ ] 5. `public_html/img`: los 31. `public_html/img/repuestos`: los 35.
- [ ] 6. `public_html` (raíz): los **17** archivos sueltos (incluye `.htaccess`, `robots.txt`, `sitemap.php`, `producto.php` y `recuperar.php`).
- [ ] 7. Permisos: clic derecho en `public_html/img/repuestos` > Permisos > **755**. Lo mismo para `public_html/img/modelos` (créala si no existe).

---

## 6. Configurar desde el panel (15 min)

Entra a https://gmgrepuestos.cl/admin (Ctrl+F5 si se ve raro).

- [ ] 1. **Usuarios**: agrega el correo de cada administrador (rodolfo, vicente, martin) y presiona Guardar en cada fila. Sin correo no pueden usar "¿Olvidaste tu contraseña?". Si alguien solo debe ver productos y pedidos, ponle rol "Vendedor".
- [ ] 2. **Asistente**: completa "WhatsApp de la tienda" (+56 9 ...) y, si quieren, el "Mensaje de bienvenida del chat". Revisa que horario, dirección, despacho y medios de pago tengan los datos reales de GMG.
- [ ] 3. **Stock real** (si el paso 3.5 dio 0), elige una opción:
  - Pocos productos: **Productos** > cambia el stock en la fila > Guardar.
  - Muchos: **Reportes** > "Descargar catálogo con stock" > completa la columna Stock en Excel > guarda como CSV (separado por punto y coma) > **Productos** > "Importar CSV" > primero "Solo revisar" y luego "Importar". (El CSV de Reportes ya viene en el formato del importador.)
- [ ] 4. (Opcional) **Banners**: un aviso para la portada, por ejemplo "Llegaron pastillas para Swift".

---

## 7. Tarea programada (5 min)

Borra cada día las conversaciones del chat de más de 12 meses (lo promete la política de privacidad).

- [ ] 1. hPanel > Avanzado > **Trabajos Cron** > Crear: tipo **PHP**, comando `/home/USUARIO/domains/gmgrepuestos.cl/public_html/backend/limpiar_chat.php` (USUARIO es el que aparece en la ruta del Administrador de archivos; si la ruta de tu cuenta es `/home/USUARIO/public_html`, usa esa), frecuencia **una vez al día**, 04:00.
- [ ] 2. Al día siguiente, en el mismo panel de Cron, revisa la salida: debe decir "conversaciones de más de 12 meses borradas: N".

---

## 8. Probar en el sitio real (15 min)

Recarga con Ctrl+F5. Toma captura de cada punto (sirven como evidencia para el informe).

- [ ] 1. https://gmgrepuestos.cl : carga. Entra a un modelo: filtros, paginación de 24 y botón verde de WhatsApp abajo a la izquierda.
- [ ] 2. Clic en el nombre de un repuesto: la dirección queda como `/repuestos/swift/...` y se ve la ficha (fotos, compatibilidad, "Preguntar al asistente").
- [ ] 3. Busca "terminal swift": aparecen los terminales del Swift. Busca "zzzz": aparecen alternativas y WhatsApp.
- [ ] 4. Chat: "pastillas de freno para Swift 2019" (responde con tarjetas) y luego "agrega el segundo" (va al carrito). Si inicias sesión en /admin antes, un error mostraría una línea `[DEBUG]`: cópiala y repórtala (cierra **F1-1**).
- [ ] 5. Carrito: "Finalizar por WhatsApp" abre WhatsApp con los productos y el total. "Pagar con Mercado Pago" abre Mercado Pago (ciérralo sin pagar).
- [ ] 6. https://gmgrepuestos.cl/login.php > "¿Olvidaste tu contraseña?" > tu usuario: debe llegar el correo con el enlace (prueba el SMTP; cierra la parte de F3-2 que no se pudo probar en el computador).
- [ ] 7. Panel > Pedidos > aprueba un pedido de prueba: deben llegar el correo al cliente y el aviso interno "Pedido #N aprobado: coordinar entrega" (F4-6).
- [ ] 8. Seguridad (CP-47): estas direcciones deben responder **403 o 404**, nunca mostrar contenido:
  `https://gmgrepuestos.cl/.env`, `/backend/config.php`, `/backend/fotos.php`, `/BD_v2/`, `/docs/`, `/tests/`, `/.git/config`.
- [ ] 9. https://gmgrepuestos.cl/sitemap.xml muestra la lista de páginas; `/robots.txt` también.
- [ ] 10. Avísale a Claude Code "subido" para que revise todo desde afuera.

---

## 9. Tareas de cierre que hace una persona

### 9.1 Claves filtradas (F0-2) — urgente, 20 min

El repositorio público del profe tuvo un zip con el `.env` real. Detalle completo en `docs/PENDIENTES_VICENTE.md`, punto 7. Resumen:

- [ ] 1. **OpenAI**: platform.openai.com > API keys > **Revoke** en la clave antigua (la de fecha más antigua). La nueva ya está en el `.env`. Prueba el chat después.
- [ ] 2. **Correo (SMTP)**: hPanel > Correos > cuenta `Ventas@...` > Cambiar contraseña > en el `.env`, `SMTP_PASS=<nueva>`. Prueba con el paso 8.6.
- [ ] 3. **Mercado Pago**: panel de desarrolladores > Credenciales de producción > renovar Access Token > en el `.env`, `MP_ACCESS_TOKEN=<nuevo>`. Prueba con el paso 8.5.
- [ ] 4. **Base de datos**: hPanel > Bases de datos > Cambiar contraseña > en menos de un minuto, en el `.env`, `DB_PASS=<nueva>` > recarga la tienda.
- [ ] 5. **Administradores**: ahora es simple: cada uno usa "¿Olvidaste tu contraseña?" (necesita su correo cargado, parte 6.1).
- [ ] 6. **Repositorio del profe** (`mnunezt14/GmgRepuestos`): sacar el zip con claves siguiendo `docs/evidencias/ordenar_repositorio_github.md` (punto 8 de los pendientes).

### 9.2 Producción limpia (F5-5) — 10 min

- [ ] 1. hPanel > Bases de datos > crea un usuario nuevo de MySQL **solo con permisos SELECT, INSERT, UPDATE, DELETE** sobre la base del sitio (sin DROP ni ALTER). Pon ese usuario y su clave en el `.env` (`DB_USER`, `DB_PASS`). Para ejecutar scripts nuevos de `BD_v2` se usa phpMyAdmin con el usuario principal.
- [ ] 2. Confirma `APP_ENV=produccion` (parte 4).
- [ ] 3. Si quedaron pedidos o clientes de prueba, bórralos desde phpMyAdmin (anota los números antes).

### 9.3 Operación (F5-4) — 30 min

- [ ] 1. **Correo que no caiga en spam**: hPanel > Correos > Configuración de DNS / Autenticación: activa **SPF**, **DKIM** y **DMARC** (Hostinger tiene un botón para cada uno). Verifica con https://mxtoolbox.com/emailhealth.
- [ ] 2. **Respaldos**: hPanel > Archivos > Copias de seguridad: confirma que hay copias diarias. Haz una **prueba de restauración** de la base en una base vacía de prueba y anota fecha y resultado (es evidencia).
- [ ] 3. **Monitor de disponibilidad**: crea una cuenta gratis en https://uptimerobot.com con un monitor HTTP a https://gmgrepuestos.cl cada 5 minutos y alerta al correo del equipo.
- [ ] 4. **Capturas**: candado HTTPS del navegador y la tienda abierta en Chrome, Edge, Firefox, Safari (iPhone) y un Android.

### 9.4 Pruebas con la IA real (F1-9 y F1-1) — 30 min

- [ ] 1. En el sitio real, ejecutar los casos CP-24 a CP-35 y EV-05, EV-08, EV-09 del Plan de Pruebas v2 (en Drive: `Plan_de_Pruebas_GMG_Repuestos_v2.docx`), 3 veces cada uno, y anotar el resultado. Claude Code puede automatizarlo en local con `tests/chat_ia_real_test.php --confirmo-gasto` (gasta saldo de OpenAI: pedir permiso antes).

### 9.5 Carga y seguridad (F5-2 y F5-3) — 40 min

Idealmente en un ambiente de pruebas (un subdominio como `pruebas.gmgrepuestos.cl`); si no existe, hazlo de noche en el sitio real y avisa a GMG.

- [ ] 1. **Carga con k6**: instala k6 (https://grafana.com/docs/k6/latest/set-up/install-k6/). En la carpeta del proyecto:
  `k6 run -e SITIO=https://gmgrepuestos.cl tests/k6/carga.js`
  Aprueba si al final `http_req_duration p(95)` es menor a 1500 ms y `http_req_failed` menor a 1 %. Guarda la salida como evidencia. (Las sugerencias tienen un límite de 60 por minuto por IP: verás respuestas vacías, es lo esperado.)
- [ ] 2. **OWASP ZAP** (escaneo base): instala ZAP (https://www.zaproxy.org/download/) > Automated Scan > URL `https://gmgrepuestos.cl` > "Use traditional spider" > Attack. Exporta el informe (Report > Generate HTML). Meta: **ningún hallazgo de riesgo alto (High)**. Los "Medium" o "Low" se anotan; la CSP permite `'unsafe-inline'` a propósito (está documentado en el plan, F3-5).

### 9.6 Cliente (F0-7 y F5-8)

- [ ] 1. Pedir a GMG: stock real, códigos OEM, fotos de sus productos (se suben en Productos > Editar, hasta 3 por repuesto) y el ID de Google Analytics si quieren medir visitas.
- [ ] 2. **UAT**: sesión con GMG usando el sitio real (buscar, comprar con Mercado Pago de prueba, usar el chat, aprobar un pedido en el panel). Anotar observaciones y firmar el **acta de aceptación**.

### 9.7 Documentación (F5-7)

- [ ] 1. Product Backlog: actualizar el estado de las historias según `docs/PLAN_PRODUCCION.md` (cada tarea cita sus historias, por ejemplo E2-H5).
- [ ] 2. Documento 4+1 (`arquitectura/`): agregar las pantallas nuevas (Usuarios, Banners, Reportes, Importar, ficha de producto) y quitar `gmgModelosSugeridos` (ya no existe).
- [ ] 3. Guía 2.6 y presentación: el esquema está en `docs/evidencias/presentacion_final.md`.

---

## 10. Si algo sale mal (vuelta atrás)

- **El sitio no carga después de subir**: vuelve a subir los 27 archivos de `backend` juntos (paso 5.1). Si sigue igual, descomprime en tu computador el zip del paso 1.1 y sube de nuevo sus carpetas, una por una, reemplazando.
- **"El sitio no está disponible"**: el código no encuentra el `.env` o la base. Revisa que el `.env` esté en `domains/gmgrepuestos.cl/` y que `DB_*` sea correcto.
- **El panel de pedidos dice que no puede actualizar el estado**: falta el script `07` (parte 3).
- **"Falta ejecutar el script BD_v2/09"** en Usuarios o Banners: falta el script `09` (parte 3).
- **La base no necesita volver atrás**: los scripts 07, 08 y 09 solo agregan columnas y tablas; la versión anterior del código las ignora. Si igual quieres restaurarla: phpMyAdmin > Importar > `respaldo_bd_FECHA.sql`.

---

## Resumen de qué falta y quién lo hace

| Tarea del plan | Qué es | Parte de esta guía |
| --- | --- | --- |
| F5-6 Despliegue | Subir el código nuevo | 1 a 8 |
| F0-2 Secretos | Revocar y rotar claves filtradas | 9.1 |
| F0-7 Datos reales | Stock, OEM y fotos de GMG | 6.3 y 9.6 |
| F1-1 Diagnóstico | Chat en producción | 8.4 |
| F1-9 IA real | Casos del Plan de Pruebas con la IA | 9.4 |
| F5-5 Producción limpia | Usuario de base con permisos mínimos | 9.2 |
| F5-4 Operación | SPF, DKIM, DMARC, respaldos, monitor, capturas | 9.3 |
| F5-2 Carga | k6 | 9.5 |
| F5-3 Seguridad | OWASP ZAP | 9.5 |
| F5-1 Plan de Pruebas | Correr y registrar los casos Crítica y Alta | 8, 9.4 y 9.5 (más las 38 pruebas automáticas) |
| F5-7 Documentación | Backlog, 4+1, guía 2.6 | 9.7 |
| F5-8 UAT | Acta firmada con GMG | 9.6 |
