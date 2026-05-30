#!/usr/bin/env python3
"""
generar_datos_sisat.py
SISAT — Sistema de Alerta Temprana para Abandono Escolar
Generador masivo de datos de simulación (modo TESIS por defecto).

Uso:
    python generar_datos_sisat.py                           # tesis
    python generar_datos_sisat.py --modo tesis
    python generar_datos_sisat.py --modo tesis --particionado
    python generar_datos_sisat.py --modo tesis --gzip
    python generar_datos_sisat.py --modo demo
    python generar_datos_sisat.py --modo smoke
    python generar_datos_sisat.py --modo tesis --semilla 2025
    python generar_datos_sisat.py --modo tesis --lote 500   # alumnos por batch
    python generar_datos_sisat.py --limpiar                 # borra output y reinicia
"""

import argparse
import csv
import gzip
import json
import os
import random
import sys
import time
from datetime import date, timedelta
from pathlib import Path

# ─────────────────────────────────────────────────────────────────────────────
# VERSIÓN
# ─────────────────────────────────────────────────────────────────────────────
VERSION = "1.0.0"

# ─────────────────────────────────────────────────────────────────────────────
# CATÁLOGOS FIJOS
# ─────────────────────────────────────────────────────────────────────────────

MUNICIPIOS = [
    "Saltillo", "Torreón", "Monclova", "Piedras Negras", "Acuña",
    "Ramos Arizpe", "Arteaga", "San Pedro", "Matamoros", "Frontera",
    "Múzquiz", "Sabinas", "Parras", "Nava", "Allende",
    "Francisco I. Madero", "San Buenaventura", "Castaños",
    "Cuatro Ciénegas", "Zaragoza",
]

# Pesos para distribuir escuelas por municipio (mayor = más escuelas)
PESO_MUNICIPIO = [
    20, 18, 10, 8, 7, 6, 4, 5, 4, 4,
    3, 3, 2, 2, 2, 2, 1, 1, 1, 1,
]

NIVELES = ["SECUNDARIA", "BACHILLERATO", "PRIMARIA"]
PESO_NIVEL = [0.50, 0.30, 0.20]

CICLOS = [
    "2016-2017", "2017-2018", "2018-2019", "2019-2020", "2020-2021",
    "2021-2022", "2022-2023", "2023-2024", "2024-2025", "2025-2026",
]

GRADOS_SEC  = ["1", "2", "3"]
GRADOS_BACH = ["1", "2", "3"]
GRADOS_PRI  = ["1", "2", "3", "4", "5", "6"]
LETRAS_GRUPO = ["A", "B", "C", "D", "E", "F"]

ROLES = [
    (1, "ADMIN"), (2, "SEDU"), (3, "DIRECTOR"),
    (4, "DOCENTE"), (5, "ORIENTADOR"), (6, "ALUMNO"),
]

# Indicadores SISAT con su peso
INDICADORES = [
    (1, "Rezago en lectura",          "Dificultad notoria en comprensión lectora.",               1),
    (2, "Rezago en escritura",        "Problemas de escritura y redacción básica.",                1),
    (3, "Rezago en cálculo mental",   "Bajo desempeño en operaciones matemáticas.",               1),
    (4, "Inasistencias frecuentes",   "El alumno falta con regularidad sin justificación.",        2),
    (5, "Bajo rendimiento académico", "Promedio por debajo del mínimo aprobatorio.",               2),
    (6, "Problemas de conducta",      "Reportes de disciplina o conflictos con pares.",            1),
    (7, "Situación socioemocional",   "Indicios de problemas emocionales o ansiedad.",             3),
    (8, "Riesgo económico/familiar",  "Situación que pone en riesgo la continuidad escolar.",      3),
]
PESOS_IND = {i[0]: i[3] for i in INDICADORES}

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURACIÓN POR MODO
# ─────────────────────────────────────────────────────────────────────────────

MODOS = {
    "smoke": {
        "desc":               "Humo — validación rápida",
        "escuelas":           5,
        "grupos_por_escuela": 2,
        "alumnos_por_grupo":  10,
        "ciclos":             1,
        "evals_por_ciclo":    1,
    },
    "demo": {
        "desc":               "Demo — presentación ligera",
        "escuelas":           50,
        "grupos_por_escuela": 4,
        "alumnos_por_grupo":  10,
        "ciclos":             3,
        "evals_por_ciclo":    2,
    },
    "tesis": {
        "desc":               "TESIS — volumen representativo de alto impacto",
        "escuelas":           500,
        "grupos_por_escuela": 8,
        "alumnos_por_grupo":  38,   # 500 × 8 × 38 = 152 000 ≈ 150 K
        "ciclos":             10,
        "evals_por_ciclo":    3,
    },
}

# ─────────────────────────────────────────────────────────────────────────────
# PERFILES DE RIESGO
# ─────────────────────────────────────────────────────────────────────────────

PERFILES_DIST = ["BAJO", "MEDIO", "ALTO", "CRITICO"]
PERFILES_PROB = [0.55, 0.25, 0.15, 0.05]

PARAMS = {
    "BAJO": {
        "asist": (96, 2.5, 89),   # media, std, mínimo
        "prom":  (8.7, 0.6, 7.5),
        "ind":   [0.04, 0.04, 0.04, 0.03, 0.04, 0.03, 0.02, 0.02],
        "prob_alerta":     0.00,
        "prob_seguimiento": 0.00,
    },
    "MEDIO": {
        "asist": (84, 4.0, 76),
        "prom":  (7.0, 0.7, 6.0),
        "ind":   [0.22, 0.20, 0.24, 0.16, 0.28, 0.13, 0.11, 0.14],
        "prob_alerta":     0.38,
        "prob_seguimiento": 0.50,
    },
    "ALTO": {
        "asist": (73, 6.0, 60),
        "prom":  (5.5, 0.9, 4.0),
        "ind":   [0.48, 0.44, 0.50, 0.58, 0.65, 0.38, 0.33, 0.42],
        "prob_alerta":     0.78,
        "prob_seguimiento": 0.50,
    },
    "CRITICO": {
        "asist": (57, 8.0, 38),
        "prom":  (4.4, 0.9, 3.0),
        "ind":   [0.78, 0.72, 0.80, 0.88, 0.92, 0.68, 0.82, 0.87],
        "prob_alerta":     1.00,
        "prob_seguimiento": 0.50,
    },
}

ESTATUS_OPCIONES = ["NUEVA", "EN_REVISION", "EN_SEGUIMIENTO", "ATENDIDA", "CERRADA"]
ESTATUS_PESOS    = [0.25,    0.20,          0.25,              0.15,       0.15]

ACCIONES = [
    "Se realizó entrevista individual con el alumno.",
    "Se citó a padres o tutores para sesión informativa.",
    "Se canalizó a orientación psicológica escolar.",
    "Se gestionó apoyo económico ante trabajo social.",
    "Se otorgó tutoría académica de refuerzo.",
    "Se realizó visita domiciliaria de seguimiento.",
    "Se estableció plan de recuperación académica.",
    "Se contactó a red de apoyo comunitario.",
    "Se monitoreó asistencia de manera semanal.",
    "Se llevó a cabo sesión grupal de intervención.",
]

NOMBRES_M = [
    "Carlos","Luis","José","Juan","Miguel","Roberto","David","Eduardo",
    "Fernando","Alejandro","Daniel","Ricardo","Manuel","Antonio","Jorge",
    "Francisco","Jesús","Rafael","Héctor","Óscar","Arturo","Marco",
    "Ernesto","Raúl","Sergio","Adrián","Andrés","Enrique","Felipe","Gerardo",
]
NOMBRES_F = [
    "María","Ana","Laura","Rosa","Patricia","Daniela","Valeria","Fernanda",
    "Gabriela","Sofía","Isabella","Camila","Natalia","Alejandra","Mariana",
    "Claudia","Mónica","Elena","Adriana","Beatriz","Carmen","Diana",
    "Elizabeth","Gloria","Irene","Karla","Leticia","Norma","Paulina","Sandra",
]
APELLIDOS = [
    "García","Martínez","López","González","Hernández","Pérez","Ramírez",
    "Torres","Flores","Rivera","Morales","Jiménez","Reyes","Cruz","Ortiz",
    "Gutiérrez","Chávez","Ramos","Moreno","Mendoza","Ruiz","Salinas",
    "Vargas","Castillo","Rojas","Vega","Herrera","Medina","Aguilar","Castro",
    "Soto","Ávila","Delgado","Suárez","Navarro","Romero","Silva","Montoya",
    "Acosta","Fuentes","Paredes","Ibarra","Campos","Vera","Cervantes",
    "Guerrero","Sandoval","Ríos","Núñez","Contreras",
]

PREFIJOS_ESCUELA = {
    "SECUNDARIA":  ["Secundaria Técnica No.",
                    "Escuela Secundaria General No.",
                    "Secundaria Federal No."],
    "BACHILLERATO":["Preparatoria Estatal No.",
                    "COBAC Plantel",
                    "CONALEP Plantel",
                    "CECyTE Plantel"],
    "PRIMARIA":    ["Escuela Primaria Federal No.",
                    "Primaria Estatal No.",
                    "Centro Escolar No."],
}

ESTADO_COD = "COA"   # Coahuila en CURP


# ─────────────────────────────────────────────────────────────────────────────
# HELPERS DE GENERACIÓN
# ─────────────────────────────────────────────────────────────────────────────

def clamp(val, lo, hi):
    return max(lo, min(hi, val))


def calcular_riesgo(asistencia: float, promedio: float, ind_vals: dict) -> tuple:
    """
    Calcula puntaje y nivel de riesgo SISAT.
    ind_vals: {id_indicador: 0|1}
    Retorna: (puntaje: int, nivel: str)
    """
    pts = 0
    if asistencia < 80:
        pts += 3
    elif asistencia < 90:
        pts += 1

    if promedio < 6:
        pts += 3
    elif promedio < 7:
        pts += 2
    elif promedio < 8:
        pts += 1

    activos = 0
    for iid, v in ind_vals.items():
        if v:
            pts += PESOS_IND[iid]
            activos += 1
    if activos >= 3:
        pts += 1

    if pts <= 2:
        nivel = "BAJO"
    elif pts <= 5:
        nivel = "MEDIO"
    elif pts <= 8:
        nivel = "ALTO"
    else:
        nivel = "CRITICO"

    return pts, nivel


def gen_asistencia(perfil: str, rng: random.Random) -> float:
    m, s, mn = PARAMS[perfil]["asist"]
    return round(clamp(rng.gauss(m, s), mn, 100.0), 2)


def gen_promedio(perfil: str, rng: random.Random) -> float:
    m, s, mn = PARAMS[perfil]["prom"]
    return round(clamp(rng.gauss(m, s), mn, 10.0), 2)


def gen_indicadores(perfil: str, rng: random.Random) -> dict:
    probs = PARAMS[perfil]["ind"]
    return {ind[0]: (1 if rng.random() < probs[i] else 0)
            for i, ind in enumerate(INDICADORES)}


def fecha_en_ciclo(ciclo: str, eval_idx: int, rng: random.Random) -> str:
    """Fecha realista dentro del ciclo escolar (sep-ago)."""
    y0 = int(ciclo[:4])
    # 3 periodos: oct-nov, feb-mar, may-jun
    rangos = [(y0, 10, 11), (y0+1, 2, 3), (y0+1, 5, 6)]
    yr, m1, m2 = rangos[eval_idx % 3]
    mes = rng.randint(m1, m2)
    dia = rng.randint(1, 28)
    return f"{yr}-{mes:02d}-{dia:02d}"


def edad_en_fecha(nacimiento_str: str, eval_fecha: str) -> int:
    try:
        ny, nm, nd = map(int, nacimiento_str.split("-"))
        ey, em, ed = map(int, eval_fecha.split("-"))
        edad = ey - ny
        if (em, ed) < (nm, nd):
            edad -= 1
        return max(8, min(25, edad))
    except Exception:
        return 14


def gen_curp(nombre: str, ap: str, am: str, nac: str, sexo: str, idx: int) -> str:
    """Genera CURP con formato válido pero sintético."""
    n1 = (nombre[0] if nombre else "X").upper()
    # primera vocal interna del primer apellido
    vocales = [c for c in ap[1:] if c.upper() in "AEIOU"]
    v1 = vocales[0].upper() if vocales else "X"
    ap1 = ap[1].upper() if len(ap) > 1 else "X"
    am1 = am[0].upper() if am else "X"

    try:
        yy = nac[2:4]; mm = nac[5:7]; dd = nac[8:10]
    except Exception:
        yy, mm, dd = "00", "01", "01"

    sx = "H" if sexo == "M" else "M"
    # consonantes internas del primer apellido
    cons = [c for c in ap[1:] if c.upper() not in "AEIOU"]
    c1 = cons[0].upper() if len(cons) > 0 else "X"
    c2 = cons[1].upper() if len(cons) > 1 else "X"
    seq = f"{idx:02d}"[-2:]
    return f"{ap1}{v1}{am1}{n1}{yy}{mm}{dd}{sx}{ESTADO_COD}{ap1}{c1}{c2}{seq}"


def elegir_ponderado(opciones, pesos, rng):
    total = sum(pesos)
    r = rng.uniform(0, total)
    acum = 0
    for op, p in zip(opciones, pesos):
        acum += p
        if r <= acum:
            return op
    return opciones[-1]


# ─────────────────────────────────────────────────────────────────────────────
# ABRIR CSV (normal o gzip)
# ─────────────────────────────────────────────────────────────────────────────

def abrir_csv(ruta: Path, encabezado: list, modo_gz: bool, append: bool = False):
    """Abre un archivo CSV (o .csv.gz) y escribe el encabezado si es nuevo."""
    if modo_gz:
        ruta = ruta.with_suffix(ruta.suffix + ".gz")
    modo_ap = "at" if append else "wt"
    if modo_gz:
        fh = gzip.open(ruta, mode=modo_ap, encoding="utf-8", newline="")
    else:
        fh = open(ruta, mode=modo_ap, encoding="utf-8", newline="")
    writer = csv.writer(fh, quoting=csv.QUOTE_MINIMAL)
    if not append:
        writer.writerow(encabezado)
    return fh, writer


# ─────────────────────────────────────────────────────────────────────────────
# CHECKPOINT
# ─────────────────────────────────────────────────────────────────────────────

def leer_checkpoint(out_dir: Path) -> dict:
    cp = out_dir / "checkpoint.json"
    if cp.exists():
        with open(cp) as f:
            return json.load(f)
    return {}


def guardar_checkpoint(out_dir: Path, datos: dict):
    cp = out_dir / "checkpoint.json"
    with open(cp, "w") as f:
        json.dump(datos, f, indent=2)


def borrar_checkpoint(out_dir: Path):
    cp = out_dir / "checkpoint.json"
    if cp.exists():
        cp.unlink()


# ─────────────────────────────────────────────────────────────────────────────
# BARRA DE PROGRESO
# ─────────────────────────────────────────────────────────────────────────────

class Progreso:
    def __init__(self, total: int, descripcion: str = ""):
        self.total = total
        self.actual = 0
        self.desc = descripcion
        self.t0 = time.time()
        self._ultima_impresion = 0

    def avanzar(self, n: int = 1):
        self.actual += n
        ahora = time.time()
        if ahora - self._ultima_impresion >= 2 or self.actual == self.total:
            self._imprimir()
            self._ultima_impresion = ahora

    def _imprimir(self):
        pct = self.actual / self.total * 100 if self.total else 0
        elapsed = time.time() - self.t0
        if self.actual > 0 and elapsed > 0:
            eta = elapsed / self.actual * (self.total - self.actual)
            eta_str = _fmt_seg(eta)
        else:
            eta_str = "—"
        bar_w = 30
        filled = int(bar_w * self.actual / self.total) if self.total else 0
        bar = "█" * filled + "░" * (bar_w - filled)
        print(
            f"\r  [{bar}] {pct:5.1f}%  {self.actual:,}/{self.total:,}"
            f"  ETA: {eta_str}  {self.desc}      ",
            end="", flush=True,
        )
        if self.actual == self.total:
            print()


def _fmt_seg(s: float) -> str:
    s = int(s)
    if s < 60:
        return f"{s}s"
    elif s < 3600:
        return f"{s//60}m {s%60:02d}s"
    else:
        h = s // 3600
        return f"{h}h {(s%3600)//60}m"


def _fmt_num(n: int) -> str:
    return f"{n:,}"


# ─────────────────────────────────────────────────────────────────────────────
# FASE 1 — Datos estáticos
# ─────────────────────────────────────────────────────────────────────────────

def gen_roles(out: Path, gz: bool):
    fh, w = abrir_csv(out / "rol.csv", ["ID_ROL", "NOMBRE_ROL"], gz)
    for r in ROLES:
        w.writerow(r)
    fh.close()
    print(f"  ✓ rol.csv  ({len(ROLES)} filas)")


def gen_indicadores_csv(out: Path, gz: bool):
    fh, w = abrir_csv(
        out / "indicador_riesgo.csv",
        ["ID_INDICADOR", "NOMBRE_INDICADOR", "DESCRIPCION", "PESO", "ACTIVO"],
        gz,
    )
    for ind in INDICADORES:
        w.writerow([ind[0], ind[1], ind[2], ind[3], 1])
    fh.close()
    print(f"  ✓ indicador_riesgo.csv  ({len(INDICADORES)} filas)")


# ─────────────────────────────────────────────────────────────────────────────
# FASE 2 — Escuelas
# ─────────────────────────────────────────────────────────────────────────────

def gen_escuelas(cfg: dict, out: Path, gz: bool, rng: random.Random) -> list:
    n_esc = cfg["escuelas"]
    fh, w = abrir_csv(
        out / "escuela.csv",
        ["ID_ESCUELA", "CCT", "NOMBRE_ESCUELA", "MUNICIPIO", "NIVEL", "ZONA_ESCOLAR", "ACTIVA"],
        gz,
    )

    # Distribuir escuelas por municipio según pesos
    cuotas = _distribuir(n_esc, MUNICIPIOS, PESO_MUNICIPIO, rng)
    escuelas = []
    eid = 1
    for mun, cant in cuotas:
        for k in range(cant):
            nivel = elegir_ponderado(NIVELES, PESO_NIVEL, rng)
            prefijo = rng.choice(PREFIJOS_ESCUELA[nivel])
            nombre = f"{prefijo} {eid}"
            cct_tipo = {"SECUNDARIA": "DST", "BACHILLERATO": "DES", "PRIMARIA": "DPR"}[nivel]
            cct = f"05{cct_tipo}{eid:04d}A"
            zona = f"ZONA {rng.randint(1, 30):02d}"
            w.writerow([eid, cct, nombre, mun, nivel, zona, 1])
            escuelas.append({
                "id": eid, "cct": cct, "nombre": nombre,
                "municipio": mun, "nivel": nivel, "zona": zona,
            })
            eid += 1

    fh.close()
    print(f"  ✓ escuela.csv  ({len(escuelas):,} filas)")
    return escuelas


def _distribuir(total: int, opciones: list, pesos: list, rng: random.Random) -> list:
    """Distribuye 'total' elementos entre opciones según pesos."""
    sum_p = sum(pesos)
    cuotas = []
    restante = total
    for i, (op, p) in enumerate(zip(opciones, pesos)):
        if i == len(opciones) - 1:
            cuotas.append((op, restante))
        else:
            c = round(total * p / sum_p)
            c = max(1, c)
            c = min(c, restante - (len(opciones) - i - 1))
            cuotas.append((op, c))
            restante -= c
    return cuotas


# ─────────────────────────────────────────────────────────────────────────────
# FASE 3 — Usuarios (docentes, directores, etc.)
# ─────────────────────────────────────────────────────────────────────────────

def gen_usuarios(escuelas: list, out: Path, gz: bool, rng: random.Random) -> tuple:
    """
    Genera usuarios base: admin, sedu, director×escuela, docente×escuela.
    Retorna: (lista_docentes_por_escuela, uid_siguiente)
    uid_siguiente: primer ID libre para usuarios alumno (si se necesitan)
    """
    pwd_ph = "$2y$10$PLACEHOLDER_HASH_TESIS_SISAT_XXXXXXXXXXXXXXXXXXXXXXXXXXXX"
    fh, w = abrir_csv(
        out / "usuario.csv",
        ["ID_USUARIO", "USUARIO", "CORREO", "PWD", "ID_ROL", "ACTIVO", "FECHA_CREACION"],
        gz,
    )
    uid = 1
    fecha_base = "2023-08-01 08:00:00"

    # Usuarios fijos
    fijos = [
        (uid,   "admin",      "admin@sisat.edu.mx",       1),
        (uid+1, "sedu",       "sedu@sisat.edu.mx",        2),
    ]
    uid += 2
    for row in fijos:
        w.writerow([row[0], row[1], row[2], pwd_ph, row[3], 1, fecha_base])

    # Director por escuela (rol 3) y docentes (rol 4)
    docente_por_escuela = {}   # escuela_id → [uid_docente, ...]
    for esc in escuelas:
        eid = esc["id"]
        slug = f"dir{eid}"
        w.writerow([uid, slug, f"{slug}@sisat.edu.mx", pwd_ph, 3, 1, fecha_base])
        uid += 1

        # 2-4 docentes por escuela
        n_doc = rng.randint(2, 4)
        docente_por_escuela[eid] = []
        for d in range(n_doc):
            dslug = f"doc{eid}_{d+1}"
            w.writerow([uid, dslug, f"{dslug}@sisat.edu.mx", pwd_ph, 4, 1, fecha_base])
            docente_por_escuela[eid].append(uid)
            uid += 1

    fh.close()
    print(f"  ✓ usuario.csv  ({uid-1:,} filas)")
    return docente_por_escuela, uid


# ─────────────────────────────────────────────────────────────────────────────
# FASE 4 — Grupos
# ─────────────────────────────────────────────────────────────────────────────

def gen_grupos(cfg: dict, escuelas: list, docentes: dict,
               out: Path, gz: bool, rng: random.Random) -> list:
    gpp = cfg["grupos_por_escuela"]
    ciclo_actual = CICLOS[-1]

    fh, w = abrir_csv(
        out / "grupo.csv",
        ["ID_GRUPO", "ID_ESCUELA", "GRADO", "GRUPO", "CICLO_ESCOLAR", "ID_DOCENTE"],
        gz,
    )

    grupos = []
    gid = 1
    for esc in escuelas:
        eid = esc["id"]
        nivel = esc["nivel"]
        grados = {"SECUNDARIA": GRADOS_SEC, "BACHILLERATO": GRADOS_BACH,
                  "PRIMARIA": GRADOS_PRI}[nivel]
        docs = docentes.get(eid, [None])

        for k in range(gpp):
            grado = grados[k % len(grados)]
            letra = LETRAS_GRUPO[k % len(LETRAS_GRUPO)]
            doc_id = rng.choice(docs) if docs else None
            w.writerow([gid, eid, grado, letra, ciclo_actual, doc_id])
            grupos.append({
                "id": gid, "escuela_id": eid, "grado": grado,
                "letra": letra, "ciclo": ciclo_actual,
            })
            gid += 1

    fh.close()
    print(f"  ✓ grupo.csv  ({len(grupos):,} filas)")
    return grupos


# ─────────────────────────────────────────────────────────────────────────────
# FASE 5 — Alumnos (streaming, retiene perfil en RAM)
# ─────────────────────────────────────────────────────────────────────────────

def gen_alumnos(cfg: dict, grupos: list, uid_next: int,
                out: Path, gz: bool, rng: random.Random) -> list:
    """
    Genera alumnos distribuyendo aprox. alumnos_por_grupo por grupo.
    Retorna lista compacta: [(id_alumno, id_grupo, sexo, nac_str, perfil)]
    """
    app = cfg["alumnos_por_grupo"]
    fh, w = abrir_csv(
        out / "alumno.csv",
        ["ID_ALUMNO", "MATRICULA", "CURP", "NOMBRE", "APELLIDO_PATERNO",
         "APELLIDO_MATERNO", "FECHA_NACIMIENTO", "SEXO", "TELEFONO",
         "CORREO", "DIRECCION", "ID_GRUPO", "ID_USUARIO", "ACTIVO"],
        gz,
    )

    perfiles_validos = list(range(len(PERFILES_DIST)))   # 0,1,2,3

    total_esperado = len(grupos) * app
    prog = Progreso(total_esperado, "alumnos")

    alumno_data = []   # compact in-memory: (id, gid, sexo, nac_str, perfil_idx)
    aid = 1
    uid = uid_next

    for grp in grupos:
        gid = grp["id"]
        n_alumnos = app + rng.randint(-2, 2)   # ±2 alumnos por grupo
        n_alumnos = max(5, n_alumnos)

        for _ in range(n_alumnos):
            sexo = "M" if rng.random() < 0.50 else "F"
            nombre = rng.choice(NOMBRES_M if sexo == "M" else NOMBRES_F)
            ap = rng.choice(APELLIDOS)
            am = rng.choice(APELLIDOS)

            # Edad escolar entre 10 y 20
            edad_est = rng.randint(10, 19)
            anio_nac = 2025 - edad_est
            mes_nac  = rng.randint(1, 12)
            dia_nac  = rng.randint(1, 28)
            nac_str  = f"{anio_nac}-{mes_nac:02d}-{dia_nac:02d}"

            matricula = f"COA{aid:07d}"
            curp = gen_curp(nombre, ap, am, nac_str, sexo, aid)
            tel  = f"84{rng.randint(10000000, 99999999)}"
            correo = f"alumno{aid}@escolar.edu.mx"
            direccion = f"Calle {rng.randint(1,50)} No. {rng.randint(1,999)}, Coahuila"

            perfil_idx = elegir_ponderado(
                perfiles_validos, PERFILES_PROB, rng
            )

            w.writerow([
                aid, matricula, curp, nombre, ap, am, nac_str, sexo,
                tel, correo, direccion, gid, uid, 1,
            ])
            alumno_data.append((aid, gid, sexo, nac_str, perfil_idx))
            aid += 1
            uid += 1
            prog.avanzar()

    fh.close()
    print(f"  ✓ alumno.csv  ({len(alumno_data):,} filas)")
    return alumno_data


# ─────────────────────────────────────────────────────────────────────────────
# LOOKUPS rápidos
# ─────────────────────────────────────────────────────────────────────────────

def build_lookups(escuelas: list, grupos: list) -> tuple:
    esc_info = {e["id"]: e for e in escuelas}
    grp_info = {g["id"]: g for g in grupos}
    return esc_info, grp_info


# ─────────────────────────────────────────────────────────────────────────────
# FASE 6 — Evaluaciones, Alertas, Seguimientos, Dashboard (streaming)
# ─────────────────────────────────────────────────────────────────────────────

def gen_evaluaciones(cfg: dict, alumno_data: list,
                     esc_info: dict, grp_info: dict,
                     out: Path, dash_out: Path, gz: bool,
                     particionado: bool, rng: random.Random,
                     checkpoint: dict) -> dict:
    """
    Genera evaluaciones, detalles, alertas, seguimientos y CSV de dashboard.
    Procesa por lotes de LOTE_SIZE alumnos para no saturar RAM.
    """
    LOTE_SIZE = cfg.get("lote_size", 1000)
    n_ciclos  = cfg["ciclos"]
    n_evals   = cfg["evals_por_ciclo"]
    ciclos    = CICLOS[:n_ciclos]

    # IDs globales (se recuperan del checkpoint si existe)
    ids = checkpoint.get("ids", {
        "eval": 0, "detalle": 0, "alerta": 0, "seguimiento": 0
    })
    lote_inicio = checkpoint.get("ultimo_lote", 0)

    total_alumnos = len(alumno_data)
    total_lotes   = (total_alumnos + LOTE_SIZE - 1) // LOTE_SIZE
    total_evals_esp = total_alumnos * n_ciclos * n_evals

    print(f"  Alumnos: {total_alumnos:,} | Ciclos: {n_ciclos} | "
          f"Evals/ciclo: {n_evals} | Evals esperadas: {total_evals_esp:,}")
    print(f"  Procesando en {total_lotes:,} lotes de hasta {LOTE_SIZE:,} alumnos...")
    if lote_inicio > 0:
        print(f"  Retomando desde lote {lote_inicio + 1}")

    append = lote_inicio > 0

    # ── Abrir archivos de salida ──────────────────────────────────────────
    fh_ev, w_ev = abrir_csv(
        out / "evaluacion_sisat.csv",
        ["ID_EVALUACION","ID_ALUMNO","ID_USUARIO_CAPTURA","FECHA_EVALUACION",
         "ASISTENCIA_PORCENTAJE","PROMEDIO_GENERAL","OBSERVACIONES",
         "PUNTAJE_RIESGO","NIVEL_RIESGO"],
        gz, append=append,
    )
    fh_det, w_det = abrir_csv(
        out / "evaluacion_detalle.csv",
        ["ID_DETALLE","ID_EVALUACION","ID_INDICADOR","VALOR","OBSERVACION"],
        gz, append=append,
    )
    fh_al, w_al = abrir_csv(
        out / "alerta.csv",
        ["ID_ALERTA","ID_ALUMNO","ID_EVALUACION","NIVEL_RIESGO","ESTATUS",
         "FECHA_CREACION","FECHA_CIERRE","DESCRIPCION"],
        gz, append=append,
    )
    fh_seg, w_seg = abrir_csv(
        out / "seguimiento.csv",
        ["ID_SEGUIMIENTO","ID_ALERTA","ID_USUARIO","FECHA_SEGUIMIENTO",
         "ACCION_REALIZADA","RESULTADO","PROXIMA_ACCION","FECHA_PROXIMA_ACCION"],
        gz, append=append,
    )

    DASH_COLS = [
        "anio","ciclo_escolar","municipio","cct","nombre_escuela","nivel",
        "zona_escolar","grado","grupo","matricula","sexo","edad",
        "asistencia_porcentaje","promedio_general",
        "indicador_lectura","indicador_escritura","indicador_calculo",
        "indicador_inasistencia","indicador_bajo_rendimiento",
        "indicador_conducta","indicador_socioemocional","indicador_economico",
        "puntaje_riesgo","nivel_riesgo","alerta_generada","estatus_alerta",
        "seguimiento_generado","fecha_evaluacion",
    ]

    if particionado:
        dash_files = {}
        dash_writers = {}
        for c in ciclos:
            fname = dash_out / f"sisat_dashboard_{c.replace('-','_')}.csv"
            fh_d, w_d = abrir_csv(fname, DASH_COLS, gz, append=append)
            dash_files[c] = fh_d
            dash_writers[c] = w_d
    else:
        fh_dash, w_dash_single = abrir_csv(
            out / "sisat_dashboard_dataset.csv", DASH_COLS, gz, append=append
        )
        dash_files   = None
        dash_writers = None

    # ── Contadores de estadísticas finales ───────────────────────────────
    stats = {k: 0 for k in ["evals","alertas","seguimientos",
                             "BAJO","MEDIO","ALTO","CRITICO"]}

    prog = Progreso(total_lotes - lote_inicio, "lotes procesados")
    t0 = time.time()

    # ── Bucle principal por lotes ─────────────────────────────────────────
    for li in range(lote_inicio, total_lotes):
        lote = alumno_data[li * LOTE_SIZE : (li + 1) * LOTE_SIZE]

        for (aid, gid, sexo, nac_str, perfil_idx) in lote:
            perfil    = PERFILES_DIST[perfil_idx]
            grp       = grp_info.get(gid, {})
            esc       = esc_info.get(grp.get("escuela_id", 0), {})
            matricula = f"COA{aid:07d}"

            for ci, ciclo in enumerate(ciclos):
                anio = int(ciclo[:4])

                for ei in range(n_evals):
                    ids["eval"] += 1
                    ev_id   = ids["eval"]
                    fecha   = fecha_en_ciclo(ciclo, ei, rng)
                    asist   = gen_asistencia(perfil, rng)
                    prom    = gen_promedio(perfil, rng)
                    ind_v   = gen_indicadores(perfil, rng)
                    pts, nivel = calcular_riesgo(asist, prom, ind_v)
                    obs     = ""

                    # Evaluacion
                    capturador = aid % 10 + 3   # simula usuario captura
                    w_ev.writerow([
                        ev_id, aid, capturador, fecha,
                        asist, prom, obs, pts, nivel,
                    ])

                    # Detalles (solo VALOR=1 para ahorrar espacio)
                    for iid, val in ind_v.items():
                        if val:
                            ids["detalle"] += 1
                            w_det.writerow([ids["detalle"], ev_id, iid, 1, ""])

                    # Alerta
                    tiene_alerta  = False
                    estatus_alerta = ""
                    tiene_seg     = False

                    p_al = PARAMS[perfil]["prob_alerta"]
                    if nivel != "BAJO" and rng.random() < p_al:
                        ids["alerta"] += 1
                        al_id = ids["alerta"]
                        tiene_alerta   = True
                        estatus_alerta = elegir_ponderado(
                            ESTATUS_OPCIONES, ESTATUS_PESOS, rng
                        )
                        fecha_cierre = fecha if estatus_alerta in ("ATENDIDA","CERRADA") else ""
                        desc = f"Alerta SISAT. Puntaje: {pts}. Nivel: {nivel}."
                        w_al.writerow([
                            al_id, aid, ev_id, nivel, estatus_alerta,
                            fecha, fecha_cierre, desc,
                        ])

                        # Seguimiento
                        if rng.random() < PARAMS[perfil]["prob_seguimiento"]:
                            ids["seguimiento"] += 1
                            seg_id = ids["seguimiento"]
                            tiene_seg = True
                            accion = rng.choice(ACCIONES)
                            prox   = rng.choice(ACCIONES)
                            w_seg.writerow([
                                seg_id, al_id, capturador, fecha,
                                accion, "Acción concluida.", prox, "",
                            ])

                    # Dashboard row
                    edad = edad_en_fecha(nac_str, fecha)
                    dash_row = [
                        anio, ciclo,
                        esc.get("municipio",""),
                        esc.get("cct",""),
                        esc.get("nombre",""),
                        esc.get("nivel",""),
                        esc.get("zona",""),
                        grp.get("grado",""),
                        grp.get("letra",""),
                        matricula, sexo, edad,
                        asist, prom,
                        ind_v.get(1,0), ind_v.get(2,0), ind_v.get(3,0),
                        ind_v.get(4,0), ind_v.get(5,0), ind_v.get(6,0),
                        ind_v.get(7,0), ind_v.get(8,0),
                        pts, nivel,
                        1 if tiene_alerta else 0,
                        estatus_alerta,
                        1 if tiene_seg else 0,
                        fecha,
                    ]

                    if particionado:
                        dash_writers[ciclo].writerow(dash_row)
                    else:
                        w_dash_single.writerow(dash_row)

                    # Estadísticas
                    stats["evals"] += 1
                    stats[nivel]   += 1
                    if tiene_alerta:  stats["alertas"]     += 1
                    if tiene_seg:     stats["seguimientos"] += 1

        # Guardar checkpoint cada lote
        guardar_checkpoint(out, {
            "modo": cfg.get("modo","tesis"),
            "ultimo_lote": li + 1,
            "ids": ids,
        })
        prog.avanzar()

    # Cerrar archivos
    for fh in [fh_ev, fh_det, fh_al, fh_seg]:
        fh.close()

    if particionado:
        for fh in dash_files.values():
            fh.close()
    else:
        fh_dash.close()

    elapsed = time.time() - t0
    print(f"  ✓ Evaluaciones: {_fmt_num(stats['evals'])}")
    print(f"  ✓ Detalles (VALOR=1): {_fmt_num(ids['detalle'])}")
    print(f"  ✓ Alertas: {_fmt_num(stats['alertas'])}")
    print(f"  ✓ Seguimientos: {_fmt_num(stats['seguimientos'])}")
    print(f"  Distribución: BAJO {stats['BAJO']:,} | MEDIO {stats['MEDIO']:,} "
          f"| ALTO {stats['ALTO']:,} | CRITICO {stats['CRITICO']:,}")
    print(f"  Tiempo: {_fmt_seg(elapsed)}")
    return stats


# ─────────────────────────────────────────────────────────────────────────────
# GENERAR MUESTRAS ESTÁTICAS
# ─────────────────────────────────────────────────────────────────────────────

def gen_muestras(out_sample: Path):
    """Genera CSVs pequeños de muestra para validación de formato."""

    def csv_mini(nombre, cols, filas):
        p = out_sample / nombre
        with open(p, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow(cols)
            w.writerows(filas)

    csv_mini("rol.csv", ["ID_ROL","NOMBRE_ROL"],
        [(1,"ADMIN"),(2,"SEDU"),(3,"DIRECTOR"),(4,"DOCENTE"),(5,"ORIENTADOR")])

    csv_mini("escuela.csv",
        ["ID_ESCUELA","CCT","NOMBRE_ESCUELA","MUNICIPIO","NIVEL","ZONA_ESCOLAR","ACTIVA"],
        [(1,"05DST0001A","Sec. Técnica No. 1","Saltillo","SECUNDARIA","ZONA 01",1),
         (2,"05DES0002B","Preparatoria No. 5","Torreón","BACHILLERATO","ZONA 02",1)])

    csv_mini("alumno.csv",
        ["ID_ALUMNO","MATRICULA","NOMBRE","APELLIDO_PATERNO","SEXO","ID_GRUPO","ACTIVO"],
        [(1,"COA0000001","Ana","García","F",1,1),
         (2,"COA0000002","Carlos","López","M",1,1)])

    csv_mini("evaluacion_sisat.csv",
        ["ID_EVALUACION","ID_ALUMNO","FECHA_EVALUACION","ASISTENCIA_PORCENTAJE",
         "PROMEDIO_GENERAL","PUNTAJE_RIESGO","NIVEL_RIESGO"],
        [(1,1,"2024-10-15",92.00,8.50,1,"BAJO"),
         (2,2,"2024-10-15",74.00,5.20,7,"ALTO")])

    csv_mini("alerta.csv",
        ["ID_ALERTA","ID_ALUMNO","ID_EVALUACION","NIVEL_RIESGO","ESTATUS","FECHA_CREACION"],
        [(1,2,2,"ALTO","NUEVA","2024-10-15")])

    csv_mini("sisat_dashboard_dataset.csv",
        ["anio","ciclo_escolar","municipio","cct","nombre_escuela","nivel",
         "grado","grupo","matricula","sexo","edad","asistencia_porcentaje",
         "promedio_general","puntaje_riesgo","nivel_riesgo","alerta_generada"],
        [(2024,"2024-2025","Saltillo","05DST0001A","Sec. Técnica No. 1",
          "SECUNDARIA","2","A","COA0000001","F",14,92.00,8.50,1,"BAJO",0),
         (2024,"2024-2025","Saltillo","05DST0001A","Sec. Técnica No. 1",
          "SECUNDARIA","2","A","COA0000002","M",13,74.00,5.20,7,"ALTO",1)])

    print(f"  ✓ output_sample/  (6 archivos de muestra)")


# ─────────────────────────────────────────────────────────────────────────────
# PUNTO DE ENTRADA
# ─────────────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="SISAT — Generador masivo de datos de simulación",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""Modos disponibles:
  smoke   — ~50 alumnos, 1 ciclo  (validación rápida, segundos)
  demo    — ~2,000 alumnos, 3 ciclos (presentación, minutos)
  tesis   — ~150,000 alumnos, 10 ciclos, ~4.5M evaluaciones (DEFAULT)

Ejemplos:
  python generar_datos_sisat.py
  python generar_datos_sisat.py --modo tesis --particionado
  python generar_datos_sisat.py --modo tesis --gzip
  python generar_datos_sisat.py --modo smoke --semilla 2025
  python generar_datos_sisat.py --limpiar
"""
    )
    parser.add_argument("--modo",        choices=["smoke","demo","tesis"],
                        default="tesis", help="Modo de generación (default: tesis)")
    parser.add_argument("--particionado", action="store_true",
                        help="Dashboard CSV particionado por ciclo escolar")
    parser.add_argument("--gzip",        action="store_true",
                        help="Comprimir CSVs con gzip (.csv.gz)")
    parser.add_argument("--semilla",     type=int, default=42,
                        help="Semilla aleatoria para reproducibilidad (default: 42)")
    parser.add_argument("--lote",        type=int, default=1000,
                        help="Alumnos por lote de procesamiento (default: 1000)")
    parser.add_argument("--limpiar",     action="store_true",
                        help="Borra los archivos generados previos y reinicia")
    parser.add_argument("--version",     action="version", version=f"SISAT-Gen {VERSION}")
    args = parser.parse_args()

    # ── Rutas ──────────────────────────────────────────────────────────────
    base     = Path(__file__).parent
    out      = base / "output"
    dash_out = out  / "dashboard"
    sample   = base / "output_sample"
    for d in [out, dash_out, sample]:
        d.mkdir(parents=True, exist_ok=True)

    # ── Limpiar ────────────────────────────────────────────────────────────
    if args.limpiar:
        print("🗑️  Limpiando archivos previos...")
        for f in list(out.glob("*.csv")) + list(out.glob("*.csv.gz")) + \
                 list(dash_out.glob("*.csv")) + list(dash_out.glob("*.csv.gz")):
            f.unlink()
        borrar_checkpoint(out)
        print("   Listo. Reiniciando generación desde cero.")

    # ── Configuración ──────────────────────────────────────────────────────
    cfg = dict(MODOS[args.modo])
    cfg["modo"]      = args.modo
    cfg["lote_size"] = args.lote

    rng = random.Random(args.semilla)

    # ── Checkpoint ─────────────────────────────────────────────────────────
    checkpoint = leer_checkpoint(out)
    reanudar   = bool(checkpoint)
    if reanudar:
        print(f"⏯️  Checkpoint encontrado — reanudando desde lote "
              f"{checkpoint.get('ultimo_lote', 0) + 1}")

    # ── Banner ─────────────────────────────────────────────────────────────
    print()
    print("══════════════════════════════════════════════════════════")
    print("  SISAT — Generador de Datos de Simulación")
    print(f"  Modo: {args.modo.upper()}  •  {cfg['desc']}")
    print(f"  Semilla: {args.semilla}  •  Gzip: {args.gzip}  •"
          f"  Particionado: {args.particionado}")
    n_esc  = cfg["escuelas"]
    n_grp  = cfg["escuelas"] * cfg["grupos_por_escuela"]
    n_alum = n_grp * cfg["alumnos_por_grupo"]
    n_eval = n_alum * cfg["ciclos"] * cfg["evals_por_ciclo"]
    print(f"  Estimado: {n_esc:,} escuelas | {n_grp:,} grupos | "
          f"{n_alum:,} alumnos | ~{n_eval:,} evaluaciones")
    print("══════════════════════════════════════════════════════════")
    print()

    t_inicio = time.time()

    if not reanudar:
        print("▶ Fase 1 — Datos estáticos")
        gen_roles(out, args.gzip)
        gen_indicadores_csv(out, args.gzip)

        print("\n▶ Fase 2 — Escuelas")
        escuelas = gen_escuelas(cfg, out, args.gzip, rng)

        print("\n▶ Fase 3 — Usuarios y docentes")
        docentes, uid_next = gen_usuarios(escuelas, out, args.gzip, rng)

        print("\n▶ Fase 4 — Grupos")
        grupos = gen_grupos(cfg, escuelas, docentes, out, args.gzip, rng)

        print("\n▶ Fase 5 — Alumnos")
        alumno_data = gen_alumnos(cfg, grupos, uid_next, out, args.gzip, rng)

        # Guardar checkpoint con datos estructurales
        guardar_checkpoint(out, {
            "modo":        args.modo,
            "semilla":     args.semilla,
            "ultimo_lote": 0,
            "ids":         {"eval":0,"detalle":0,"alerta":0,"seguimiento":0},
        })
        # Reconstruir lookups
        esc_info, grp_info = build_lookups(escuelas, grupos)

    else:
        # Para reanudar, necesitamos reconstruir escuelas/grupos/alumnos
        # Los leemos desde los CSVs ya generados
        print("▶ Reconstruyendo datos estructurales desde CSVs previos...")
        escuelas, grupos, alumno_data = _cargar_estructurales(out, args.gzip)
        esc_info, grp_info = build_lookups(escuelas, grupos)
        print(f"  Cargados: {len(escuelas)} escuelas, {len(grupos)} grupos,"
              f" {len(alumno_data):,} alumnos")

    print("\n▶ Fase 6 — Evaluaciones, Alertas, Seguimientos y Dashboard")
    stats = gen_evaluaciones(
        cfg, alumno_data, esc_info, grp_info,
        out, dash_out, args.gzip, args.particionado, rng, checkpoint
    )

    print("\n▶ Generando muestras de validación...")
    gen_muestras(sample)

    borrar_checkpoint(out)

    # ── Resumen final ──────────────────────────────────────────────────────
    total_t = time.time() - t_inicio
    print()
    print("══════════════════════════════════════════════════════════")
    print(f"  ✅ GENERACIÓN COMPLETA en {_fmt_seg(total_t)}")
    print(f"  Evaluaciones: {stats['evals']:,}")
    print(f"  Alertas:      {stats['alertas']:,}")
    print(f"  Seguimientos: {stats['seguimientos']:,}")
    print(f"  Archivos en:  {out}")
    if args.particionado:
        print(f"  Dashboard:    {dash_out}")
    else:
        print(f"  Dashboard:    {out / 'sisat_dashboard_dataset.csv'}")
    print("══════════════════════════════════════════════════════════")
    print()


def _cargar_estructurales(out: Path, gz: bool):
    """Lee escuelas, grupos y alumnos de CSVs existentes para reanudar."""
    def abrir_r(nombre):
        ruta = out / nombre
        if gz and (out / (nombre + ".gz")).exists():
            ruta = out / (nombre + ".gz")
            return gzip.open(ruta, "rt", encoding="utf-8")
        return open(ruta, "r", encoding="utf-8")

    escuelas = []
    with abrir_r("escuela.csv") as f:
        for row in csv.DictReader(f):
            escuelas.append({
                "id": int(row["ID_ESCUELA"]), "cct": row["CCT"],
                "nombre": row["NOMBRE_ESCUELA"], "municipio": row["MUNICIPIO"],
                "nivel": row["NIVEL"], "zona": row["ZONA_ESCOLAR"],
            })

    grupos = []
    with abrir_r("grupo.csv") as f:
        for row in csv.DictReader(f):
            grupos.append({
                "id": int(row["ID_GRUPO"]),
                "escuela_id": int(row["ID_ESCUELA"]),
                "grado": row["GRADO"], "letra": row["GRUPO"],
                "ciclo": row["CICLO_ESCOLAR"],
            })

    alumno_data = []
    perfil_map = {p: i for i, p in enumerate(PERFILES_DIST)}
    with abrir_r("alumno.csv") as f:
        for row in csv.DictReader(f):
            # Inferir perfil del alumno — no está en el CSV, así que usamos
            # un perfil neutral BAJO como base al reanudar
            alumno_data.append((
                int(row["ID_ALUMNO"]),
                int(row["ID_GRUPO"]),
                row["SEXO"],
                row["FECHA_NACIMIENTO"],
                0,   # perfil_idx BAJO como base conservadora
            ))

    return escuelas, grupos, alumno_data


if __name__ == "__main__":
    main()
