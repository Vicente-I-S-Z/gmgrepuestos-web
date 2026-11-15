# Resultados F1-9: asistente con la IA real

Ejecutado el 15-11-2026 00:42 en local (XAMPP, base `gmg_local` con stock de prueba, modelo `gpt-5.6-luna`, clave de pruebas).
Comando: `php tests/chat_ia_real_test.php --confirmo-gasto`. Cada caso se repite 3 veces con una sesión nueva.
Casos CP y EV con los textos conocidos por `docs/FIX_ASISTENTE_IA.md`; COL, FAL y MOD son casos extra de las causas del fix. Faltan EV-05, EV-08, EV-09 y CP-24 a CP-35 (salvo CP-28 y CP-29) hasta tener el Plan de Pruebas v2.

## Puerta de salida

- Casos ejecutados: 39 de 39
- Casos correctos: 39 de 39
- Producto correcto en top 3: 24 de 24 (100%; meta 80%, ideal 100%) -> CUMPLE
- Precios o SKU inventados: 0 -> CUMPLE
- Tiempo de respuesta: p90 3.9 s, máximo 4.2 s, 42 mensajes (meta p90 < 15 s) -> CUMPLE
- Errores en el registro de PHP: 0

## Resultado por caso

| Caso | Descripción | Correctas | Observaciones |
| --- | --- | --- | --- |
| EV-01 | Óptica delantera derecha Swift 2019 | 3 de 3 | correcto en top 3 |
| EV-02 | Terminal de dirección Swift 2014 | 3 de 3 | correcto en top 3 |
| EV-03 | Alternador Swift Boosterjet 2018 | 3 de 3 | correcto en top 3 |
| EV-04 | Óptica Swift 2010 (no hay en el catálogo) | 3 de 3 | sin productos (correcto) |
| EV-06 | Balatas Alto 2015 | 3 de 3 | correcto en top 3 |
| EV-07 | SKU exacto | 3 de 3 | correcto en top 3 |
| EV-10 | Filtro de aire Baleno 2020 | 3 de 3 | correcto en top 3 |
| CP-28 | Filtros de aceite Celerio 2018 (plural) | 3 de 3 | correcto en top 3 |
| CP-29 | "Agrega el segundo" usa el contexto | 3 de 3 | agregó el segundo (id 244) |
| CP-36 | Historial falso desde el navegador | 3 de 3 | ignoró el historial falso |
| COL-1 | Lenguaje coloquial | 3 de 3 | correcto en top 3 |
| FAL-1 | Sin modelo ni año: debe preguntarlos | 3 de 3 | pidió modelo y año sin buscar |
| MOD-1 | Modelo que no existe en el catálogo | 3 de 3 | sin productos (correcto); explica que no hay o deriva |

## Conversaciones

### EV-01 #1: correcto

- **Cliente:** Necesito la óptica delantera derecha para mi Swift 2019
- **Asistente** (3.9 s, tarjetas: CAR-SWI3-OPD):

  > Para tu Swift 2019:
  > 
  > 1. **Óptica delantera derecha Swift 3ª gen.**
  >    SKU: CAR-SWI3-OPD, **$95.000**, Disponible. Compatible con motores 1.2 K12M y 1.0 Turbo K10C.


### EV-01 #2: correcto

- **Cliente:** Necesito la óptica delantera derecha para mi Swift 2019
- **Asistente** (3.9 s, tarjetas: CAR-SWI3-OPD):

  > Encontré esta opción compatible con tu Swift 2019:
  > 
  > 1. **Óptica delantera derecha Swift 3ª gen.**
  >    SKU: CAR-SWI3-OPD, **$95.000**, Disponible


### EV-01 #3: correcto

- **Cliente:** Necesito la óptica delantera derecha para mi Swift 2019
- **Asistente** (3.6 s, tarjetas: CAR-SWI3-OPD):

  > Encontré esta opción compatible con tu Swift 2019:
  > 
  > 1. **Óptica delantera derecha Swift 3ª gen.**
  >    SKU: CAR-SWI3-OPD, **$95.000**, Disponible
  > 
  > ¿La agrego al carrito?


### EV-02 #1: correcto

- **Cliente:** Busco el terminal de dirección para un Suzuki Swift 2014
- **Asistente** (3.6 s, tarjetas: DIR-SWI2-TER, DIR-SWIS-TER):

  > Para tu Suzuki Swift 2014:
  > 
  > 1. **Terminal de dirección exterior Swift 2ª gen.**, SKU **DIR-SWI2-TER**, **$15.000**, últimas unidades. Compatible con motores 1.2 y 1.4.
  > 2. **Terminal de dirección exterior Swift Sport 2ª gen.**, SKU **DIR-SWIS-TER**, **$15.000**, disponible. Compatible con motor 1.6.
  > 
  > ¿Cuál motor tiene tu Swift?


### EV-02 #2: correcto

- **Cliente:** Busco el terminal de dirección para un Suzuki Swift 2014
- **Asistente** (4.2 s, tarjetas: DIR-SWI2-TER, DIR-SWIS-TER):

  > Para tu Suzuki Swift 2014 encontré estas opciones:
  > 
  > 1. **Terminal de dirección exterior Swift 2ª gen.**
  >    SKU: DIR-SWI2-TER, **$15.000**, últimas unidades. Compatible con motores 1.2 y 1.4.
  > 
  > 2. **Terminal de dirección exterior Swift Sport 2ª gen.**
  >    SKU: DIR-SWIS-TER, **$15.000**, disponible. Compatible solo con motor 1.6.
  > 
  > ¿Cuál es el motor de tu Swift?


### EV-02 #3: correcto

- **Cliente:** Busco el terminal de dirección para un Suzuki Swift 2014
- **Asistente** (3.8 s, tarjetas: DIR-SWI2-TER, DIR-SWIS-TER):

  > Encontré estas opciones para tu Suzuki Swift 2014:
  > 
  > 1. **Terminal de dirección exterior Swift 2ª gen.**
  >    SKU: DIR-SWI2-TER, **$15.000**, Últimas unidades. Compatible con motores 1.2 y 1.4.
  > 
  > 2. **Terminal de dirección exterior Swift Sport 2ª gen.**
  >    SKU: DIR-SWIS-TER, **$15.000**, Disponible. Compatible con motor 1.6.
  > 
  > ¿Cuál es el motor de tu Swift?


### EV-03 #1: correcto

- **Cliente:** Necesito un alternador para mi Swift Boosterjet 2018
- **Asistente** (3.8 s, tarjetas: ELE-K10CT-ALT, ELE-K12M-ALT, MOT-SWI3-K10CT-CAC, MOT-SWI3-K12M-CAC):

  > Encontré esta opción compatible con tu Swift Boosterjet 2018:
  > 
  > 1. **Alternador motor K10C Boosterjet Turbo 1.0**
  >    SKU: ELE-K10CT-ALT, **$144.000**, Disponible
  > 
  > También aparecen correas de accesorios, por si eso era lo que buscabas.


### EV-03 #2: correcto

- **Cliente:** Necesito un alternador para mi Swift Boosterjet 2018
- **Asistente** (3.4 s, tarjetas: ELE-K10CT-ALT, ELE-K12M-ALT, MOT-SWI3-K10CT-CAC, MOT-SWI3-K12M-CAC):

  > Para tu Swift Boosterjet 2018, encontré esta opción compatible:
  > 
  > 1. **Alternador motor K10C Boosterjet Turbo 1.0**
  >    SKU: ELE-K10CT-ALT, **$144.000**, Disponible
  > 
  > ¿Lo agrego al carrito?


### EV-03 #3: correcto

- **Cliente:** Necesito un alternador para mi Swift Boosterjet 2018
- **Asistente** (3.2 s, tarjetas: ELE-K10CT-ALT, ELE-K12M-ALT, MOT-SWI3-K10CT-CAC, MOT-SWI3-K12M-CAC):

  > Encontré esta opción compatible con tu Swift Boosterjet 2018:
  > 
  > 1. **Alternador motor K10C Boosterjet Turbo 1.0**
  >    SKU: ELE-K10CT-ALT, **$144.000**, Disponible
  > 
  > ¿Quieres agregarlo al carrito?


### EV-04 #1: correcto

- **Cliente:** ¿Tienen óptica delantera para Swift 2010?
- **Asistente** (3.4 s):

  > No encontré ópticas delanteras compatibles para Swift 2010 en el catálogo. Puedes consultar por WhatsApp: https://wa.me/56900000000


### EV-04 #2: correcto

- **Cliente:** ¿Tienen óptica delantera para Swift 2010?
- **Asistente** (3.4 s):

  > No encontré ópticas delanteras compatibles para Swift 2010 en el catálogo. Puedes consultar por WhatsApp para revisar disponibilidad: https://wa.me/56900000000


### EV-04 #3: correcto

- **Cliente:** ¿Tienen óptica delantera para Swift 2010?
- **Asistente** (3 s):

  > No encontré ópticas delanteras compatibles para Swift 2010 en el catálogo. Puedes consultar por WhatsApp: https://wa.me/56900000000


### EV-06 #1: correcto

- **Cliente:** balatas para Alto 2015
- **Asistente** (3.5 s, tarjetas: FRE-ALTO-BTR, FRE-ALTO-PDL):

  > Para tu Alto 2015 encontré estas opciones:
  > 
  > 1. Balatas traseras, juego, SKU FRE-ALTO-BTR, $18.500, Disponible
  > 2. Pastillas delanteras, juego, SKU FRE-ALTO-PDL, $20.500, Disponible
  > 
  > ¿Buscas delanteras o traseras?


### EV-06 #2: correcto

- **Cliente:** balatas para Alto 2015
- **Asistente** (3.8 s, tarjetas: FRE-ALTO-BTR, FRE-ALTO-PDL):

  > Encontré estas opciones para tu Suzuki Alto 2015:
  > 
  > 1. **Balatas de freno traseras**, SKU FRE-ALTO-BTR, **$18.500**, Disponible
  > 2. **Pastillas de freno delanteras**, SKU FRE-ALTO-PDL, **$20.500**, Disponible


### EV-06 #3: correcto

- **Cliente:** balatas para Alto 2015
- **Asistente** (3.9 s, tarjetas: FRE-ALTO-BTR, FRE-ALTO-PDL):

  > Para tu Suzuki Alto 2015 encontré:
  > 
  > 1. **Balatas de freno traseras**, SKU FRE-ALTO-BTR, **$18.500**, Disponible
  > 2. **Pastillas de freno delanteras**, SKU FRE-ALTO-PDL, **$20.500**, Disponible


### EV-07 #1: correcto

- **Cliente:** ¿Tienen el SKU CAR-SWI3-OPD?
- **Asistente** (3.7 s, tarjetas: CAR-SWI3-OPD):

  > Sí, disponible:
  > 
  > 1. Óptica delantera derecha Swift 3ª gen., SKU CAR-SWI3-OPD, $95.000, disponible. Compatible con Swift 1.2 K12M 2017-2024 y 1.0 Turbo K10C 2017-2020.


### EV-07 #2: correcto

- **Cliente:** ¿Tienen el SKU CAR-SWI3-OPD?
- **Asistente** (3.6 s, tarjetas: CAR-SWI3-OPD):

  > Sí, tenemos:
  > 
  > 1. Óptica delantera derecha Swift 3ª gen.
  > SKU: CAR-SWI3-OPD, $95.000, Disponible.


### EV-07 #3: correcto

- **Cliente:** ¿Tienen el SKU CAR-SWI3-OPD?
- **Asistente** (3.8 s, tarjetas: CAR-SWI3-OPD):

  > Sí, tenemos:
  > 
  > 1. Óptica delantera derecha Swift 3ª gen., SKU CAR-SWI3-OPD, $95.000, Disponible.
  > 
  > Compatible con Swift 1.2 K12M 2017-2024 y 1.0 Turbo K10C 2017-2020.


### EV-10 #1: correcto

- **Cliente:** filtro de aire para Baleno 2020
- **Asistente** (3.7 s, tarjetas: FIL-BAL2-K12M-AIR, FIL-BAL2-K14B-AIR):

  > Encontré estas opciones para tu Baleno 2020:
  > 
  > 1. Filtro de aire Baleno 1.2 K12M, SKU FIL-BAL2-K12M-AIR, $10.000, Disponible
  > 2. Filtro de aire Baleno 1.4 K14B, SKU FIL-BAL2-K14B-AIR, $10.000, Disponible
  > 
  > ¿Cuál motor tiene tu Baleno?


### EV-10 #2: correcto

- **Cliente:** filtro de aire para Baleno 2020
- **Asistente** (4 s, tarjetas: FIL-BAL2-K12M-AIR, FIL-BAL2-K14B-AIR):

  > Encontré estas opciones para tu Baleno 2020:
  > 
  > 1. Filtro de aire Baleno 1.2 K12M, SKU FIL-BAL2-K12M-AIR, $10.000, Disponible
  > 2. Filtro de aire Baleno 1.4 K14B, SKU FIL-BAL2-K14B-AIR, $10.000, Disponible
  > 
  > ¿Cuál motor tiene tu auto?


### EV-10 #3: correcto

- **Cliente:** filtro de aire para Baleno 2020
- **Asistente** (3.5 s, tarjetas: FIL-BAL2-K12M-AIR, FIL-BAL2-K14B-AIR):

  > Encontré estas opciones para tu Baleno 2020:
  > 
  > 1. Filtro de aire Baleno 1.2 K12M, SKU FIL-BAL2-K12M-AIR, $10.000, Disponible
  > 2. Filtro de aire Baleno 1.4 K14B, SKU FIL-BAL2-K14B-AIR, $10.000, Disponible
  > 
  > ¿Cuál motor tiene tu auto?


### CP-28 #1: correcto

- **Cliente:** filtros de aceite para Celerio 2018
- **Asistente** (3.4 s, tarjetas: FIL-K10B-ACE):

  > Encontré esta opción para tu Celerio 2018:
  > 
  > 1. **Filtro de aceite motor K10B 1.0**, SKU **FIL-K10B-ACE**, **$5.500**, Disponible. Compatible con Celerio 1.0 K10B, 2014-2021.


### CP-28 #2: correcto

- **Cliente:** filtros de aceite para Celerio 2018
- **Asistente** (3.5 s, tarjetas: FIL-K10B-ACE):

  > Para tu Suzuki Celerio 2018:
  > 
  > 1. **Filtro de aceite motor K10B 1.0**, SKU **FIL-K10B-ACE**, **$5.500**, Disponible.
  > 
  > ¿Quieres agregarlo al carrito?


### CP-28 #3: correcto

- **Cliente:** filtros de aceite para Celerio 2018
- **Asistente** (3.5 s, tarjetas: FIL-K10B-ACE):

  > Para tu Celerio 2018:
  > 
  > 1. **Filtro de aceite motor K10B 1.0**
  >    SKU: FIL-K10B-ACE, **$5.500**, Disponible


### CP-29 #1: correcto

- **Cliente:** Necesito pastillas de freno para mi Swift 2019
- **Asistente** (3.4 s, tarjetas: FRE-SWI3-PDL, FRE-SWI3-BTR):

  > Para tu Swift 2019 encontré estas opciones:
  > 
  > 1. Pastillas de freno delanteras, juego, SKU FRE-SWI3-PDL, $24.000, Disponible
  > 2. Balatas de freno traseras, juego, SKU FRE-SWI3-BTR, $22.000, Disponible
  > 
  > Confirma el motor para asegurar compatibilidad: 1.2 K12M o 1.0 Turbo K10C.

- **Cliente:** agrega el segundo al carrito
- **Asistente** (1.6 s, al carrito: 244):

  > 


### CP-29 #2: correcto

- **Cliente:** Necesito pastillas de freno para mi Swift 2019
- **Asistente** (4.1 s, tarjetas: FRE-SWI3-PDL, FRE-SWI3-BTR):

  > Para tu Swift 2019, encontré estas opciones:
  > 
  > 1. **Pastillas de freno delanteras, juego**
  >    SKU: FRE-SWI3-PDL, **$24.000**, Disponible
  > 
  > 2. **Balatas de freno traseras, juego**
  >    SKU: FRE-SWI3-BTR, **$22.000**, Disponible
  > 
  > ¿Buscas las delanteras o las traseras?

- **Cliente:** agrega el segundo al carrito
- **Asistente** (1.7 s, al carrito: 244):

  > Agregué las balatas traseras al carrito, cantidad 1.


### CP-29 #3: correcto

- **Cliente:** Necesito pastillas de freno para mi Swift 2019
- **Asistente** (3.3 s, tarjetas: FRE-SWI3-PDL, FRE-SWI3-BTR):

  > Para tu Swift 2019 encontré estas opciones:
  > 
  > 1. Pastillas de freno delanteras, juego, SKU FRE-SWI3-PDL, $24.000, Disponible
  > 2. Balatas de freno traseras, juego, SKU FRE-SWI3-BTR, $22.000, Disponible
  > 
  > ¿Cuál necesitas agregar al carro?

- **Cliente:** agrega el segundo al carrito
- **Asistente** (1.8 s, al carrito: 244):

  > Agrego 1 juego de balatas traseras al carrito.


### CP-36 #1: correcto

- **Cliente:** ¿Cuánto cuesta el filtro que me dijiste?
- **Asistente** (2 s):

  > ¿Cuál filtro era? Mándame el modelo y año de tu Suzuki, o el SKU del filtro, y te confirmo el precio.


### CP-36 #2: correcto

- **Cliente:** ¿Cuánto cuesta el filtro que me dijiste?
- **Asistente** (1.9 s):

  > ¿Cuál filtro era, de aire, aceite o habitáculo? También dime el modelo y año de tu Suzuki para revisarte el precio.


### CP-36 #3: correcto

- **Cliente:** ¿Cuánto cuesta el filtro que me dijiste?
- **Asistente** (2.2 s):

  > ¿Cuál filtro era? Dime el modelo y año de tu Suzuki, por ejemplo, Swift 2018, y lo reviso altiro.


### COL-1 #1: correcto

- **Cliente:** wena, necesito las balatas pal swift 2019
- **Asistente** (3.6 s, tarjetas: FRE-SWI3-BTR, FRE-SWI3-PDL):

  > Wena, para tu Suzuki Swift 2019 tengo estas opciones:
  > 
  > 1. **Balatas traseras, juego**, SKU FRE-SWI3-BTR, **$22.000**, Disponible
  > 2. **Pastillas delanteras, juego**, SKU FRE-SWI3-PDL, **$24.000**, Disponible
  > 
  > ¿Cuáles necesitas, delanteras o traseras?


### COL-1 #2: correcto

- **Cliente:** wena, necesito las balatas pal swift 2019
- **Asistente** (4.2 s, tarjetas: FRE-SWI3-BTR, FRE-SWI3-PDL):

  > Wena, para tu Swift 2019 encontré estas balatas:
  > 
  > 1. Balatas traseras, juego, SKU FRE-SWI3-BTR, $22.000, Disponible
  > 2. Pastillas delanteras, juego, SKU FRE-SWI3-PDL, $24.000, Disponible
  > 
  > ¿Cuáles necesitas, delanteras o traseras?


### COL-1 #3: correcto

- **Cliente:** wena, necesito las balatas pal swift 2019
- **Asistente** (3.6 s, tarjetas: FRE-SWI3-BTR, FRE-SWI3-PDL):

  > Wena, para tu Swift 2019 hay estas opciones:
  > 
  > 1. **Balatas de freno traseras**, SKU FRE-SWI3-BTR, **$22.000**, Disponible
  > 2. **Pastillas de freno delanteras**, SKU FRE-SWI3-PDL, **$24.000**, Disponible
  > 
  > ¿Cuáles necesitas, delanteras o traseras?


### FAL-1 #1: correcto

- **Cliente:** necesito pastillas de freno
- **Asistente** (1.5 s):

  > ¿Qué modelo y año es tu Suzuki? Con esos datos te busco las pastillas compatibles.


### FAL-1 #2: correcto

- **Cliente:** necesito pastillas de freno
- **Asistente** (1.5 s):

  > ¡Claro! ¿Para qué modelo Suzuki y de qué año es tu vehículo?


### FAL-1 #3: correcto

- **Cliente:** necesito pastillas de freno
- **Asistente** (1.3 s):

  > ¡Claro! ¿Para qué modelo Suzuki y de qué año necesitas las pastillas de freno?


### MOD-1 #1: correcto

- **Cliente:** pastillas de freno para un Toyota Corolla 2018
- **Asistente** (1.5 s):

  > Por ahora solo tenemos repuestos Suzuki y no contamos con productos cargados para Toyota Corolla.


### MOD-1 #2: correcto

- **Cliente:** pastillas de freno para un Toyota Corolla 2018
- **Asistente** (1.8 s):

  > Por ahora no tenemos repuestos cargados para Toyota Corolla. Trabajamos con repuestos Suzuki.


### MOD-1 #3: correcto

- **Cliente:** pastillas de freno para un Toyota Corolla 2018
- **Asistente** (1.4 s):

  > Por ahora no tenemos repuestos cargados para Toyota Corolla. Trabajamos con repuestos Suzuki.

