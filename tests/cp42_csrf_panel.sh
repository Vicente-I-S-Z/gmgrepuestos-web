#!/usr/bin/env bash
# CP-42 (INC-04, E10-H2): un POST al panel sin el código de seguridad (CSRF) no cambia nada.
#
# Prueba de integración local. Crea una base temporal con todos los scripts de BD_v2, levanta
# php -S en otro puerto y envía cada acción del panel de 3 formas: sin token, con un
# token falso (ambas simulando otra página web) y con el token correcto.
# No usa ni lee el .env: la configuración de prueba se arma en una carpeta temporal.
#
# Requisitos: MariaDB de XAMPP encendido, php y mysql en el PATH, root sin contraseña.
# Uso (Git Bash, desde la carpeta del proyecto):  bash tests/cp42_csrf_panel.sh
# Termina en "TODO OK" si todas las acciones rechazan el POST sin token válido.

set -u
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
BASE="gmg_prueba_cp42"
PUERTO="${PUERTO:-8099}"
U="http://localhost:$PUERTO"
M="mysql -u root --default-character-set=utf8mb4"
TMP="$(mktemp -d)"
TMPW="$(cygpath -m "$TMP" 2>/dev/null || echo "$TMP")"
J="$TMP/cookies.txt"
FALLAS=0
PID=""

limpiar() {
    [ -n "$PID" ] && kill "$PID" 2>/dev/null
    $M -e "DROP DATABASE IF EXISTS $BASE;" 2>/dev/null
    rm -rf "$TMP"
}
trap limpiar EXIT

sql() { $M "$BASE" -N -e "$1"; }

revisar() { # descripción, valor obtenido, valor esperado
    if [ "$2" = "$3" ]; then
        printf "ok     %s\n" "$1"
    else
        printf "FALLA  %s: se esperaba '%s' y quedó '%s'\n" "$1" "$3" "$2"
        FALLAS=$((FALLAS + 1))
    fi
}

# caso "nombre" pagina "datos del formulario" "consulta" "valor si se rechaza" "valor si se acepta"
caso() {
    local nombre="$1" pagina="$2" datos="$3" consulta="$4" rechazado="$5" aceptado="$6"
    # Otra página web no conoce el token: lo omite o inventa uno
    curl -s -b "$J" -c "$J" -o /dev/null -H "Origin: http://sitio-ajeno.example" \
         -e "http://sitio-ajeno.example/" -d "$datos" "$U/admin/$pagina"
    revisar "$nombre (sin token)" "$(sql "$consulta")" "$rechazado"
    curl -s -b "$J" -c "$J" -o /dev/null -H "Origin: http://sitio-ajeno.example" \
         -e "http://sitio-ajeno.example/" -d "$datos&csrf=0123456789abcdef" "$U/admin/$pagina"
    revisar "$nombre (token falso)" "$(sql "$consulta")" "$rechazado"
    # El formulario real del panel sí funciona
    curl -s -b "$J" -c "$J" -o /dev/null -d "$datos&csrf=$TOKEN" "$U/admin/$pagina"
    revisar "$nombre (token correcto)" "$(sql "$consulta")" "$aceptado"
}

echo "== Preparando base temporal $BASE =="
$M -e "DROP DATABASE IF EXISTS $BASE; CREATE DATABASE $BASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || exit 1
for f in "$RAIZ"/BD_v2/[0-9][0-9]_*.sql; do   # todos los scripts numerados, en orden
    $M "$BASE" < "$f" || { echo "No se pudo cargar $f"; exit 1; }
done
HASH="$(printf 'Clave-CP42\n' | php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT);')"
sql "INSERT INTO administradores (usuario, nombre, password_hash) VALUES ('prueba_cp42', 'Prueba CP-42', '$HASH');
     INSERT INTO clientes (rut, nombre, apellido, email, telefono) VALUES ('11111111-1', 'Prueba', 'CP42', 'prueba@example.com', '+56911111111');
     INSERT INTO pedidos (id_cliente, total) VALUES (LAST_INSERT_ID(), 10000);
     INSERT INTO avisos_stock (id_repuesto, contacto, sesion_id) VALUES (1, 'prueba@example.com', 'cp42');
     INSERT INTO codigos_descuento (codigo, tipo, valor, activo) VALUES ('BORRAR42', 'monto', 1000, 1);" || exit 1
ID_PEDIDO="$(sql "SELECT MAX(id_pedido) FROM pedidos")"
ID_AVISO="$(sql "SELECT MAX(id_aviso) FROM avisos_stock")"
ID_BIENVENIDO="$(sql "SELECT id_codigo FROM codigos_descuento WHERE codigo = 'BIENVENIDO10'")"
ID_BORRAR="$(sql "SELECT id_codigo FROM codigos_descuento WHERE codigo = 'BORRAR42'")"
PRECIO_ORIGINAL="$(sql "SELECT precio FROM repuestos WHERE id_repuesto = 1")"
DATOS_PRODUCTO="$(sql "SELECT nombre, id_categoria, sku FROM repuestos WHERE id_repuesto = 1" | php -r '
    [$n, $c, $s] = explode("\t", trim(fgets(STDIN)));
    echo http_build_query(["accion" => "guardar", "id" => 1, "nombre" => $n, "id_categoria" => $c,
                           "sku" => $s, "precio" => 999999, "stock" => 7, "activo" => 1]);')"

# Configuración de prueba: SMTP a 127.0.0.1 para que ningún correo salga del computador
printf 'APP_ENV=pruebas\nDB_HOST=localhost\nDB_USER=root\nDB_PASS=\nDB_NAME=%s\nSMTP_HOST=127.0.0.1\n' "$BASE" > "$TMP/config_prueba.txt"

echo "== Levantando el sitio en $U =="
GMG_ENV_FILE="$TMPW/config_prueba.txt" php -d error_log="$TMPW/php.log" -S "localhost:$PUERTO" -t "$RAIZ" >/dev/null 2>&1 &
PID=$!
for _ in $(seq 1 40); do curl -s -o /dev/null "$U/login.php" && break; sleep 0.25; done

curl -s -b "$J" -c "$J" -o /dev/null -d 'usuario=prueba_cp42&password=Clave-CP42' "$U/login.php"
TOKEN="$(curl -s -b "$J" -c "$J" "$U/admin/catalogo.php" | grep -o 'name="csrf" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//; s/"$//')"
if [ -z "$TOKEN" ]; then echo "No se pudo iniciar sesión u obtener el token del panel"; exit 1; fi

echo "== Formularios del panel: cada formulario POST lleva el campo de seguridad =="
for pagina in codigos.php pedidos.php productos.php "productos.php?nuevo=1" avisos.php catalogo.php conocimiento.php; do
    html="$(curl -s -b "$J" -c "$J" "$U/admin/$pagina")"
    formularios="$(printf '%s' "$html" | grep -ciE '<form[^>]*method="post"')"
    campos="$(printf '%s' "$html" | grep -c "name=\"csrf\" value=\"$TOKEN\"")"
    if [ "$formularios" -gt 0 ]; then
        revisar "$pagina: $formularios formularios POST con su campo CSRF" "$campos" "$formularios"
    else
        revisar "$pagina: tiene formularios POST" "$formularios" "más de 0"
    fi
done

echo "== Al rechazar, la página muestra el aviso al administrador =="
for pagina in codigos.php pedidos.php productos.php avisos.php catalogo.php conocimiento.php; do
    html="$(curl -s -L -b "$J" -c "$J" -d "accion=nada" "$U/admin/$pagina")"
    revisar "$pagina: muestra 'No se pudo comprobar la seguridad'" \
        "$(printf '%s' "$html" | grep -c 'No se pudo comprobar la seguridad del formulario')" 1
done

echo "== CP-42: acciones del panel =="
caso "Códigos: crear"            codigos.php      "accion=crear&codigo=CSRF42&tipo=porcentaje&valor=5" \
     "SELECT COUNT(*) FROM codigos_descuento WHERE codigo = 'CSRF42'" 0 1
caso "Códigos: activar/desactivar" codigos.php    "accion=alternar&id=$ID_BIENVENIDO" \
     "SELECT activo FROM codigos_descuento WHERE id_codigo = $ID_BIENVENIDO" 1 0
caso "Códigos: eliminar"         codigos.php      "accion=eliminar&id=$ID_BORRAR" \
     "SELECT COUNT(*) FROM codigos_descuento WHERE id_codigo = $ID_BORRAR" 1 0
caso "Pedidos: cambiar estado"   pedidos.php      "accion=actualizar_estado&id=$ID_PEDIDO&estado=aprobado" \
     "SELECT estado FROM pedidos WHERE id_pedido = $ID_PEDIDO" pendiente aprobado
caso "Productos: cambiar precio" productos.php    "$DATOS_PRODUCTO" \
     "SELECT precio FROM repuestos WHERE id_repuesto = 1" "$PRECIO_ORIGINAL" 999999.00
caso "Productos: eliminar"       productos.php    "accion=eliminar&id=2" \
     "SELECT COUNT(*) FROM repuestos WHERE id_repuesto = 2" 1 0
caso "Avisos: marcar notificado" avisos.php       "accion=marcar_notificado&id=$ID_AVISO" \
     "SELECT notificado FROM avisos_stock WHERE id_aviso = $ID_AVISO" 0 1
caso "Catálogo: ocultar modelo"  catalogo.php     "accion=alternar_modelo&id_modelo=1" \
     "SELECT activo FROM modelos WHERE id_modelo = 1" 1 0
caso "Asistente: cambiar horario" conocimiento.php "t_horario=Prueba+CP42" \
     "SELECT IFNULL(contenido, '') FROM base_conocimiento WHERE clave = 'horario'" "" "Prueba CP42"

echo "== Registro de errores de PHP =="
if [ -s "$TMP/php.log" ]; then
    sed 's/^\[[^]]*\] //' "$TMP/php.log" | sort | uniq -c
    ERRORES_PHP="$(grep -cE 'PHP (Fatal|Warning|Notice|Deprecated|Parse)' "$TMP/php.log")"
    revisar "Sin errores de PHP en el registro" "$ERRORES_PHP" 0
else
    echo "vacío"
fi

echo
if [ "$FALLAS" -eq 0 ]; then echo "TODO OK"; else echo "FALLAS: $FALLAS"; exit 1; fi
