---
paths:
  - "backend/chat*.php"
  - "backend/bot_config.php"
  - "backend/conocimiento.php"
  - "js/chat.js"
  - "css/chat.css"
---

# Reglas del Asistente IA

- La especificación vigente es `docs/FIX_ASISTENTE_IA.md`; el código de referencia probado está en `docs/referencia/`.
- El historial que se envía a la IA se lee de la base (`mensajes_chat` + `mensaje_repuestos`), nunca del navegador.
- Todo dato de producto (precio, stock, compatibilidad, SKU) sale de `buscar_productos`. Nada de catálogos pegados en el prompt.
- `[ADD_TO_CART]` solo acepta IDs que el bot ya mostró en esa conversación.
- Un error de la API nunca se guarda como mensaje del bot.
- Presupuesto de 25 s por mensaje, máximo 2 rondas de herramientas, 1 reintento ante 0, 429 o 5xx.
- `CHAT_DEBUG` siempre en `false` en lo que se sube; para diagnosticar se usa la sesión de administrador.
- Al cambiar la búsqueda, correr `php docs/referencia/chat_busqueda_test.php` y la prueba con SQLite; deben terminar en "TODO OK".
- Respuestas al cliente: español de Chile, máximo 3 opciones numeradas, con salida a WhatsApp cuando no hay datos.
