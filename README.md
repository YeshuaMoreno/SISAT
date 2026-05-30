# SISAT — Sistema de Alerta Temprana para Abandono Escolar

Sistema web desarrollado en **PHP + MySQL** para automatizar la captura, evaluación, generación de alertas y seguimiento de alumnos con posible riesgo de abandono escolar.

El proyecto nace como una adaptación de un sistema escolar previo, pero fue rediseñado para funcionar como un **SISAT**, enfocado en el análisis de indicadores académicos, asistencia, rendimiento y factores de riesgo.

## Objetivo

Digitalizar y automatizar un proceso que normalmente se realiza de forma manual, permitiendo:

* Registrar alumnos, escuelas y grupos.
* Capturar evaluaciones SISAT.
* Calcular automáticamente el nivel de riesgo.
* Generar alertas tempranas.
* Registrar seguimiento de casos.
* Consultar reportes para dirección, docentes, orientación y SEDU.
* Simular datos históricos para análisis y dashboards.

## Tecnologías utilizadas

* PHP
* MySQL / MariaDB
* PDO
* HTML
* CSS
* JavaScript
* Chart.js
* Python para simulación masiva de datos
* Navicat Premium para modelado y administración de base de datos

## Módulos principales

* Login con roles
* Dashboard principal
* Gestión de alumnos
* Gestión de escuelas
* Gestión de grupos
* Captura SISAT
* Alertas tempranas
* Seguimiento de casos
* Reportes SEDU
* Dashboard de simulación
* Generador de datos históricos para tesis

## Roles del sistema

El sistema contempla los siguientes roles:

* ADMIN
* SEDU
* DIRECTOR
* DOCENTE
* ORIENTADOR
* ALUMNO

Cada rol puede acceder a diferentes módulos según su función dentro del proceso de alerta temprana.

## Modelo general de base de datos

La base de datos utiliza una estructura relacional orientada al seguimiento escolar:

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

El modelo fue diseñado para evitar relaciones circulares innecesarias, separando la autenticación del modelo académico.

## Flujo principal del sistema

1. Se registra la información del alumno.
2. Se captura una evaluación SISAT.
3. El sistema calcula el puntaje de riesgo.
4. Se clasifica el caso como BAJO, MEDIO, ALTO o CRÍTICO.
5. Si el riesgo es MEDIO, ALTO o CRÍTICO, se genera una alerta.
6. El personal correspondiente registra acciones de seguimiento.
7. SEDU o dirección consulta reportes y estadísticas.

## Niveles de riesgo

El sistema clasifica el riesgo de abandono escolar en cuatro niveles:

* BAJO
* MEDIO
* ALTO
* CRÍTICO

El cálculo toma en cuenta asistencia, promedio general e indicadores como rezago académico, inasistencias, conducta, situación socioemocional y riesgo económico/familiar.

## Simulación de datos para tesis

El proyecto incluye un módulo de simulación ubicado en:

```txt
simulacion_sisat/
```

Este módulo permite generar datos históricos para alimentar dashboards y pruebas de rendimiento.

Modos disponibles:

```bash
python simulacion_sisat/generar_datos_sisat.py --modo smoke
python simulacion_sisat/generar_datos_sisat.py --modo demo
python simulacion_sisat/generar_datos_sisat.py --modo tesis
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado
```

El modo recomendado para pruebas de tesis es:

```bash
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado
```

Este modo genera una muestra grande y representativa, pero dividida por ciclos escolares para evitar saturar la computadora local.

## Archivos de simulación

El generador puede crear archivos CSV normalizados como:

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

También puede generar un dataset denormalizado para dashboards:

```txt
sisat_dashboard_dataset.csv
```

## Advertencia sobre archivos grandes

Los archivos generados por el modo TESIS pueden ser demasiado grandes para abrirse en Excel.

Se recomienda analizarlos con:

* MySQL
* Power BI
* Tableau
* Python
* Herramientas de análisis por lotes

Los archivos generados dentro de `simulacion_sisat/output/` no deben subirse al repositorio.

## Instalación básica

1. Clonar el repositorio:

```bash
git clone https://github.com/YeshuaMoreno/SISAT.git
```

2. Copiar el proyecto en el entorno local de XAMPP, por ejemplo:

```txt
C:\xampp\htdocs\SISAT
```

3. Importar el archivo SQL en MySQL/Navicat:

```txt
sisat.sql
```

4. Revisar la conexión en:

```txt
conexion.php
```

5. Abrir el sistema en el navegador:

```txt
http://localhost/SISAT/
```

## Credenciales de prueba

Las credenciales pueden variar según el SQL importado, pero el sistema contempla usuarios como:

```txt
admin
sedu
director
docente
orientador
alumno01
```

## Dashboard de simulación

El archivo:

```txt
dashboard_sisat.php
```

muestra estadísticas generales como:

* Total de alumnos
* Total de escuelas
* Total de evaluaciones
* Total de alertas
* Riesgo bajo
* Riesgo medio
* Riesgo alto
* Riesgo crítico
* Riesgo por municipio
* Alertas por estatus
* Casos críticos por escuela

## Recomendación para exposición

Para la exposición se recomienda usar el modo TESIS como muestra representativa de alto volumen. El modo estatal completo de 40 años se justifica conceptualmente, pero se genera por particiones anuales para evitar saturar una laptop local.

Esta estrategia demuestra escalabilidad sin comprometer el rendimiento del equipo durante la presentación.

## Estado del proyecto

Proyecto académico en desarrollo para demostrar la automatización de un Sistema de Alerta Temprana para Abandono Escolar mediante PHP, MySQL, dashboards y simulación de datos históricos.

## Autor

Roberto Yeshua Moreno Pedraza
