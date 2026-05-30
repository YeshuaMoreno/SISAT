#!/usr/bin/env python3
"""
importar_csv_mysql.py
SISAT — Importador de CSVs simulados a MySQL.

Intenta primero LOAD DATA LOCAL INFILE (rápido, masivo).
Si falla, usa INSERT por lotes de 500 filas.

Uso:
    python importar_csv_mysql.py
    python importar_csv_mysql.py --solo evaluacion_sisat
    python importar_csv_mysql.py --truncar          # vacía tablas antes
    python importar_csv_mysql.py --csv-dir otra/ruta
    python importar_csv_mysql.py --dry-run          # muestra qué haría
"""

import argparse
import csv
import gzip
import os
import sys
import time
from pathlib import Path

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURACIÓN — Edita aquí tus credenciales MySQL
# ─────────────────────────────────────────────────────────────────────────────

HOST     = "localhost"
PORT     = 3306
USER     = "root"
PASSWORD = "root123"
DATABASE = "sisat"
CSV_DIR  = "simulacion_sisat/output"

# ─────────────────────────────────────────────────────────────────────────────
# ORDEN DE IMPORTACIÓN (respeta claves foráneas)
# ─────────────────────────────────────────────────────────────────────────────

TABLAS = [
    {
        "tabla": "rol",
        "csv":   "rol.csv",
        "cols":  ["ID_ROL", "NOMBRE_ROL"],
        "pk":    "ID_ROL",
    },
    {
        "tabla": "indicador_riesgo",
        "csv":   "indicador_riesgo.csv",
        "cols":  ["ID_INDICADOR", "NOMBRE_INDICADOR", "DESCRIPCION", "PESO", "ACTIVO"],
        "pk":    "ID_INDICADOR",
    },
    {
        "tabla": "escuela",
        "csv":   "escuela.csv",
        "cols":  ["ID_ESCUELA", "CCT", "NOMBRE_ESCUELA", "MUNICIPIO",
                  "NIVEL", "ZONA_ESCOLAR", "ACTIVA"],
        "pk":    "ID_ESCUELA",
    },
    {
        "tabla": "usuario",
        "csv":   "usuario.csv",
        "cols":  ["ID_USUARIO", "USUARIO", "CORREO", "PWD",
                  "ID_ROL", "ACTIVO", "FECHA_CREACION"],
        "pk":    "ID_USUARIO",
    },
    {
        "tabla": "grupo",
        "csv":   "grupo.csv",
        "cols":  ["ID_GRUPO", "ID_ESCUELA", "GRADO", "GRUPO",
                  "CICLO_ESCOLAR", "ID_DOCENTE"],
        "pk":    "ID_GRUPO",
    },
    {
        "tabla": "alumno",
        "csv":   "alumno.csv",
        "cols":  ["ID_ALUMNO", "MATRICULA", "CURP", "NOMBRE",
                  "APELLIDO_PATERNO", "APELLIDO_MATERNO", "FECHA_NACIMIENTO",
                  "SEXO", "TELEFONO", "CORREO", "DIRECCION",
                  "ID_GRUPO", "ID_USUARIO", "ACTIVO"],
        "pk":    "ID_ALUMNO",
    },
    {
        "tabla": "evaluacion_sisat",
        "csv":   "evaluacion_sisat.csv",
        "cols":  ["ID_EVALUACION", "ID_ALUMNO", "ID_USUARIO_CAPTURA",
                  "FECHA_EVALUACION", "ASISTENCIA_PORCENTAJE",
                  "PROMEDIO_GENERAL", "OBSERVACIONES",
                  "PUNTAJE_RIESGO", "NIVEL_RIESGO"],
        "pk":    "ID_EVALUACION",
    },
    {
        "tabla": "evaluacion_detalle",
        "csv":   "evaluacion_detalle.csv",
        "cols":  ["ID_DETALLE", "ID_EVALUACION", "ID_INDICADOR", "VALOR", "OBSERVACION"],
        "pk":    "ID_DETALLE",
    },
    {
        "tabla": "alerta",
        "csv":   "alerta.csv",
        "cols":  ["ID_ALERTA", "ID_ALUMNO", "ID_EVALUACION", "NIVEL_RIESGO",
                  "ESTATUS", "FECHA_CREACION", "FECHA_CIERRE", "DESCRIPCION"],
        "pk":    "ID_ALERTA",
    },
    {
        "tabla": "seguimiento",
        "csv":   "seguimiento.csv",
        "cols":  ["ID_SEGUIMIENTO", "ID_ALERTA", "ID_USUARIO",
                  "FECHA_SEGUIMIENTO", "ACCION_REALIZADA",
                  "RESULTADO", "PROXIMA_ACCION", "FECHA_PROXIMA_ACCION"],
        "pk":    "ID_SEGUIMIENTO",
    },
]

LOTE_INSERT = 500   # filas por INSERT en el modo fallback


# ─────────────────────────────────────────────────────────────────────────────
# DETECTAR DRIVER MYSQL
# ─────────────────────────────────────────────────────────────────────────────

def obtener_driver():
    try:
        import mysql.connector
        return "connector", mysql.connector
    except ImportError:
        pass
    try:
        import pymysql
        return "pymysql", pymysql
    except ImportError:
        pass
    return None, None


def conectar(driver_nombre, driver):
    kwargs = dict(host=HOST, port=PORT, user=USER, password=PASSWORD,
                  database=DATABASE, charset="utf8mb4")
    if driver_nombre == "connector":
        kwargs["allow_local_infile"] = True
        conn = driver.connect(**kwargs)
    else:
        conn = driver.connect(**kwargs)
    return conn


# ─────────────────────────────────────────────────────────────────────────────
# HELPERS
# ─────────────────────────────────────────────────────────────────────────────

def _fmt(s):
    s = int(s)
    if s < 60:   return f"{s}s"
    if s < 3600: return f"{s//60}m {s%60:02d}s"
    return f"{s//3600}h {(s%3600)//60}m"


def contar_filas(csv_path: Path) -> int:
    """Cuenta filas de un CSV (sin contar encabezado)."""
    opener = gzip.open if str(csv_path).endswith(".gz") else open
    try:
        with opener(csv_path, "rt", encoding="utf-8") as f:
            return sum(1 for _ in f) - 1
    except Exception:
        return 0


def abrir_csv(csv_path: Path):
    if str(csv_path).endswith(".gz"):
        return gzip.open(csv_path, "rt", encoding="utf-8")
    return open(csv_path, "r", encoding="utf-8", newline="")


def csv_path_real(csv_dir: Path, nombre: str) -> Path:
    """Devuelve la ruta real del CSV (normal o .gz)."""
    p = csv_dir / nombre
    if p.exists():
        return p
    pg = csv_dir / (nombre + ".gz")
    if pg.exists():
        return pg
    return p   # retorna aunque no exista (se detectará después)


# ─────────────────────────────────────────────────────────────────────────────
# LOAD DATA LOCAL INFILE
# ─────────────────────────────────────────────────────────────────────────────

def importar_load_data(conn, cursor, tabla_cfg: dict, csv_path: Path, dry: bool) -> bool:
    """
    Intenta LOAD DATA LOCAL INFILE.
    Retorna True si tuvo éxito, False si falló (para hacer fallback).
    """
    if str(csv_path).endswith(".gz"):
        print("    ⚠ LOAD DATA LOCAL INFILE no soporta .gz directamente."
              " Usando INSERT fallback.")
        return False

    abs_path = str(csv_path.resolve()).replace("\\", "/")
    cols_str  = ", ".join(f"`{c}`" for c in tabla_cfg["cols"])
    sql = f"""
        LOAD DATA LOCAL INFILE '{abs_path}'
        INTO TABLE `{tabla_cfg['tabla']}`
        CHARACTER SET utf8mb4
        FIELDS TERMINATED BY ','
        OPTIONALLY ENCLOSED BY '"'
        LINES TERMINATED BY '\\n'
        IGNORE 1 LINES
        ({cols_str});
    """
    if dry:
        print(f"    [DRY-RUN] LOAD DATA: {abs_path}")
        return True
    try:
        cursor.execute("SET foreign_key_checks = 0;")
        cursor.execute(sql)
        conn.commit()
        cursor.execute("SET foreign_key_checks = 1;")
        rows = cursor.rowcount
        print(f"    ✓ LOAD DATA: {rows:,} filas")
        return True
    except Exception as e:
        print(f"    ⚠ LOAD DATA falló: {e}")
        conn.rollback()
        return False


# ─────────────────────────────────────────────────────────────────────────────
# INSERT POR LOTES (fallback)
# ─────────────────────────────────────────────────────────────────────────────

def importar_insert(conn, cursor, tabla_cfg: dict, csv_path: Path, dry: bool):
    cols     = tabla_cfg["cols"]
    tabla    = tabla_cfg["tabla"]
    ph       = ", ".join(["%s"] * len(cols))
    cols_str = ", ".join(f"`{c}`" for c in cols)
    sql      = f"INSERT IGNORE INTO `{tabla}` ({cols_str}) VALUES ({ph})"

    total_est = contar_filas(csv_path)
    importadas = 0
    t0 = time.time()

    def none_if_empty(v):
        return None if v == "" else v

    with abrir_csv(csv_path) as f:
        reader = csv.DictReader(f)
        lote = []
        for row in reader:
            lote.append(tuple(none_if_empty(row.get(c, "")) for c in cols))
            if len(lote) >= LOTE_INSERT:
                if not dry:
                    try:
                        cursor.execute("SET foreign_key_checks = 0;")
                        cursor.executemany(sql, lote)
                        conn.commit()
                        cursor.execute("SET foreign_key_checks = 1;")
                    except Exception as e:
                        conn.rollback()
                        print(f"\n    ✗ Error en lote: {e}")
                importadas += len(lote)
                lote = []
                pct = importadas / total_est * 100 if total_est else 0
                eta = (time.time()-t0)/importadas*(total_est-importadas) if importadas else 0
                print(
                    f"\r    {importadas:,}/{total_est:,} ({pct:.1f}%) ETA {_fmt(eta)}   ",
                    end="", flush=True,
                )
        if lote:
            if not dry:
                try:
                    cursor.execute("SET foreign_key_checks = 0;")
                    cursor.executemany(sql, lote)
                    conn.commit()
                    cursor.execute("SET foreign_key_checks = 1;")
                except Exception as e:
                    conn.rollback()
                    print(f"\n    ✗ Error en último lote: {e}")
            importadas += len(lote)

    elapsed = time.time() - t0
    print(f"\r    ✓ INSERT: {importadas:,} filas en {_fmt(elapsed)}            ")


# ─────────────────────────────────────────────────────────────────────────────
# TRUNCAR TABLAS
# ─────────────────────────────────────────────────────────────────────────────

def truncar_tablas(conn, cursor, nombres: list, dry: bool):
    print("  Truncando tablas (orden inverso de FK)...")
    cursor.execute("SET foreign_key_checks = 0;")
    for t in reversed(nombres):
        if dry:
            print(f"    [DRY-RUN] TRUNCATE {t}")
        else:
            try:
                cursor.execute(f"TRUNCATE TABLE `{t}`")
                print(f"    ✓ TRUNCATE {t}")
            except Exception as e:
                print(f"    ✗ {t}: {e}")
    cursor.execute("SET foreign_key_checks = 1;")
    conn.commit()


# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURAR local_infile en el servidor
# ─────────────────────────────────────────────────────────────────────────────

def habilitar_local_infile(cursor) -> bool:
    try:
        cursor.execute("SET GLOBAL local_infile = 1;")
        return True
    except Exception:
        return False


# ─────────────────────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="SISAT — Importador de CSVs simulados a MySQL"
    )
    parser.add_argument("--solo",    metavar="TABLA",
                        help="Importar solo esta tabla (nombre sin .csv)")
    parser.add_argument("--truncar", action="store_true",
                        help="TRUNCATE tablas antes de importar")
    parser.add_argument("--csv-dir", default=CSV_DIR,
                        help=f"Carpeta de CSVs (default: {CSV_DIR})")
    parser.add_argument("--dry-run", action="store_true",
                        help="Simula sin escribir nada en MySQL")
    args = parser.parse_args()

    csv_dir = Path(args.csv_dir)
    dry     = args.dry_run

    print()
    print("══════════════════════════════════════════════════════════")
    print("  SISAT — Importador CSV → MySQL")
    print(f"  Host: {HOST}:{PORT}  BD: {DATABASE}")
    print(f"  CSV dir: {csv_dir}")
    if dry: print("  ⚠  MODO DRY-RUN — no se escribe en MySQL")
    print("══════════════════════════════════════════════════════════")
    print()

    # ── Verificar driver ──────────────────────────────────────────────────
    driver_nombre, driver = obtener_driver()
    if driver is None:
        print("✗ No se encontró ningún driver MySQL compatible.")
        print("  Instala uno con:")
        print("    pip install mysql-connector-python")
        print("  o:")
        print("    pip install pymysql")
        sys.exit(1)
    print(f"  Driver: {driver_nombre}")

    # ── Conectar ──────────────────────────────────────────────────────────
    try:
        conn   = conectar(driver_nombre, driver)
        cursor = conn.cursor()
        print(f"  ✓ Conectado a {DATABASE}@{HOST}")
    except Exception as e:
        print(f"  ✗ Error de conexión: {e}")
        print("  Verifica HOST, USER, PASSWORD y DATABASE en el script.")
        sys.exit(1)

    # Intentar habilitar local_infile en el servidor
    habilitar_local_infile(cursor)

    # ── Filtrar tablas ────────────────────────────────────────────────────
    tablas = TABLAS
    if args.solo:
        tablas = [t for t in TABLAS if t["tabla"] == args.solo
                  or t["csv"].replace(".csv","") == args.solo]
        if not tablas:
            print(f"  ✗ Tabla '{args.solo}' no encontrada en la configuración.")
            sys.exit(1)

    # ── Truncar ───────────────────────────────────────────────────────────
    if args.truncar:
        truncar_tablas(conn, cursor, [t["tabla"] for t in tablas], dry)

    # ── Importar ──────────────────────────────────────────────────────────
    t_total = time.time()
    for tcfg in tablas:
        csv_path = csv_path_real(csv_dir, tcfg["csv"])
        print(f"\n📥  {tcfg['tabla']:25s}  ←  {csv_path.name}")

        if not csv_path.exists():
            alt = csv_path.with_suffix(csv_path.suffix + ".gz")
            if alt.exists():
                csv_path = alt
            else:
                print(f"    ⚠ Archivo no encontrado: {csv_path}  (saltando)")
                continue

        # Contar filas estimadas
        total_est = contar_filas(csv_path)
        print(f"    Filas estimadas: {total_est:,}")

        # Intentar LOAD DATA primero
        ok = importar_load_data(conn, cursor, tcfg, csv_path, dry)
        if not ok:
            importar_insert(conn, cursor, tcfg, csv_path, dry)

    cursor.close()
    conn.close()

    print()
    print("══════════════════════════════════════════════════════════")
    print(f"  ✅ Importación completada en {_fmt(time.time()-t_total)}")
    print("══════════════════════════════════════════════════════════")
    print()
    print("  Siguientes pasos:")
    print("    1. Actualiza las contraseñas: ejecuta hash_sisat.php")
    print("       y aplica el UPDATE SQL generado.")
    print("    2. Abre dashboard_sisat.php en tu servidor local.")
    print()


if __name__ == "__main__":
    main()
