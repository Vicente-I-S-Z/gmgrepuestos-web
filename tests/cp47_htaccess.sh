#!/usr/bin/env bash
# CP-47 (RNF-007, RNF-034): desde internet no se pueden abrir claves, scripts SQL,
# documentos internos ni archivos de lógica del backend; el sitio público sí funciona.
#
# Usa el Apache real de XAMPP con los .htaccess del proyecto (tests/apache/httpd-gmg.conf,
# solo en 127.0.0.1:8080). php -S no sirve para esto porque ignora los .htaccess.
# No pide el .env real: la regla que lo bloquea es la de "archivos que empiezan con punto",
# y se prueba con un nombre que no existe (Apache responde 403 igual, sin leer nada).
# La configuración de PHP de la prueba apunta a gmg_local, sin claves.
#
# Requisitos: XAMPP (Apache y PHP), MariaDB encendido con la base gmg_local, puerto 8080 libre.
# Uso (Git Bash, desde la carpeta del proyecto):  bash tests/cp47_htaccess.sh
# Termina en "TODO OK" si todas las rutas responden lo esperado.

set -u
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
RAIZW="$(cygpath -m "$RAIZ" 2>/dev/null || echo "$RAIZ")"
HTTPD="/c/xampp/apache/bin/httpd.exe"
PIDFILE="/c/xampp/apache/logs/gmg-8080.pid"
U="http://127.0.0.1:8080"
TMP="$(mktemp -d)"
TMPW="$(cygpath -m "$TMP" 2>/dev/null || echo "$TMP")"
FALLAS=0

detener() {
    if [ -f "$PIDFILE" ]; then
        taskkill //F //T //PID "$(tr -d '\r\n ' < "$PIDFILE")" >/dev/null 2>&1
    fi
    rm -rf "$TMP"
}
trap detener EXIT

if curl -s -o /dev/null --max-time 2 "$U/"; then
    echo "El puerto 8080 ya está en uso. Cierra el Apache de prueba (Ctrl+C) y vuelve a intentar."
    trap - EXIT; rm -rf "$TMP"; exit 1
fi

printf 'APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME=gmg_local\nSMTP_HOST=127.0.0.1\n' > "$TMP/config_prueba.txt"
"$HTTPD" -t -f "$RAIZW/tests/apache/httpd-gmg.conf" >/dev/null 2>&1 || { echo "La configuración de Apache tiene errores"; exit 1; }
GMG_ENV_FILE="$TMPW/config_prueba.txt" "$HTTPD" -f "$RAIZW/tests/apache/httpd-gmg.conf" >/dev/null 2>&1 &
for _ in $(seq 1 40); do curl -s -o /dev/null --max-time 1 "$U/" && break; sleep 0.25; done

esperar() { # código esperado, ruta
    local obtenido
    obtenido="$(curl -s -o /dev/null -w '%{http_code}' "$U/$2")"
    if [ "$obtenido" = "$1" ]; then
        printf "ok     %s  /%s\n" "$obtenido" "$2"
    else
        printf "FALLA  /%s: se esperaba %s y respondió %s\n" "$2" "$1" "$obtenido"
        FALLAS=$((FALLAS + 1))
    fi
}

echo "== Claves y archivos ocultos (403) =="
for r in ".no-existe-como-env" "prueba-inexistente.env" ".env.example" ".htaccess" ".gitignore" ".gitattributes" "CLAUDE.md"; do
    esperar 403 "$r"
done

echo "== Carpetas internas (403) =="
for r in "BD_v2/" "BD_v2/01_estructura.sql" "BD_v2/catalogo_repuestos.csv" \
         "docs/" "docs/PLAN_PRODUCCION.md" "Docs/PLAN_PRODUCCION.md" \
         "arquitectura/" "arquitectura/index.html" \
         "tests/" "tests/apache/httpd-gmg.conf" "tests/cp47_htaccess.sh" \
         ".claude/" ".claude/settings.json" ".claude/rules/php-backend.md" \
         ".git/" ".git/HEAD" ".git/config" "%2Egit/config" \
         "backend/parciales/widgets_tienda.php"; do
    esperar 403 "$r"
done

echo "== Lógica interna del backend (403) =="
for n in bot_config config auth chat_helpers chat_busqueda email_config email_helpers SmtpMailer modelos datos \
         entorno seguridad conocimiento pagina_publica auditoria pagos admin_ui pedido_estado fotos limpiar_chat; do
    esperar 403 "backend/$n.php"
done

echo "== Sitio público (200) y panel (302 al login) =="
for r in "" "index.php" "index.php?modelo=swift" "login.php" "contacto.php" "mi-pedido.php" \
         "css/style.css" "js/chat.js" "img/fronx.jpg" \
         "backend/buscar_sugerencias.php?q=filtro" "backend/chat.php" "sitemap.xml" "robots.txt"; do
    esperar 200 "$r"
done
esperar 302 "admin/"
esperar 404 "no-existe-esta-pagina.php"

echo "== URLs legibles (F4-2) =="
ubicacion() { curl -s -o /dev/null -w '%{redirect_url}' "$U/$1"; }
codigo() { curl -s -o /dev/null -w '%{http_code}' "$U/$1"; }
verificar() { # condición (0 = ok), descripción
    if [ "$1" -eq 0 ]; then printf "ok     %s
" "$2"; else printf "FALLA  %s
" "$2"; FALLAS=$((FALLAS + 1)); fi
}
LEGIBLE="$(ubicacion 'producto.php?id=255')"
[ "$(codigo 'producto.php?id=255')" = "301" ] && echo "$LEGIBLE" | grep -q '/repuestos/swift/optica-delantera-derecha-swift-3a-gen-255$'
verificar $? "producto.php?id=255 -> 301 a ${LEGIBLE#$U}"
RUTA="${LEGIBLE#$U/}"
FICHA="$(curl -s "$U/$RUTA")"
[ "$(codigo "$RUTA")" = "200" ] && echo "$FICHA" | grep -q "<base href=\"/\">" && echo "$FICHA" | grep -q "rel=\"canonical\" href=\"$LEGIBLE\""
verificar $? "la URL legible responde 200 con <base> y canonical propia"
[ "$(codigo 'css/style.css')" = "200" ] && echo "$FICHA" | grep -q 'href="css/style.css'
verificar $? "los recursos de la ficha siguen siendo relativos a la raíz"
[ "$(codigo 'repuestos/swift/nombre-viejo-255')" = "301" ] && [ "$(ubicacion 'repuestos/swift/nombre-viejo-255')" = "$LEGIBLE" ]
verificar $? "un nombre viejo redirige (301) a la URL correcta"
[ "$(codigo 'repuestos/swift/no-existe-999999')" = "404" ]
verificar $? "un id inexistente responde 404"
curl -s "$U/index.php?modelo=swift" | grep -q 'href="repuestos/swift/'
verificar $? "las tarjetas de la tienda enlazan a URLs legibles"
curl -s "$U/sitemap.xml" | grep -q "<loc>$U/repuestos/"
verificar $? "el sitemap usa URLs legibles"

echo "== Rendimiento (F4-7): compresión y caché =="
cabecera() { # texto esperado en las cabeceras, ruta, descripción
    if curl -s -D - -o /dev/null -H 'Accept-Encoding: gzip' "$U/$2" | grep -qi "$1"; then
        printf "ok     /%s: %s
" "$2" "$3"
    else
        printf "FALLA  /%s: falta %s
" "$2" "$3"
        FALLAS=$((FALLAS + 1))
    fi
}
cabecera 'Content-Encoding: gzip' "css/style.css" "comprimido"
cabecera 'Content-Encoding: gzip' "index.php" "comprimido"
cabecera 'Cache-Control: max-age=31536000' "css/style.css" "caché de 1 año"
cabecera 'Cache-Control: max-age=2592000' "img/swift.webp" "caché de 1 mes"

echo
if [ "$FALLAS" -eq 0 ]; then echo "TODO OK"; else echo "FALLAS: $FALLAS"; exit 1; fi
