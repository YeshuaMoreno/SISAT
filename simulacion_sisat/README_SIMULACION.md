# SISAT — Simulación de Datos de Tesis

**Sistema de Alerta Temprana para Abandono Escolar**  
Módulo de generación masiva de datos sintéticos para análisis, dashboards y tesis.

---

## ¿Qué es esta simulación?

El módulo genera datos sintéticos **estadísticamente coherentes** que replican el comportamiento real de un sistema de alerta temprana escolar. Los datos son completamente ficticios pero respetan distribuciones reales de riesgo, tendencias por municipio y comportamiento de indicadores.

Se usa para:
- Poblar el sistema con volumen suficiente para dashboards reales
- Demostrar escalabilidad del sistema SISAT
- Análisis estadístico para tesis o investigación
- Pruebas de rendimiento con MySQL

---

## ¿Por qué modo TESIS?

| Modo   | Escuelas | Grupos | Alumnos  | Ciclos | Evaluaciones   | Tiempo est. |
|--------|----------|--------|----------|--------|----------------|-------------|
| smoke  | 5        | 10     | ~100     | 1      | ~100           | < 5 s       |
| demo   | 50       | 200    | ~2,000   | 3      | ~12,000        | < 30 s      |
| **tesis** | **500** | **4,000** | **~150,000** | **10** | **~4,500,000** | **30–60 min** |

El modo **tesis** genera un volumen representativo de alto impacto:
- Suficiente para alimentar dashboards Tableau / Power BI con datos reales
- Suficiente para análisis estadístico con Python / pandas
- Importable a MySQL para consultas SQL en tiempo real
- Dimensionado para una institución estatal mediana real

---

## Archivos generados

```
simulacion_sisat/
├── generar_datos_sisat.py       ← Generador principal
├── importar_csv_mysql.py        ← Importador a MySQL
├── consultas_dashboard.sql      ← Consultas SQL listas para usar
├── README_SIMULACION.md         ← Este archivo
├── output/
│   ├── checkpoint.json          ← Progreso (se elimina al terminar)
│   ├── rol.csv
│   ├── indicador_riesgo.csv
│   ├── escuela.csv              ← 500 escuelas de Coahuila
│   ├── usuario.csv              ← Usuarios docentes y directores
│   ├── grupo.csv                ← 4,000 grupos escolares
│   ├── alumno.csv               ← ~150,000 alumnos
│   ├── evaluacion_sisat.csv     ← ~4,500,000 evaluaciones
│   ├── evaluacion_detalle.csv   ← Indicadores activos por evaluación
│   ├── alerta.csv               ← ~700K–1.3M alertas
│   ├── seguimiento.csv          ← ~350K–650K seguimientos
│   └── sisat_dashboard_dataset.csv  ← Dataset denormalizado (1 fila = 1 eval)
│
├── output/dashboard/            ← Solo con --particionado
│   ├── sisat_dashboard_2016_2017.csv
│   ├── sisat_dashboard_2017_2018.csv
│   └── ... (10 archivos)
│
└── output_sample/               ← Muestras de 5 filas para validar formato
```

---

## Cómo ejecutar el generador

### Requisitos

- Python 3.8 o superior (sin dependencias externas — solo stdlib)
- Espacio en disco: **3–5 GB** para modo tesis sin gzip
- Con gzip: **400–700 MB**

### Comandos

```bash
# Modo TESIS (default) — volumen completo para tesis
python simulacion_sisat/generar_datos_sisat.py

# Modo TESIS explícito
python simulacion_sisat/generar_datos_sisat.py --modo tesis

# Modo TESIS con dashboard particionado por ciclo escolar
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado

# Modo TESIS con compresión gzip (recomendado si disco < 5 GB libres)
python simulacion_sisat/generar_datos_sisat.py --modo tesis --gzip

# Modo TESIS particionado + gzip
python simulacion_sisat/generar_datos_sisat.py --modo tesis --particionado --gzip

# Semilla diferente para datos reproducibles distintos
python simulacion_sisat/generar_datos_sisat.py --modo tesis --semilla 2025

# Validación rápida del sistema (segundos)
python simulacion_sisat/generar_datos_sisat.py --modo smoke

# Demo ligero (minutos)
python simulacion_sisat/generar_datos_sisat.py --modo demo

# Borrar datos previos y reiniciar desde cero
python simulacion_sisat/generar_datos_sisat.py --limpiar

# Tamaño de lote personalizado (menor = menos RAM, más lento)
python simulacion_sisat/generar_datos_sisat.py --lote 500
```

### Progreso en consola

```
▶ Fase 6 — Evaluaciones, Alertas, Seguimientos y Dashboard
  Alumnos: 152,000 | Ciclos: 10 | Evals/ciclo: 3 | Evals esperadas: 4,560,000
  Procesando en 152 lotes de hasta 1,000 alumnos...
  [████████████░░░░░░░░░░░░░░░░░░] 40.3%  61/152  ETA: 18m 22s  lotes procesados
```

### Pausa y reanudación

El script guarda `output/checkpoint.json` después de cada lote. Si la laptop se apaga o el script se interrumpe:

```bash
# Simplemente vuelve a ejecutar el mismo comando — retoma donde dejó
python simulacion_sisat/generar_datos_sisat.py --modo tesis
```

---

## Por qué NO abrir el CSV en Excel

Excel tiene un límite de **1,048,576 filas**. El CSV de evaluaciones del modo tesis tiene ~4,500,000 filas. Excel:
- Trunca silenciosamente las filas sobrantes
- Puede colgarse al intentar abrir archivos > 500 MB
- No tiene capacidad de análisis estadístico sobre ese volumen

**Alternativas recomendadas:**

### Tableau / Power BI
```
1. Abre Tableau Desktop / Power BI Desktop
2. Conectar → Archivo de texto
3. Selecciona: simulacion_sisat/output/sisat_dashboard_dataset.csv
4. Tableau detecta automáticamente tipos de columna
5. Arrastra dimensiones y métricas para crear vistas
```

> Con `--particionado`, carga un archivo por ciclo escolar para reducir carga de memoria.

### Python / pandas
```python
import pandas as pd

# Carga eficiente con tipos de dato optimizados
dtype_map = {
    'asistencia_porcentaje': 'float32',
    'promedio_general':      'float32',
    'puntaje_riesgo':        'int8',
    'alerta_generada':       'int8',
    'seguimiento_generado':  'int8',
    'indicador_lectura':     'int8',
    # ... mismo para los otros indicadores
}

df = pd.read_csv(
    'simulacion_sisat/output/sisat_dashboard_dataset.csv',
    dtype=dtype_map,
    parse_dates=['fecha_evaluacion'],
)

# Análisis por municipio
print(df.groupby('municipio')['nivel_riesgo'].value_counts())

# Distribución de riesgo
print(df['nivel_riesgo'].value_counts(normalize=True).mul(100).round(1))
```

Para el CSV completo (~4.5M filas), usa:
```python
# Lectura en chunks para datasets muy grandes
chunks = []
for chunk in pd.read_csv('sisat_dashboard_dataset.csv', chunksize=100_000):
    chunks.append(chunk.groupby('nivel_riesgo').size())
resultado = pd.concat(chunks).groupby(level=0).sum()
```

### MySQL
```sql
-- Importar con el script incluido:
python simulacion_sisat/importar_csv_mysql.py

-- Luego consultar directamente desde dashboard_sisat.php
-- o desde MySQL Workbench / DBeaver
```

---

## Dashboard particionado por ciclo escolar

Con `--particionado`, en lugar de un solo archivo de ~4.5M filas se generan 10 archivos:

```
output/dashboard/sisat_dashboard_2016_2017.csv  (~450K filas)
output/dashboard/sisat_dashboard_2017_2018.csv  (~450K filas)
...
output/dashboard/sisat_dashboard_2025_2026.csv  (~450K filas)
```

**Ventajas:**
- Cada archivo se abre bien en Power BI sin problema
- Puedes cargar solo el ciclo que necesitas en Tableau
- Permite comparaciones año a año directamente

**En Tableau:** Conecta a "Carpeta" y une todos los archivos de la carpeta `dashboard/` con wildcard `sisat_dashboard_*.csv`.

---

## Volumen esperado

| Archivo                    | Filas (tesis)     | Tamaño aprox. |
|----------------------------|-------------------|---------------|
| escuela.csv                | 500               | 50 KB         |
| grupo.csv                  | 4,000             | 200 KB        |
| alumno.csv                 | ~152,000          | 18 MB         |
| evaluacion_sisat.csv       | ~4,560,000        | 450 MB        |
| evaluacion_detalle.csv     | ~10–14M (solo =1) | 600 MB        |
| alerta.csv                 | ~700K–1.2M        | 80 MB         |
| seguimiento.csv            | ~350K–600K        | 40 MB         |
| sisat_dashboard_dataset.csv| ~4,560,000        | 800 MB–1.2 GB |
| **Total**                  | **~20M filas**    | **~3–4 GB**   |
| **Con --gzip**             |                   | **~400–600 MB** |

---

## Distribución de riesgo simulada

| Nivel   | Proporción | Descripción                             |
|---------|------------|-----------------------------------------|
| BAJO    | ~55%       | Sin factores significativos de riesgo   |
| MEDIO   | ~25%       | Algunos indicadores, asistencia regular |
| ALTO    | ~15%       | Múltiples indicadores, baja asistencia  |
| CRÍTICO | ~5%        | Situación grave, intervención urgente   |

---

## Qué hacer si la PC se queda corta

### Poca RAM (< 8 GB)
```bash
# Reducir tamaño de lote (usa menos RAM por iteración)
python generar_datos_sisat.py --modo tesis --lote 200
```

### Poco disco (< 5 GB libres)
```bash
# Comprimir con gzip (reduce a ~10-15% del tamaño original)
python generar_datos_sisat.py --modo tesis --gzip
```

### Laptop lenta o con batería limitada
```bash
# El script guarda checkpoint automáticamente.
# Puedes pausarlo con Ctrl+C y reanudarlo cuando tengas corriente.
# Solo ejecuta el mismo comando nuevamente.
```

### Para exposición sin tiempo de generación
```bash
# Usa modo demo (~30 segundos) para demostración en vivo
python generar_datos_sisat.py --modo demo
```

---

## Recomendación para exposición

Para la exposición se recomienda usar el **modo TESIS** como muestra representativa de alto volumen. El modo estatal completo de 40 años se justifica conceptualmente, pero se genera por particiones anuales para evitar saturar una laptop local. Esta estrategia demuestra escalabilidad sin comprometer el rendimiento del equipo durante la presentación.

**Flujo recomendado para presentación:**

1. Genera modo tesis con `--particionado --gzip` **la noche anterior**
2. Importa a MySQL con `importar_csv_mysql.py`
3. Abre `dashboard_sisat.php` en localhost — ya tiene las gráficas Chart.js
4. Ten listo Tableau o Power BI con el CSV de un ciclo escolar para demostrar análisis cruzado
5. Para el argumento de escalabilidad: muestra `README_SIMULACION.md` y explica que el modo estatal de 40 años se generaría en particiones de 10 archivos por periodo

---

## Credenciales MySQL por defecto

El importador usa las mismas credenciales que `conexion.php`:

```
Host:     localhost
BD:       sisat
Usuario:  root
Clave:    root123
```

Edita las primeras líneas de `importar_csv_mysql.py` si tus credenciales difieren.

---

*SISAT — Sistema de Alerta Temprana para Abandono Escolar*  
*Módulo de Simulación de Datos — v1.0.0*
