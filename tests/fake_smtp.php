<?php
/**
 * Servidor SMTP falso para tests/correo_test.php: atiende conexiones en 127.0.0.1:PUERTO sin cifrado,
 * contesta como un servidor real y guarda la conversación completa en FAKE_SMTP_LOG.
 * FAKE_SMTP_MODO=ok (acepta todo) o auth_falla (responde 535 a la contraseña). No se publica.
 * Uso: php tests/fake_smtp.php 2526
 */
$puerto = (int)($argv[1] ?? 2526);
$log = getenv('FAKE_SMTP_LOG') ?: sys_get_temp_dir() . '/fake_smtp.log';
$modo = getenv('FAKE_SMTP_MODO') ?: 'ok';
$servidor = stream_socket_server("tcp://127.0.0.1:$puerto", $errno, $errstr);
if (!$servidor) {
    fwrite(STDERR, "No se pudo abrir el puerto $puerto: $errstr\n");
    exit(1);
}
while ($c = @stream_socket_accept($servidor, 300)) {
    $sesion = '';
    $responder = function ($texto) use ($c, &$sesion) {
        fwrite($c, $texto . "\r\n");
        $sesion .= "S: $texto\n";
    };
    $responder('220 smtp.falso listo');
    $enDatos = false;
    $paso = '';
    while (($linea = fgets($c)) !== false) {
        $sesion .= 'C: ' . $linea;
        if ($enDatos) {
            if (rtrim($linea, "\r\n") === '.') {
                $enDatos = false;
                $responder('250 OK mensaje aceptado');
            }
            continue;
        }
        $comando = strtoupper(substr(trim($linea), 0, 10));
        if (strpos($comando, 'EHLO') === 0) {
            $responder('250 smtp.falso');
        } elseif (strpos($comando, 'AUTH LOGIN') === 0) {
            $paso = 'usuario';
            $responder('334 VXNlcm5hbWU6');
        } elseif ($paso === 'usuario') {
            $paso = 'clave';
            $responder('334 UGFzc3dvcmQ6');
        } elseif ($paso === 'clave') {
            $paso = '';
            if ($modo === 'auth_falla') {
                $responder('535 5.7.8 Error: authentication failed');
                break;
            }
            $responder('235 Autenticado');
        } elseif (strpos($comando, 'MAIL FROM') === 0 || strpos($comando, 'RCPT TO') === 0) {
            $responder('250 OK');
        } elseif (strpos($comando, 'DATA') === 0) {
            $enDatos = true;
            $responder('354 Termina con un punto');
        } elseif (strpos($comando, 'QUIT') === 0) {
            $responder('221 Adios');
            break;
        } else {
            $responder('502 Comando no reconocido');
        }
    }
    fclose($c);
    file_put_contents($log, $sesion . "=====\n", FILE_APPEND);
}
