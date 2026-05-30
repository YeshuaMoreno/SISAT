# 🚨 SISAT — Sistema de Alerta Temprana para Abandono Escolar

![PHP](https://img.shields.io/badge/Backend-PHP-777BB4?style=for-the-badge\&logo=php\&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge\&logo=mysql\&logoColor=white)
![Python](https://img.shields.io/badge/Data%20Simulation-Python-3776AB?style=for-the-badge\&logo=python\&logoColor=white)
![Chart.js](https://img.shields.io/badge/Dashboard-Chart.js-FF6384?style=for-the-badge\&logo=chartdotjs\&logoColor=white)
![Status](https://img.shields.io/badge/Status-Prototipo%20Funcional-brightgreen?style=for-the-badge)

---

## 📌 Descripción

**SISAT** es un sistema web desarrollado en **PHP + MySQL** para automatizar el proceso de detección, registro, análisis y seguimiento de alumnos con posible riesgo de abandono escolar.

El sistema permite capturar evaluaciones escolares, calcular automáticamente el nivel de riesgo del alumno, generar alertas tempranas y registrar acciones de seguimiento por parte de docentes, orientación, dirección y autoridades educativas.

Además, el proyecto incorpora un módulo de **simulación masiva de datos históricos**, desarrollado en Python, para probar el comportamiento del sistema con un volumen representativo de información a nivel tesis.

---

## 🎯 Objetivo general

Automatizar un proceso que normalmente se realiza de manera manual, permitiendo que la información fluya desde la captura escolar hasta el análisis institucional mediante reportes, alertas y dashboards.

El sistema busca apoyar la toma de decisiones tempranas mediante indicadores relacionados con asistencia, rendimiento académico, rezago escolar y factores de riesgo socioemocional o familiar.

---

## ✅ Estado del proyecto

| Módulo                       | Estado               |
| ---------------------------- | -------------------- |
| Login y control de roles     | 🟢 Funcional         |
| CRUD de alumnos              | 🟢 Funcional         |
| CRUD de escuelas             | 🟢 Funcional         |
| CRUD de grupos               | 🟢 Funcional         |
| Captura SISAT                | 🟢 Funcional         |
| Cálculo automático de riesgo | 🟢 Funcional         |
| Alertas tempranas            | 🟢 Funcional         |
| Seguimiento de casos         | 🟢 Funcional         |
| Dashboard principal          | 🟢 Funcional         |
| Dashboard de simulación      | 🟢 Funcional         |
| Simulación masiva modo tesis | 🟢 Funcional         |
| Reportes institucionales     | 🟢 Funcional         |
| Exportación PDF/Excel        | 🟢 Funcional         |

---

## 🧰 Tecnologías utilizadas

### ⚙️ Backend

* PHP
* PDO
* MySQL / MariaDB
* Sesiones PHP
* Consultas preparadas

### 🗄️ Base de datos

* MySQL
* Modelo Entidad-Relación
* Vistas y consultas SQL
* Tablas resumen para dashboards
* Índices para consultas de alto volumen

### 🎨 Frontend

* HTML5
* CSS3
* JavaScript
* Sidebar por roles
* Diseño institucional responsivo

### 📊 Análisis y simulación

* Python
* CSV
* Chart.js
* Simulación por streaming
* Datos históricos sintéticos
* Dashboard de alto volumen

---

## 🏗️ Arquitectura general

El sistema utiliza una arquitectura web tradicional:

```txt
Usuario
  ↓
Interfaz PHP / HTML / CSS
  ↓
Control de sesión y permisos
  ↓
Lógica SISAT
  ↓
Base de datos MySQL
  ↓
Reportes, alertas y dashboards
```

---

## 🗄️ Modelo general de base de datos

El modelo se divide en dos bloques principales.

### 🔐 Autenticación

```txt
rol → usuario
```

### 🏫 Modelo académico SISAT

```txt
escuela → grupo → alumno → evaluacion_sisat → alerta → seguimiento
                         ↓
                  evaluacion_detalle → indicador_riesgo
```

La autenticación se mantiene separada del modelo académico para reducir dependencias innecesarias y facilitar la interpretación del diagrama Entidad-Relación.

---

## 📦 Tablas principales

| Tabla                     | Función                              |
| ------------------------- | ------------------------------------ |
| `rol`                     | Catálogo de roles del sistema        |
| `usuario`                 | Acceso y autenticación               |
| `escuela`                 | Registro de instituciones educativas |
| `grupo`                   | Grupos escolares por escuela y ciclo |
| `alumno`                  | Información del estudiante           |
| `indicador_riesgo`        | Catálogo de indicadores SISAT        |
| `evaluacion_sisat`        | Evaluación general del alumno        |
| `evaluacion_detalle`      | Indicadores activos por evaluación   |
| `alerta`                  | Alertas generadas por riesgo         |
| `seguimiento`             | Acciones de atención y seguimiento   |
| `resumen_dashboard_sisat`        | Tabla resumen para dashboards              |
| `resumen_riesgo_municipio`       | Resumen de riesgo agrupado por municipio   |
| `resumen_riesgo_escuela`         | Resumen de riesgo agrupado por escuela     |
| `resumen_alertas_estatus`        | Conteo de alertas por estatus              |
| `resumen_top_escuelas_criticas`  | Top escuelas con más casos críticos        |

---

## 👥 Roles del sistema

El sistema contempla los siguientes roles:

* **ADMIN**
* **SEDU**
* **DIRECTOR**
* **DOCENTE**
* **ORIENTADOR**
* **ALUMNO**

Cada rol tiene acceso a módulos específicos de acuerdo con su función dentro del proceso de alerta temprana.

---

## 🔁 Flujo principal del sistema

1. Se registra la información del alumno.
2. El alumno se asocia a una escuela y grupo.
3. Se captura una evaluación SISAT.
4. El sistema calcula el puntaje de riesgo.
5. El alumno se clasifica en un nivel de riesgo.
6. Si el riesgo es medio, alto o crítico, se genera una alerta.
7. Se registra seguimiento del caso.
8. Dirección o SEDU consultan reportes y estadísticas.

---

## ⚠️ Niveles de riesgo

| Nivel          | Descripción                                      |
| -------------- | ------------------------------------------------ |
| 🟢 **BAJO**    | Alumno sin factores graves de riesgo             |
| 🟡 **MEDIO**   | Alumno con señales iniciales de riesgo           |
| 🟠 **ALTO**    | Alumno con múltiples factores de riesgo          |
| 🔴 **CRÍTICO** | Alumno con alta probabilidad de abandono escolar |

---

## 🧮 Cálculo de riesgo

El sistema calcula el riesgo considerando:

* Porcentaje de asistencia.
* Promedio general.
* Rezago en lectura.
* Rezago en escritura.
* Rezago en cálculo mental.
* Inasistencias frecuentes.
* Bajo rendimiento académico.
* Problemas de conducta.
* Situación socioemocional.
* Riesgo económico o familiar.

Reglas generales utilizadas:

```txt
Asistencia < 80%        → aumenta riesgo
Promedio < 7            → aumenta riesgo
Indicadores activos     → suman puntaje
Factores socioemocionales/económicos → mayor peso
```

Clasificación general:

```txt
0 - 2 puntos   → BAJO
3 - 5 puntos   → MEDIO
6 - 8 puntos   → ALTO
9+ puntos      → CRÍTICO
```

---

## 📊 Dashboard de simulación

El sistema incluye un dashboard especializado para visualizar datos de alto volumen.

Archivo principal:

```txt
dashboard_sisat.php
```

El dashboard muestra:

* Total de alumnos.
* Total de escuelas.
* Total de evaluaciones.
* Total de alertas.
* Alertas abiertas.
* Alertas cerradas.
* Riesgo bajo.
* Riesgo medio.
* Riesgo alto.
* Riesgo crítico.
* Distribución por municipio.
* Alertas por estatus.
* Casos críticos por escuela.
* Serie histórica por ciclo escolar.

Para evitar consultas pesadas sobre millones de registros, el dashboard utiliza una tabla resumen:

```txt
resumen_dashboard_sisat
```

Esta tabla se actualiza mediante:

```txt
actualizar_resumen_dashboard.sql
```

---

## 🧪 Simulación masiva de datos

El proyecto incluye un módulo de simulación ubicado en:

```txt
simulacion_sisat/
```

Este módulo genera datos sintéticos para pruebas de rendimiento, análisis y exposición académica.

### Modos disponibles

```bash
python simulacion_sisat/generar_datos_sisat.py --modo smoke
python simulacion_sisat/generar_datos_sisat.py --modo demo
python simulacion_sisat/generar_datos_sisat.py --modo tesis
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado
```

### Modo recomendado para tesis

```bash
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado
```

Este modo genera una muestra grande y representativa, dividida por ciclos escolares para evitar saturar la computadora local.

---

## 📈 Volumen de datos generado para prueba de tesis

Para validar escalabilidad, se generó e importó una muestra masiva con los siguientes volúmenes aproximados:

| Entidad                | Registros |
| ---------------------- | --------: |
| Alumnos                |   151,909 |
| Escuelas               |       500 |
| Evaluaciones SISAT     | 4,558,230 |
| Detalles de evaluación | 6,389,739 |
| Alertas                | 1,147,056 |
| Seguimientos           |   574,033 |

Después de recalcular los niveles de riesgo, la distribución general de evaluaciones quedó:

| Nivel de riesgo | Registros |
| --------------- | --------: |
| BAJO            | 2,479,732 |
| MEDIO           |   716,103 |
| ALTO            |   389,575 |
| CRÍTICO         |   972,820 |

Para el dashboard institucional se utiliza la última evaluación por alumno, mostrando el estado actual de la población simulada.

---

## 📁 Estructura del proyecto

```txt
SISAT/
├── alertas.php
├── alumnos.php
├── captura_sisat.php
├── conexion.php
├── dashboard.php
├── dashboard_sisat.php
├── escuelas.php
├── estilos.css
├── funciones.php
├── grupos.php
├── index.php
├── login.php
├── logout.php
├── reportes.php
├── seguimiento.php
├── sidebar.php
├── sisat.sql
├── usuarios.php
├── actualizar_resumen_dashboard.sql
├── actualizar_resumen_reportes.sql
├── exportar_excel.php
├── exportar_pdf.php
├── reparar_nivel_riesgo.sql
├── simulacion_sisat/
│   ├── generar_datos_sisat.py
│   ├── importar_csv_mysql.py
│   ├── README_SIMULACION.md
│   ├── consultas_dashboard.sql
│   ├── output/
│   └── output_sample/
└── README.md
```

---

## ⚙️ Instalación y ejecución

### 1. Clonar el repositorio

```bash
git clone https://github.com/YeshuaMoreno/SISAT.git
```

### 2. Entrar al proyecto

Si se usa XAMPP:

```bash
cd /c/xampp/htdocs/SISAT
```

En CMD de Windows:

```bat
cd /d C:\xampp\htdocs\SISAT
```

### 3. Importar la base de datos

Importar el archivo:

```txt
sisat.sql
```

Con MySQL:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < sisat.sql
```

O desde Navicat/phpMyAdmin ejecutando el contenido de `sisat.sql`.

### 4. Configurar conexión

Editar:

```txt
conexion.php
```

Ejemplo para MySQL local sin contraseña:

```php
$host = "127.0.0.1";
$db   = "sisat";
$user = "root";
$pass = "";
```

Ejemplo para MySQL local con contraseña:

```php
$host = "127.0.0.1";
$db   = "sisat";
$user = "root";
$pass = "root";
```

### 5. Abrir el sistema

```txt
http://localhost/SISAT/
```

O directamente:

```txt
http://localhost/SISAT/login.php
```

---

## 🔐 Credenciales de prueba

Las credenciales pueden variar según el SQL importado. El sistema contempla usuarios base como:

```txt
admin
sedu
director
docente
orientador
alumno01
```

En la versión de prueba, varios usuarios semilla utilizan la contraseña:

```txt
password
```

---

## 🚀 Generar datos modo tesis

Desde la carpeta del proyecto:

```bash
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado
```

Los archivos CSV se generan en:

```txt
simulacion_sisat/output/
```

---

## 📥 Importar CSVs generados a MySQL

Instalar el conector de MySQL para Python:

```bash
python -m pip install mysql-connector-python
```

Ejecutar el importador:

```bash
python simulacion_sisat/importar_csv_mysql.py
```

Si el campo `NIVEL_RIESGO` queda vacío por incompatibilidad de importación CSV/ENUM, ejecutar:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < reparar_nivel_riesgo.sql
```

Después actualizar la tabla resumen:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < actualizar_resumen_dashboard.sql
```

---

## 📊 Actualizar resumen del dashboard

Después de importar datos nuevos o reparar niveles de riesgo, ejecutar:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < actualizar_resumen_dashboard.sql
```

Esto actualiza la tabla:

```txt
resumen_dashboard_sisat
```

El dashboard usa esta tabla para cargar rápidamente sin consultar millones de registros en cada visita.

---

## 📋 Reportes y exportación

### Reportes institucionales

`reportes.php` ofrece una vista completa de reportes institucionales para SEDU, Dirección y Admin:

* Resumen general con 10 indicadores clave.
* Distribución de riesgo por municipio.
* Distribución de riesgo por escuela (filtrable por municipio y nivel).
* Alertas por estatus con porcentajes.
* Top escuelas con más casos críticos abiertos.
* Filtros: municipio, nivel educativo y cantidad de resultados.

Los reportes leen desde **tablas resumen** para garantizar velocidad con millones de registros.
Para generar las tablas resumen de reportes ejecutar:

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root < actualizar_resumen_reportes.sql
```

### Exportación Excel

`exportar_excel.php` genera archivos `.xls` compatibles con Microsoft Excel y LibreOffice Calc:

```txt
exportar_excel.php?tipo=general
exportar_excel.php?tipo=municipio
exportar_excel.php?tipo=escuela
exportar_excel.php?tipo=alertas
exportar_excel.php?tipo=criticos
```

Acepta filtros opcionales: `&municipio=Saltillo&nivel=SECUNDARIA`

No requiere librerías externas. Usa HTML con `Content-Type: application/vnd.ms-excel`.

### Exportación PDF

`exportar_pdf.php` genera reportes en PDF:

* **Con Dompdf instalado vía Composer**: genera PDF real descargable.
* **Sin Dompdf** (caso por defecto): genera vista HTML imprimible con CSS `@print` y botón "Imprimir / Guardar PDF". El usuario guarda como PDF desde el diálogo de impresión del navegador.

```txt
exportar_pdf.php?tipo=general
exportar_pdf.php?tipo=municipio
exportar_pdf.php?tipo=escuela
exportar_pdf.php?tipo=alertas
exportar_pdf.php?tipo=criticos
```

---

## ⚠️ Advertencia sobre archivos grandes

Los archivos generados dentro de:

```txt
simulacion_sisat/output/
```

pueden ser muy grandes y no deben subirse al repositorio.

El repositorio solo debe incluir:

* Código fuente.
* Scripts de generación.
* Scripts SQL.
* Documentación.
* Muestras pequeñas en `output_sample/`.

---

## 📌 Recomendación para exposición

Para una exposición académica se recomienda mostrar:

1. Login del sistema.
2. Módulo de alumnos.
3. Captura SISAT.
4. Generación de alerta.
5. Seguimiento.
6. Dashboard de simulación.
7. Conteos masivos cargados en MySQL.
8. Explicación del modelo Entidad-Relación.
9. Justificación del uso de tabla resumen para rendimiento.

El escenario estatal completo puede justificarse conceptualmente, pero para ejecución local se recomienda usar datos particionados y muestras representativas de alto volumen.

---

## 🧾 Comandos útiles

### Entrar al proyecto desde Git Bash

```bash
cd /c/xampp/htdocs/SISAT
```

### Ver estado de Git

```bash
git status
```

### Subir cambios

```bash
git add .
git commit -m "docs: update SISAT README"
git push
```

### Ver conteos principales en MySQL

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root sisat -e "SELECT COUNT(*) alumnos FROM alumno; SELECT COUNT(*) evaluaciones FROM evaluacion_sisat; SELECT COUNT(*) alertas FROM alerta;"
```

### Ver distribución de riesgo

```bat
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root sisat -e "SELECT NIVEL_RIESGO, COUNT(*) total FROM evaluacion_sisat GROUP BY NIVEL_RIESGO;"
```

---

## 🚧 Mejoras futuras

* Paginación avanzada para alumnos.
* Bitácora de acciones por usuario.
* Filtros avanzados por municipio, escuela y ciclo escolar.
* Optimización de reportes para grandes volúmenes.
* Visualización geográfica por municipio.
* Integración con Power BI o Tableau.
* Separación de configuración sensible mediante archivo `.env`.

---

## 👨‍💻 Autor

**Roberto Yeshua Moreno Pedraza**
Ingeniero en Sistemas Computacionales

---

## ⭐ Nota académica

Este proyecto fue desarrollado con fines académicos como propuesta de automatización para un Sistema de Alerta Temprana para Abandono Escolar, integrando base de datos relacional, lógica de riesgo, dashboards, alertas y simulación de datos históricos.

El enfoque principal es demostrar cómo un proceso manual puede transformarse en un sistema digital capaz de apoyar la toma de decisiones institucionales mediante datos, indicadores y seguimiento oportuno.
