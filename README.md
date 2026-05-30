# 🚨 SISAT — Sistema de Alerta Temprana para Abandono Escolar

![PHP](https://img.shields.io/badge/Backend-PHP-777BB4?style=for-the-badge\&logo=php\&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge\&logo=mysql\&logoColor=white)
![Python](https://img.shields.io/badge/Data%20Simulation-Python-3776AB?style=for-the-badge\&logo=python\&logoColor=white)
![Chart.js](https://img.shields.io/badge/Dashboard-Chart.js-FF6384?style=for-the-badge\&logo=chartdotjs\&logoColor=white)
![Status](https://img.shields.io/badge/Status-En%20Desarrollo-yellow?style=for-the-badge)

---

## 📌 Descripción

**SISAT** es un sistema web desarrollado en **PHP + MySQL** para automatizar el proceso de detección, registro, análisis y seguimiento de alumnos con posible riesgo de abandono escolar.

El sistema permite capturar evaluaciones académicas y de contexto, calcular automáticamente el nivel de riesgo del alumno, generar alertas tempranas y registrar acciones de seguimiento por parte de docentes, orientación, dirección y autoridades educativas.

Este proyecto está orientado a un escenario académico de tesis, incorporando además un módulo de **simulación masiva de datos históricos** para análisis, dashboards y pruebas de escalabilidad.

---

## 🎯 Objetivo del proyecto

Automatizar un proceso que normalmente se realiza de manera manual, permitiendo:

* Registrar escuelas, grupos y alumnos.
* Capturar evaluaciones SISAT.
* Calcular el nivel de riesgo escolar.
* Generar alertas tempranas.
* Registrar seguimiento de casos.
* Consultar reportes institucionales.
* Simular datos históricos para análisis estatal.
* Visualizar indicadores mediante dashboards.

---

## 📌 Estado del proyecto

* 🟢 **Login y roles** → FUNCIONAL
* 🟢 **CRUD de alumnos** → FUNCIONAL
* 🟢 **CRUD de escuelas** → FUNCIONAL
* 🟢 **CRUD de grupos** → FUNCIONAL
* 🟢 **Captura SISAT** → FUNCIONAL
* 🟢 **Alertas tempranas** → FUNCIONAL
* 🟢 **Seguimiento de casos** → FUNCIONAL
* 🟡 **Dashboard principal** → EN MEJORA
* 🟡 **Dashboard de simulación** → EN DESARROLLO
* 🟡 **Simulación masiva modo TESIS** → EN DESARROLLO
* 🔵 **Reportes SEDU** → BASE IMPLEMENTADA

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
* MariaDB
* Navicat Premium
* Modelo Entidad-Relación
* Vistas SQL para reportes

### 🎨 Frontend

* HTML5
* CSS3
* JavaScript
* Diseño institucional responsivo
* Sidebar por roles

### 📊 Dashboards y simulación

* Chart.js
* Python
* CSV
* Simulación por streaming
* Datos históricos sintéticos

---

## 🏗️ Arquitectura general

El sistema mantiene una arquitectura web tradicional basada en PHP y MySQL:

```txt
Usuario
  ↓
Interfaz PHP / HTML / CSS
  ↓
Control de sesiones y roles
  ↓
Lógica SISAT
  ↓
Base de datos MySQL
  ↓
Reportes / Dashboards / Seguimiento
```

---

## 🗄️ Modelo general de base de datos

El modelo SISAT se organiza en dos bloques principales:

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

La autenticación se mantiene separada del modelo académico para evitar relaciones circulares innecesarias y facilitar la explicación del diagrama Entidad-Relación.

---

## 📦 Tablas principales

* `rol`
* `usuario`
* `escuela`
* `grupo`
* `alumno`
* `indicador_riesgo`
* `evaluacion_sisat`
* `evaluacion_detalle`
* `alerta`
* `seguimiento`

---

## 👥 Roles del sistema

El sistema contempla los siguientes roles:

* **ADMIN**
* **SEDU**
* **DIRECTOR**
* **DOCENTE**
* **ORIENTADOR**
* **ALUMNO**

Cada rol puede acceder a diferentes módulos de acuerdo con su función dentro del proceso de alerta temprana.

---

## 🔁 Flujo principal del sistema

1. Se registra la información del alumno.
2. Se asocia el alumno a una escuela y grupo.
3. Se captura una evaluación SISAT.
4. El sistema calcula el puntaje de riesgo.
5. Se clasifica el caso como:

   * BAJO
   * MEDIO
   * ALTO
   * CRÍTICO
6. Si el riesgo es MEDIO, ALTO o CRÍTICO, se genera una alerta.
7. Se registra seguimiento del caso.
8. Dirección o SEDU consulta reportes y estadísticas.

---

## ⚠️ Niveles de riesgo

El sistema clasifica el riesgo de abandono escolar en cuatro niveles:

| Nivel      | Descripción                                      |
| ---------- | ------------------------------------------------ |
| 🟢 BAJO    | Alumno sin factores graves de riesgo             |
| 🟡 MEDIO   | Alumno con señales iniciales de riesgo           |
| 🟠 ALTO    | Alumno con múltiples factores de riesgo          |
| 🔴 CRÍTICO | Alumno con alta probabilidad de abandono escolar |

---

## 🧮 Cálculo de riesgo

La evaluación SISAT toma en cuenta factores como:

* Porcentaje de asistencia.
* Promedio general.
* Rezago en lectura.
* Rezago en escritura.
* Rezago en cálculo mental.
* Problemas de conducta.
* Situación socioemocional.
* Riesgo económico o familiar.

Reglas generales:

```txt
Asistencia < 80%        → aumenta riesgo
Promedio < 7            → aumenta riesgo
Indicadores activos     → suman puntaje
Factores socioemocionales/económicos → mayor peso
```

---

## 📊 Dashboards

El sistema incluye dashboards para visualizar información clave:

* Total de alumnos.
* Total de escuelas.
* Total de evaluaciones.
* Total de alertas.
* Alertas abiertas.
* Riesgo bajo.
* Riesgo medio.
* Riesgo alto.
* Riesgo crítico.
* Casos por municipio.
* Casos por escuela.
* Alertas por estatus.
* Casos críticos.

---

## 🧪 Simulación masiva de datos

El proyecto incluye un módulo de simulación ubicado en:

```txt
simulacion_sisat/
```

Este módulo permite generar datos sintéticos para pruebas de rendimiento, dashboards y análisis de tesis.

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

### 2. Copiar el proyecto a XAMPP

Ejemplo:

```txt
C:\xampp\htdocs\SISAT
```

### 3. Importar la base de datos

Importar el archivo:

```txt
sisat.sql
```

En MySQL, MariaDB o Navicat Premium.

### 4. Configurar conexión

Revisar el archivo:

```txt
conexion.php
```

Ejemplo:

```php
$host = "localhost";
$db   = "sisat";
$user = "root";
$pass = "root123";
```

### 5. Abrir el sistema

```txt
http://localhost/SISAT/
```

---

## 🔐 Credenciales de prueba

Las credenciales pueden variar según el SQL importado, pero el sistema contempla usuarios como:

```txt
admin
sedu
director
docente
orientador
alumno01
```

---

## 📈 Dataset para dashboards

La simulación puede generar CSVs normalizados como:

* `rol.csv`
* `usuario.csv`
* `escuela.csv`
* `grupo.csv`
* `alumno.csv`
* `indicador_riesgo.csv`
* `evaluacion_sisat.csv`
* `evaluacion_detalle.csv`
* `alerta.csv`
* `seguimiento.csv`

Y también un dataset denormalizado para análisis:

```txt
sisat_dashboard_dataset.csv
```

Este archivo puede ser utilizado en:

* Power BI
* Tableau
* Python
* MySQL
* Herramientas de análisis por lotes

---

## ⚠️ Advertencia sobre archivos grandes

Los archivos generados por el modo TESIS pueden ser demasiado grandes para abrirse en Excel.

Por esa razón, los archivos generados dentro de:

```txt
simulacion_sisat/output/
```

no deben subirse al repositorio.

Se recomienda subir únicamente:

* Scripts de generación.
* Documentación.
* Archivos SQL.
* Muestras pequeñas.
* Código fuente.

---

## 📌 Recomendación para exposición

Para una exposición académica se recomienda utilizar el modo **TESIS** como muestra representativa de alto volumen.

El escenario estatal completo de 40 años puede justificarse conceptualmente, pero debe generarse por particiones anuales o por ciclos escolares para evitar saturar una laptop local.

Esta estrategia demuestra escalabilidad sin comprometer el rendimiento durante la presentación.

---

## 🚧 Pendientes y mejoras futuras

* Mejorar filtros avanzados en dashboards.
* Agregar exportación PDF y Excel.
* Implementar autenticación más robusta.
* Agregar bitácora de acciones.
* Mejorar permisos específicos por rol.
* Agregar gráficas comparativas por ciclo escolar.
* Preparar documentación técnica completa.
* Integrar datasets simulados con Power BI o Tableau.

---

## 👨‍💻 Autor

**Roberto Yeshua Moreno Pedraza**
Ingeniería en Sistemas Computacionales

---

## ⭐ Nota

Este proyecto fue desarrollado con fines académicos como propuesta de automatización para un Sistema de Alerta Temprana para Abandono Escolar, integrando base de datos relacional, lógica de riesgo, dashboards y simulación de datos históricos.
