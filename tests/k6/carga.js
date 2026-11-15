// Prueba de carga F5-2 (sección 6 del Plan de Pruebas v2) con Grafana k6. No se sube al hosting.
// Escenario: 50 usuarios simultáneos por 5 minutos (1 minuto de subida), navegando la tienda como un
// cliente: portada, catálogo de Swift con Frenos y sugerencias del buscador, con pausas de 1 a 3 s.
// Aprueba si el P95 es menor a 1.500 ms y menos del 1 % de las peticiones falla.
//
// Uso (en la terminal, con k6 instalado):  k6 run -e SITIO=https://gmgrepuestos.cl tests/k6/carga.js
// OJO: las sugerencias tienen límite de 60 por minuto por IP (F3-3). Desde un solo computador, pasado
// ese límite responden una lista vacía (sigue siendo 200, no cuenta como falla). Es lo esperado.
import http from 'k6/http';
import { check, sleep } from 'k6';

const SITIO = __ENV.SITIO || 'https://gmgrepuestos.cl';

export const options = {
    stages: [
        { duration: '1m', target: 50 },
        { duration: '4m', target: 50 },
        { duration: '20s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<1500'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    const rutas = ['/', '/index.php?modelo=swift&categoria=Frenos', '/backend/buscar_sugerencias.php?q=filtro'];
    for (const ruta of rutas) {
        const r = http.get(SITIO + ruta, { tags: { pagina: ruta.split('?')[0] } });
        check(r, { 'responde 200': (x) => x.status === 200 });
        sleep(1 + Math.random() * 2);
    }
}
