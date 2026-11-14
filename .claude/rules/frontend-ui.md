---
paths:
  - "js/**/*.js"
  - "css/**/*.css"
  - "*.php"
  - "backend/parciales/*.php"
---

# Reglas de interfaz

- JavaScript y CSS nativos. Sin librerías nuevas (las skills de Emil Kowalski se aplican como principios, no como dependencias).
- Nada de `onclick` en línea con datos: usar atributos `data-*` y `addEventListener`. Texto dinámico con `escapeHTML()` o `textContent`.
- Animar solo `opacity` y `transform`; nunca `transition: all`. Duraciones de 150 a 250 ms para interacciones.
- Respetar `prefers-reduced-motion` en CSS y en JS (`matchMedia`).
- Mobile first: probar a 360, 768 y 1366 px sin scroll horizontal; áreas táctiles de 44 x 44 px o más.
- Paleta de marca (RNF-025): negro `#111111`, rojo `#D32F2F` (acento y botones), gris `#F5F5F5` (fondo), blanco en tarjetas, verde `#25D366` (WhatsApp), azul `#009EE3` (Mercado Pago).
- Accesibilidad: controles como `<button>`, foco visible, contraste 4,5:1, `alt` descriptivo en imágenes de producto.
- Estados de carga, éxito y error visibles en cada acción asíncrona (RNF-028).
- Cache de recursos: versionar CSS y JS con `gmgVersionArchivo()` (usa `filemtime`), nunca con `time()`.
