<?php
// Control de tests/csp_test.php (F3-5): página con la CSP del sitio que intenta cargar un script
// de otro dominio. Debe quedar bloqueado. No se publica (tests/).
require __DIR__ . '/../backend/config.php';
?><!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.example.com/x.js"></script></head><body>control</body></html>
