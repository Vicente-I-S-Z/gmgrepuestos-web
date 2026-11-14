---
paths:
  - "**/*.php"
---

# Reglas para PHP

- SQL solo con `prepare` + `bind_param`. Para listas de IDs, castear cada uno a `int` antes de `implode`.
- En `/admin`: `requerirAdmin('../')` en la primera línea útil, `backend/admin_ui.php` cargado antes de procesar `POST`, y todo `POST` rechazado si `gmgCsrfValido()` es falso.
- Escapar toda salida dinámica: `gmgH()` (público) o `gmgAdminH()` (panel). Nunca `echo` directo de `$_GET`, `$_POST` o datos de la base.
- Errores: `error_log('[GMG Modulo] detalle')`. Al usuario solo un mensaje corto en español de Chile, sin rutas ni SQL.
- Endpoints JSON públicos (`backend/*.php` que responde al navegador): validar tipo y largo de cada campo y limitar por IP con `gmgLimiteSuperado()`.
- Archivos internos nuevos de `backend/` se agregan a la lista `FilesMatch` de `backend/.htaccess`.
- Cambios de precio, stock, estado de pedido o códigos de descuento se registran con `gmgAuditar()`.
- No tocar la integración de Mercado Pago (`mercadopago_return.php`, `backend/pagos.php`, preferencia en `checkout.php`) salvo que la tarea lo diga.
- Compatibilidad: PHP 7.4 o superior (Hostinger). Evitar funciones solo de 8.1+ como `fetch_column()`.
- Antes de cerrar: `php -l` sobre cada archivo tocado.
