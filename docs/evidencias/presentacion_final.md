# Presentación final: GMG Repuestos (esquema de 11 láminas)

Esquema para armar las láminas. Las cifras salen de las pruebas reales del proyecto (15-11-2026).
Lo marcado con **[completar]** lo pone el equipo (no se inventa: reflexiones, capturas, datos de GMG).

---

## Lámina 1. Portada

- GMG Repuestos: tienda en línea de repuestos Suzuki con Asistente Virtual IA.
- Capstone Duoc UC, sección 003D. Equipo: Vicente Santibañez (Product Owner) y Martín Núñez. Cliente: GMG Repuestos SpA.
- **[completar]** docente guía y fecha.

## Lámina 2. El problema

- GMG vende repuestos Suzuki y atendía todo por WhatsApp y en persona: cada consulta ("¿le sirve a mi Swift 2019?") dependía de que alguien respondiera.
- El cliente no sabe el nombre técnico ni el código de la pieza, y equivocarse de modelo o año significa un cambio o una devolución.
- **[completar]** un dato de GMG (consultas por día, horas de respuesta o devoluciones por incompatibilidad), si lo tienen.

## Lámina 3. Objetivo

- Que el cliente encuentre el repuesto correcto para su modelo y año, lo compre en línea y coordine la entrega por WhatsApp (GMG no hace despachos).
- Que GMG administre catálogo, stock, pedidos y pagos sin depender de terceros.

## Lámina 4. La solución

- Tienda: 14 modelos, 538 repuestos, buscador por palabras (entiende plurales, sinónimos chilenos y SKU), filtros, ficha de cada producto con compatibilidad.
- Asistente Virtual IA (OpenAI con la herramienta `buscar_productos`): pregunta modelo y año, busca en el catálogo real, nunca inventa precios ni stock, agrega al carrito y deriva a WhatsApp con un resumen.
- Compra: carrito con tope de stock, código de descuento, pago con Mercado Pago o "Finalizar por WhatsApp".
- Panel: productos (fotos, edición rápida de precio y stock), pedidos (aprobar descuenta stock), avisos de reposición, base de conocimiento del asistente, auditoría.
- Stack: PHP 8 sin framework, MariaDB, JavaScript nativo, Hostinger.

## Lámina 5. Arquitectura (1 diagrama)

- Navegador (tienda, chat, carrito) → PHP en Hostinger → MariaDB.
- Integraciones: OpenAI Responses API (function calling), Mercado Pago, SMTP de Hostinger, enlaces wa.me.
- Seguridad en capas: HTTPS, CSP, CSRF en el panel, sentencias preparadas, límites por IP, `.env` fuera de `public_html`.
- **[completar]** usar la vista lógica o de despliegue del documento 4+1 (`arquitectura/`).

## Lámina 6. Demo (guion de 3 minutos)

1. (0:00) Portada: elegir **Swift**. Mostrar paginación de 24, filtros "Solo con stock" y orden por precio.
2. (0:30) Buscar **"terminal swift"**: aparecen los 3 terminales (antes no encontraba nada, INC-07).
3. (0:50) Abrir una ficha: compatibilidad, disponibilidad, **"Preguntar al asistente"** (el chat se abre con el repuesto escrito).
4. (1:20) En el chat: **"wena, necesito las balatas pal swift 2019"** → pregunta o muestra opciones con tarjetas reales; luego **"agrega el segundo"** → queda en el carrito.
5. (2:00) Carrito: **"Finalizar por WhatsApp"** (mensaje con productos y total) o "Pagar con Mercado Pago".
6. (2:20) Panel: Productos → cambiar el stock de uno en el listado (queda auditado); si tenía avisos de reposición, el panel dice a cuántos clientes avisó por correo.
7. (2:45) Cierre: un repuesto sin stock muestra "Avísame cuando llegue".

Plan B si falla internet o la IA: capturas o video grabado de los mismos pasos **[completar]**.

## Lámina 7. Resultados de pruebas

- **Asistente con la IA real** (39 casos × 3 repeticiones): 39 de 39 correctos; producto correcto en el top 3 en el **100 %** (meta 80 %); **0** precios o SKU inventados; tiempo de respuesta p90 **3,9 s** (meta 15 s).
- **39 pruebas automáticas** (38 sin costo, todas en "TODO OK", y 1 con la IA real) con **452 verificaciones**: búsqueda, checkout con stock y bloqueo `FOR UPDATE` (CP-15), CSRF del panel (CP-42), bloqueos del `.htaccess` con Apache real (CP-47), login con bloqueo por fuerza bruta (CP-38), entradas maliciosas en el buscador (CP-06), subidas de archivos inválidas (CP-46), accesibilidad (contraste ≥ 4,5:1) y CSP sin bloqueos.
- Casos del Plan de Pruebas v2 cubiertos con prueba automática: CP-03 a CP-06, CP-15, CP-34, CP-36, CP-37, CP-38, CP-40, CP-41, CP-42, CP-46, CP-47 y valores límite VL-13, VL-17 y VL-18. (VL-16 difiere: con 4 fotos el sitio las rechaza con un aviso en vez de enviar 3; decidir con GMG si se ajusta el plan o el sitio.)
- **[completar]** resultados en producción tras la subida (CP-47 en el sitio real, prueba con el cliente).

## Lámina 8. Dificultades y ajustes

- El asistente inventaba o no encontraba productos (búsqueda con OR, sin plurales ni sinónimos, historial duplicado): se rehízo con búsqueda por relevancia, historial desde la base y herramienta con datos reales (INC-05, INC-06).
- El chat se cortaba por tiempo (llamadas encadenadas de 40 s): presupuesto de 25 s, máximo 2 rondas y 1 reintento (INC-01).
- Seguridad: el respaldo con el `.env` real estaba en un repositorio público; se sacó y se rotaron claves (INC-03). Faltaba CSRF en el panel (INC-04) y había SQL armado a mano: hoy todo usa sentencias preparadas.
- Despliegue: una subida con "Extraer" dejó el sitio caído el 14-11; se restauró y se cambió el método (archivos sueltos por carpeta, con respaldo previo).
- Stock: se podía vender sin stock (INC-10); ahora se valida al pagar con bloqueo y se descuenta una sola vez al aprobar.

## Lámina 9. Gestión del proyecto

- Backlog de 99 historias (525 puntos) y SRS de 126 requisitos; plan por fases con puerta de salida y prueba por tarea.
- **[completar]** puntos cerrados y velocidad por sprint (ver `docs/CIERRE.md`).
- Lo que queda: restablecer contraseña, URLs legibles, Google Analytics (falta el ID de GMG), importar CSV, correos de pedido aprobado, pruebas de carga (k6) y OWASP ZAP, y el acta de aceptación de GMG.

## Lámina 10. Aprendizajes

- **[completar]** reflexión de Vicente.
- **[completar]** reflexión de Martín.
- Sugerencias de temas: probar con datos reales antes de dar algo por hecho; la IA necesita herramientas y reglas, no solo un prompt; la seguridad se diseña desde el inicio; desplegar con respaldo y en pasos pequeños.

## Lámina 11. Cierre y preguntas

- Sitio: https://gmgrepuestos.cl
- Próximos pasos con GMG: carga de stock real, UAT y acta firmada.
