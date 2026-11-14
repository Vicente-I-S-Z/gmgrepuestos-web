# Ordenar el repositorio GmgRepuestos (GitHub)

Repositorio: https://github.com/mnunezt14/GmgRepuestos (dueño: Martín).
Estructura pedida por el profe: una carpeta por fase, con `Evidencias Individuales`, `Evidencias Grupales` y (en Fase 2) `Evidencias Proyecto`.
Nombres de estudiantes en MAYÚSCULA y sin tildes: `SANTIBANEZ_VICENTE_...`, `NUNEZ_MARTIN_...`.

## 0. Antes de todo: hay claves a la vista

El repositorio es **público** y `Fase 2/Evidencias de proyecto/Codigo/GmgRepuestos.zip` trae adentro el `.env` con las 15 claves reales (base de datos, Mercado Pago, OpenAI y correo). El respaldo `u118470389_PruebaHpanel.sql` trae los hashes de las contraseñas de los 3 administradores.

1. **Cambiar las 4 claves ahora** (hPanel: contraseña de la BD y del correo; Mercado Pago: nuevo access token; OpenAI: borrar la clave y crear otra). Borrar el zip no basta: queda en el historial de Git.
2. Sacar esos dos archivos del repo (paso 2 de los comandos).
3. Opcional, pero recomendable: pedirle al profe si acepta el repo **privado** con él como colaborador.

## 1. Cómo debe quedar (foco en Fase 3)

```text
Fase 3/
├── Evidencias Individuales/
│   ├── SANTIBANEZ_VICENTE_3.1_APT122_DiarioReflexionFase3.docx   (pendiente: lo escribe cada uno)
│   └── NUNEZ_MARTIN_3.1_APT122_DiarioReflexionFase3.docx         (pendiente)
└── Evidencias Grupales/
    ├── PLANILLA DE EVALUACIÓN FASE 3.xlsx                         (la manda el profe por correo)
    ├── Presentación Final del proyecto (Español).pptx            (pendiente)
    └── Presentación Final del proyecto (Inglés).pptx             (optativo)

Fase 2/
├── Evidencias Individuales/
│   ├── SANTIBANEZ_VICENTE_2.1_APT122_DiarioReflexionFase2.docx
│   └── NUNEZ_MARTIN_2.1_APT122_DiarioReflexionFase2.docx
├── Evidencias Grupales/
│   ├── 2.4_GuiaEstudiante_Fase 2_DesarrolloProyecto APT (Español).docx    (hoy: Informe_Avance_APT_Fase2_GMG.docx)
│   ├── PLANILLA DE EVALUACION AVANCE FASE 2.xlsx                          (la entrega el profe)
│   ├── 2.6_GuiaEstudiante_Fase 2_Informe Final Proyecto APT (Español).docx (nueva, ya rellenada)
│   └── PLANILLA DE EVALUACION FINAL FASE 2.xlsx                           (la entrega el profe)
└── Evidencias Proyecto/
    ├── Presentación Proyecto.pptx                                         (pendiente)
    ├── Evidencias de documentación/   (Scrum, DAS 4+1, costos, matriz de cambios, SRS, Plan de Pruebas v2)
    └── Evidencias de sistema/
        ├── Aplicación/      (código del sitio SIN .env)
        └── Base de datos/   (modelo y scripts 01, 02 y 04 de BD_v2)

Fase 1/
├── Evidencias Individuales/   (1.1, 1.2 y 1.3 de cada uno; hoy están sueltos en Fase 1/)
└── Evidencias Grupales/
    ├── Presentación Proyecto.pptx                                    (hoy: Gmgrepuestos.pptx)
    ├── 1.4_APT122_FormativaFase1.docx
    ├── 1.5_GuiaEstudiante_Fase 1_Definicion Proyecto APT (Español).docx
    └── PLANILLA DE EVALUACIÓN FASE 1.xlsx
```

## 2. Comandos (Git Bash, desde la carpeta del repo)

Si no tienes permiso de escritura en el repo de Martín, pídele que te agregue como colaborador o que él ejecute esto. Los nombres con tilde se escriben más fácil con la tecla Tab.

```bash
# 0) Traer lo último
git pull

# 1) Que nunca más se suba un .env
printf ".env\n**/.env\n*.env\n" >> .gitignore

# 2) Seguridad: sacar el respaldo con claves y el volcado con hashes
git rm "Fase 2/Evidencias de proyecto/Codigo/GmgRepuestos.zip"
git rm "Fase 2/Evidencias de proyecto/Codigo/u118470389_PruebaHpanel.sql"

# 3) Fase 1: individuales a su carpeta y nombres correctos
mkdir -p "Fase 1/Evidencias Individuales"
git mv "Fase 1/SANTIBANEZ_VICENTE_1.1_APT122_AutoevaluacionCompetenciasFase1.docx" "Fase 1/Evidencias Individuales/"
git mv "Fase 1/SANTIBANEZ_VICENTE_1.2_APT122_DiarioReflexionFase1.docx" "Fase 1/Evidencias Individuales/"
git mv "Fase 1/SANTIBANEZ_VICENTE_1.3_APT122_AutoevaluaciónFase1.docx" "Fase 1/Evidencias Individuales/SANTIBANEZ_VICENTE_1.3_APT122_AutoevaluacionFase1.docx"
git mv "Fase 1/NUNEZ_MARTIN_APT122_AutoevaluacionCompetenciasFase1.docx.pdf" "Fase 1/Evidencias Individuales/NUNEZ_MARTIN_1.1_APT122_AutoevaluacionCompetenciasFase1.pdf"
git mv "Fase 1/NUNEZ_MARTIN_APT122_DiarioReflexionFase1.docx.pdf" "Fase 1/Evidencias Individuales/NUNEZ_MARTIN_1.2_APT122_DiarioReflexionFase1.pdf"
git mv "Fase 1/NUNEZ_MARTIN_APT122_AutoevaluaciónFase1.docx.pdf" "Fase 1/Evidencias Individuales/NUNEZ_MARTIN_1.3_APT122_AutoevaluacionFase1.pdf"
git mv "Fase 1/Evidencias Grupales/Gmgrepuestos.pptx" "Fase 1/Evidencias Grupales/Presentación Proyecto.pptx"
git mv "Fase 1/Evidencias Grupales/1.5_GuiaEstudiante_Fase 1_Definicion Proyecto APT.docx" "Fase 1/Evidencias Grupales/1.5_GuiaEstudiante_Fase 1_Definicion Proyecto APT (Español).docx"

# 4) Fase 2: individuales en MAYÚSCULA (en Windows, un cambio solo de mayúsculas se hace en 2 pasos)
cd "Fase 2/Evidencias Individuales"
git mv Santibanez_Vicente_2.1_APT122_DiarioReflexionFase2.docx tmp_v.docx
git mv tmp_v.docx SANTIBANEZ_VICENTE_2.1_APT122_DiarioReflexionFase2.docx
git mv Nunez_Martin_2.1_APT122_DiarioReflexionFase2.docx tmp_m.docx
git mv tmp_m.docx NUNEZ_MARTIN_2.1_APT122_DiarioReflexionFase2.docx
cd ../..

# 5) Fase 2: grupales
git mv "Fase 2/Evidencias Grupales/Informe_Avance_APT_Fase2_GMG.docx" "Fase 2/Evidencias Grupales/2.4_GuiaEstudiante_Fase 2_DesarrolloProyecto APT (Español).docx"
# copia aquí la guía 2.6 (está en docs/evidencias de la carpeta del proyecto):
cp "/c/Users/santi/Desktop/Gmgrepuestos/docs/evidencias/2.6_GuiaEstudiante_Fase 2_Informe Final Proyecto APT (Español).docx" "Fase 2/Evidencias Grupales/"

# 6) Fase 2: "Evidencias de proyecto" pasa a "Evidencias Proyecto"
mkdir -p "Fase 2/Evidencias Proyecto/Evidencias de sistema/Base de datos" "Fase 2/Evidencias Proyecto/Evidencias de sistema/Aplicación"
git mv "Fase 2/Evidencias de proyecto/Documentacion" "Fase 2/Evidencias Proyecto/Evidencias de documentación"
git mv "Fase 2/Evidencias de proyecto/Codigo/Modelo_BD.dmd" "Fase 2/Evidencias Proyecto/Evidencias de sistema/Base de datos/"
git mv "Fase 2/Evidencias Proyecto/Evidencias de documentación/Modelo relacional.png" "Fase 2/Evidencias Proyecto/Evidencias de sistema/Base de datos/"
cp /c/Users/santi/Desktop/Gmgrepuestos/BD_v2/01_estructura.sql /c/Users/santi/Desktop/Gmgrepuestos/BD_v2/02_datos_catalogo.sql /c/Users/santi/Desktop/Gmgrepuestos/BD_v2/04_fotos_modelos.sql "Fase 2/Evidencias Proyecto/Evidencias de sistema/Base de datos/"
rm -rf "Fase 2/Evidencias de proyecto"

# 7) Código del sitio, SIN .env ni carpetas internas
APP="Fase 2/Evidencias Proyecto/Evidencias de sistema/Aplicación"
cp -r /c/Users/santi/Desktop/Gmgrepuestos/. "$APP/"
rm -rf "$APP/.env" "$APP/BD_v2" "$APP/docs" "$APP/arquitectura" "$APP/.claude" "$APP/CLAUDE.md" "$APP/.git" "$APP/dist"

# 8) Fase 3: dejar las carpetas listas (Git no guarda carpetas vacías)
touch "Fase 3/Evidencias Grupales/.gitkeep" "Fase 3/Evidencias Individuales/.gitkeep"
git rm "Fase 3/Evidencias Grupales/momentaneo" "Fase 3/Evidencias Individuales/Momentaneo"

# 9) Revisar, confirmar y subir
git add -A
git status
git status --short | grep -i "\.env"     # NO debe mostrar nada; si muestra algo, no sigas
git commit -m "Ordenar carpetas segun estructura APT y quitar respaldo con credenciales"
git push
```

## 3. Lo que falta subir (no está en el repo ni en Drive)

| Archivo | Carpeta | Quién |
| --- | --- | --- |
| `SANTIBANEZ_VICENTE_3.1_APT122_DiarioReflexionFase3.docx` | Fase 3 / Evidencias Individuales | Vicente |
| `NUNEZ_MARTIN_3.1_APT122_DiarioReflexionFase3.docx` | Fase 3 / Evidencias Individuales | Martín |
| `PLANILLA DE EVALUACIÓN FASE 3.xlsx` | Fase 3 / Evidencias Grupales | La manda el profe por correo |
| `Presentación Final del proyecto (Español).pptx` | Fase 3 / Evidencias Grupales | Equipo |
| `PLANILLA DE EVALUACION AVANCE FASE 2.xlsx` y `PLANILLA DE EVALUACION FINAL FASE 2.xlsx` | Fase 2 / Evidencias Grupales | El profe |
| `Presentación Proyecto.pptx` | Fase 2 / Evidencias Proyecto | Equipo (en Drive solo está el video) |
| `SRS_GMG_Repuestos.xlsx` y `Plan_de_Pruebas_GMG_Repuestos_v2.docx` | Fase 2 / Evidencias Proyecto / Evidencias de documentación | Bajarlos de Drive (Capstone 2026) |
| Diarios 1.x de Martín en `.docx` (hoy solo hay PDF) | Fase 1 / Evidencias Individuales | Martín |

Al cerrar el proyecto, vuelve a copiar el código a `Aplicación` (paso 7) para que quede la versión final.

## 4. Este repo y Claude Code

Este repo sirve para las **evidencias** que pide el profe, no para desarrollar: es público y guarda archivos pesados. Para trabajar con Claude Code, crea un repo **privado** aparte con la carpeta `Gmgrepuestos` del escritorio (tarea F0-1 de `docs/PLAN_PRODUCCION.md`) y, al final, copia una versión limpia aquí.
