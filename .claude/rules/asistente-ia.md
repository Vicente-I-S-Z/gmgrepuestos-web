---
paths:
  - "backend/chat*.php"
  - "backend/bot_config.php"
  - "backend/conocimiento.php"
  - "js/chat.js"
  - "css/chat.css"
---

# Reglas del Asistente IA

- La especificación vigente es `docs/FIX_ASISTENTE_IA.md`. La búsqueda y el historial están en `backend/chat_busqueda.php`; la llamada a la API y las rondas de herramientas en `backend/chat_helpers.php`; `gmgLimiteSuperado()` en `backend/seguridad.php`.
- El historial que se envía a la IA se lee de la base (`mensajes_chat` + `mensaje_repuestos`), nunca del navegador.
- Todo dato de producto (precio, stock, compatibilidad, SKU) sale de `buscar_productos`. Nada de catálogos pegados en el prompt.
- `[ADD_TO_CART]` solo acepta IDs que el bot ya mostró en esa conversación.
- Un error de la API nunca se guarda como mensaje del bot.
- Presupuesto de 25 s por mensaje, máximo 2 rondas de herramientas, 1 reintento ante 0, 429 o 5xx.
- `CHAT_DEBUG` siempre en `false` en lo que se sube; para diagnosticar se usa la sesión de administrador.
- Al cambiar la búsqueda, correr `php tests/chat_busqueda_test.php`, `php tests/chat_busqueda_sqlite_test.php`, `php tests/chat_busqueda_mariadb_test.php` y `php tests/chat_herramienta_test.php`; al cambiar `backend/chat.php` o el flujo con la API, `php tests/chat_historial_test.php` y `php tests/chat_api_test.php` (usan la API falsa `tests/fake_openai.php`). Deben terminar en "TODO OK".
- Respuestas al cliente: español de Chile, máximo 3 opciones numeradas, con salida a WhatsApp cuando no hay datos.
