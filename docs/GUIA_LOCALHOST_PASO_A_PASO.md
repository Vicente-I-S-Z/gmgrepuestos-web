# Guía paso a paso: el sitio en localhost

Para: quien quiere ver y probar la tienda y el panel en su propio computador (Windows), sin tocar el sitio real.
Tiempo estimado: 40 minutos la primera vez; después, 1 minuto para encenderlo.

Lo que funciona en local: la tienda completa, el buscador, la ficha, el carrito, el panel y las 38 pruebas automáticas.
Lo que **no** funciona en local sin claves extra (y está bien): el chat responde con la salida a WhatsApp si no hay clave de OpenAI, Mercado Pago muestra "No pudimos conectar" y los correos no salen (quedan anotados en el log).

---

## 1. Instalar (una sola vez, 15 min)

- [ ] 1. **Git for Windows**: https://git-scm.com/download/win (trae "Git Bash", que se usa en el paso 3 y en las pruebas).
- [ ] 2. **XAMPP con PHP 8.2 o 8.3**: https://www.apachefriends.org, instalado en `C:\xampp` (las pruebas buscan esa ruta).
- [ ] 3. **Edge** ya viene en Windows (lo usan algunas pruebas). **Node.js** solo si vas a usar `tests/telefono_test.php` o reinstalar las skills.
- [ ] 4. Agrega PHP y MySQL al PATH: Inicio > "Editar las variables de entorno de tu cuenta" > `Path` > Editar > Nuevo `C:\xampp\php` > Nuevo `C:\xampp\mysql\bin` > Aceptar. Cierra y vuelve a abrir la terminal. Comprueba con `php -v` (debe decir 8.x).
- [ ] 5. En `C:\xampp\php\php.ini` (ábrelo con el Bloc de notas): quita el `;` del inicio de la línea `extension=gd` y deja `date.timezone=America/Santiago`. Guarda. Comprueba con `php -m` que aparezcan `curl`, `fileinfo`, `gd`, `mbstring`, `mysqli` y `pdo_sqlite`.

## 2. Traer el proyecto (5 min)

- [ ] 1. En Git Bash, en el Escritorio (pide a Vicente que te agregue como colaborador del repo privado si no tienes acceso):

  ```bash
  cd ~/Desktop
  git clone https://github.com/Vicente-I-S-Z/gmgrepuestos-web.git Gmgrepuestos
  cd Gmgrepuestos
  ```

- [ ] 2. Si después hay cambios nuevos: `git pull` dentro de esa carpeta.

## 3. Base de datos local (10 min)

- [ ] 1. Abre el **Panel de control de XAMPP** y presiona **Start** en **MySQL** (Apache no hace falta).
- [ ] 2. En Git Bash, dentro de la carpeta del proyecto, crea la base y carga los 9 scripts en orden (01 a 09):

  ```bash
  mysql -u root -e "CREATE DATABASE gmg_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  for f in BD_v2/0*.sql; do echo "$f"; mysql -u root gmg_local < "$f" || break; done
  ```

  Debe listar los 9 archivos sin errores.
- [ ] 3. **Stock de prueba** (los scripts dejan todo en 0). Esto es **solo para local, nunca en producción**:

  ```bash
  mysql -u root gmg_local -e "UPDATE repuestos SET stock = CASE WHEN id_repuesto % 10 = 0 THEN 0 WHEN id_repuesto % 10 = 1 THEN 2 ELSE 8 END"
  ```

  Así hay productos "Disponible", "Últimas unidades" y "Sin stock" (las pruebas lo necesitan).
- [ ] 4. **Tu usuario del panel.** Los usuarios que crea el script 03 tienen las contraseñas reales del equipo; para local crea uno propio. Este comando (en Git Bash, cópialo completo) crea el usuario `local` con la contraseña `ClaveLocal-2026`:

  ```bash
  php -r '$c = new mysqli("localhost", "root", "", "gmg_local"); $h = password_hash("ClaveLocal-2026", PASSWORD_DEFAULT); $s = $c->prepare("INSERT INTO administradores (usuario, nombre, email, rol, password_hash) VALUES (\"local\", \"Admin local\", \"tu@correo.cl\", \"admin\", ?)"); $s->bind_param("s", $h); $s->execute(); echo "usuario listo\n";'
  ```

  Debe decir `usuario listo`. Si quieres otra contraseña, cámbiala dentro del comando antes de ejecutarlo.

## 4. El archivo `.env` local (5 min)

- [ ] 1. En la carpeta del proyecto, copia `.env.example` y renombra la copia a `.env` (en Git Bash: `cp .env.example .env`).
- [ ] 2. Ábrelo con el Bloc de notas y deja así estas líneas (el resto puede quedar vacío):

  ```text
  APP_ENV=pruebas
  DB_HOST=localhost
  DB_USER=root
  DB_PASS=
  DB_NAME=gmg_local
  WHATSAPP_NUMBER=56900000000
  ```

  Opcional: `AI_API_KEY=` con una clave **de pruebas** de OpenAI con límite de gasto (si no, el chat ofrece WhatsApp). Nunca uses ni copies el `.env` de producción.
- [ ] 3. **Importante:** el sitio busca el `.env` primero **una carpeta más arriba** (en tu Escritorio) y después en la carpeta del proyecto. Revisa que **no** haya un archivo `.env` suelto en el Escritorio; si lo hay, el sitio usaría ese.
- [ ] 4. El `.env` nunca se sube a Git (ya está en `.gitignore`). Revisa con `git status` que no aparezca.

## 5. Encender el sitio

Elige una opción.

**Opción A, la simple** (para ver la tienda y el panel):

- [ ] 1. En Git Bash, dentro de la carpeta del proyecto: `php -S localhost:8000`
- [ ] 2. Abre http://localhost:8000 (tienda) y http://localhost:8000/admin (panel: usuario `local`, contraseña `ClaveLocal-2026`).
- [ ] 3. Para apagarlo: `Ctrl + C` en esa terminal.

Con esta opción no se aplican los `.htaccess`, así que las fichas se ven como `producto.php?id=255` (en el sitio real son `/repuestos/swift/...`). Es normal.

**Opción B, igual al hosting** (con `.htaccess`, URLs legibles, compresión y bloqueos):

- [ ] 1. Abre `tests/apache/httpd-gmg.conf` y cambia la línea `Define GMG_RAIZ "C:/Users/santi/Desktop/Gmgrepuestos"` por la ruta de tu carpeta (con `/`, por ejemplo `C:/Users/martin/Desktop/Gmgrepuestos`). **No hagas commit de ese cambio.**
- [ ] 2. En una terminal de Windows (cmd), dentro de la carpeta del proyecto: `C:\xampp\apache\bin\httpd.exe -f "%CD%\tests\apache\httpd-gmg.conf"` y deja la ventana abierta.
- [ ] 3. Abre http://127.0.0.1:8080. Para apagarlo: `Ctrl + C` en esa ventana.

## 6. Qué revisar en localhost (10 min)

- [ ] 1. Portada: carrusel de modelos y botón verde de WhatsApp abajo a la izquierda.
- [ ] 2. Entra a **Swift**: 24 repuestos por página, filtros (precio, solo con stock, orden) y paginación.
- [ ] 3. Busca **"terminal swift"** (aparecen 3) y **"zzzz"** (aparecen alternativas y WhatsApp).
- [ ] 4. Clic en el nombre de un repuesto: ficha con fotos, compatibilidad, relacionados y "Preguntar al asistente".
- [ ] 5. Un repuesto "Sin stock": formulario "Avísame cuando llegue" (prueba con un correo).
- [ ] 6. Carrito: agrega 2 productos; "Finalizar por WhatsApp" arma el mensaje con el total.
- [ ] 7. Panel (`/admin`): Productos (cambia stock en el listado y Guardar), Pedidos, Usuarios, Banners (crea uno y míralo en la tienda), Reportes (descarga el CSV), Productos > Importar CSV (sube el mismo CSV con "Solo revisar").
- [ ] 8. Login: 5 contraseñas malas seguidas bloquean 15 minutos (para desbloquear en local: `mysql -u root gmg_local -e "DELETE FROM limites_solicitudes"`).

## 7. Correr las pruebas automáticas (opcional, 10 min)

En Git Bash, dentro de la carpeta del proyecto, con MySQL encendido:

```bash
export PATH="/c/xampp/php:/c/xampp/mysql/bin:$PATH"
for t in tests/*_test.php; do case "$t" in *chat_ia_real*|*sqlite*) continue;; esac; echo "$t: $(php "$t" 2>&1 | tail -1)"; done
php tests/chat_busqueda_sqlite_test.php BD_v2/catalogo_repuestos.csv | tail -1
bash tests/cp42_csrf_panel.sh | tail -1
bash tests/cp47_htaccess.sh | tail -1
```

Cada línea debe terminar en `TODO OK`. Notas:

- Tardan unos 10 minutos en total; varias crean una base temporal y la borran al final.
- `cp47_htaccess.sh` usa el Apache de la opción B (puerto 8080 libre y `GMG_RAIZ` con tu ruta).
- `tests/chat_ia_real_test.php` **no** se corre: gasta saldo de OpenAI (pedir permiso a Vicente).

## 8. Problemas comunes

| Síntoma | Causa y solución |
| --- | --- |
| "El sitio no está disponible en este momento" | No encuentra el `.env` o la base. Revisa la parte 4 (y que no haya un `.env` en el Escritorio) y que MySQL esté encendido. |
| `php` o `mysql` "no se reconoce" | Falta el PATH (paso 1.4) o no reabriste la terminal. |
| Todo aparece "Sin stock" | Falta el stock de prueba (paso 3.3). |
| No puedo entrar al panel | Usa el usuario `local` con la contraseña `ClaveLocal-2026` (paso 3.4). Si te bloqueó por intentos, usa el comando del paso 6.8. |
| "Falta ejecutar el script BD_v2/09" | La base se creó antes del script 09: `mysql -u root gmg_local < BD_v2/09_cuentas_banners.sql`. |
| Las fotos que subo en el panel no se guardan | Falta `extension=gd` (paso 1.5) o la carpeta `img/repuestos` no permite escribir. |
| `Address already in use` | Otro programa usa el puerto 8000 u 8080. Usa otro: `php -S localhost:8001`. |
| La prueba CP-47 dice "La configuración de Apache tiene errores" | `GMG_RAIZ` no apunta a tu carpeta (opción B, paso 1). |

Para subir al sitio real, la guía es otra: `docs/GUIA_HOSTINGER_PASO_A_PASO.md`. (`docs/OTRO_COMPUTADOR.md` es la versión para que Vicente cambie de computador con Claude Code.)
