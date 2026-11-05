from __future__ import annotations

from datetime import datetime
from pathlib import Path
import json
import subprocess

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "informe_estado_modulos_noretel.docx"

NAVY = RGBColor(11, 37, 69)
BLUE = RGBColor(46, 116, 181)
DARK_BLUE = RGBColor(31, 77, 120)
GRAY = RGBColor(90, 90, 90)
LIGHT_GRAY = "F2F4F7"
LIGHT_BLUE = "E8EEF5"
WARNING_FILL = "FFF8E1"
RISK_FILL = "FDEDED"
OK_FILL = "EAF4EA"


def module_inventory() -> list[dict[str, object]]:
    modules_dir = ROOT / "Modules"
    rows: list[dict[str, object]] = []
    for module in sorted([p for p in modules_dir.iterdir() if p.is_dir()]):
        def count(base: Path, pattern: str) -> int:
            return len(list(base.rglob(pattern))) if base.exists() else 0

        rows.append(
            {
                "module": module.name,
                "controllers": count(module / "Http" / "Controllers", "*.php"),
                "entities": count(module / "Entities", "*.php"),
                "requests": count(module / "Http" / "Requests", "*.php"),
                "repositories": count(module / "Repositories", "*.php"),
                "services": count(module / "Services", "*.php"),
                "views": count(module / "Resources" / "views", "*.blade.php"),
                "web_routes": (module / "Routes" / "web.php").exists(),
                "api_routes": (module / "Routes" / "api.php").exists(),
            }
        )
    return rows


def git_status() -> list[str]:
    try:
        result = subprocess.run(
            ["git", "status", "--short"],
            cwd=ROOT,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            timeout=20,
            check=False,
        )
        return [line for line in result.stdout.splitlines() if line.strip()]
    except Exception:
        return []


def route_count() -> str:
    try:
        result = subprocess.run(
            ["php", "artisan", "route:list"],
            cwd=ROOT,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            timeout=60,
            check=False,
        )
        for line in result.stdout.splitlines():
            if "Showing [" in line and "] routes" in line:
                return line.strip()
        return "No se pudo extraer el total, pero route:list ejecutó."
    except Exception:
        return "No se pudo ejecutar route:list desde el generador."


def test_state() -> dict[str, object]:
    return {
        "artisan_test_available": False,
        "phpunit_binary_present": (ROOT / "vendor" / "bin" / "phpunit").exists()
        or (ROOT / "vendor" / "bin" / "phpunit.bat").exists(),
        "tests": sorted(str(p.relative_to(ROOT)) for p in (ROOT / "tests").rglob("*.php")),
    }


def modules_statuses() -> dict[str, bool]:
    path = ROOT / "modules_statuses.json"
    if not path.exists():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def set_cell_fill(cell, fill: str) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), fill)
    tc_pr.append(shd)


def set_cell_text(cell, text: str, bold: bool = False) -> None:
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    run = p.add_run(text)
    run.font.name = "Calibri"
    run.font.size = Pt(9.5)
    run.bold = bold
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER


def add_table(doc: Document, headers: list[str], rows: list[list[str]], widths: list[float] | None = None) -> None:
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"
    hdr = table.rows[0].cells
    for idx, header in enumerate(headers):
        set_cell_text(hdr[idx], header, bold=True)
        set_cell_fill(hdr[idx], LIGHT_GRAY)
        if widths:
            hdr[idx].width = Inches(widths[idx])
    for row in rows:
        cells = table.add_row().cells
        for idx, value in enumerate(row):
            set_cell_text(cells[idx], value)
            if widths:
                cells[idx].width = Inches(widths[idx])
    doc.add_paragraph()


def add_para(doc: Document, text: str, style: str | None = None, bold_lead: str | None = None) -> None:
    p = doc.add_paragraph(style=style)
    p.paragraph_format.space_after = Pt(6)
    if bold_lead and text.startswith(bold_lead):
        r = p.add_run(bold_lead)
        r.bold = True
        p.add_run(text[len(bold_lead):])
    else:
        p.add_run(text)


def add_bullets(doc: Document, items: list[str]) -> None:
    for item in items:
        p = doc.add_paragraph(style="List Bullet")
        p.paragraph_format.space_after = Pt(4)
        p.add_run(item)


def add_numbered(doc: Document, items: list[str]) -> None:
    for item in items:
        p = doc.add_paragraph(style="List Number")
        p.paragraph_format.space_after = Pt(4)
        p.add_run(item)


def add_callout(doc: Document, title: str, body: str, fill: str = LIGHT_BLUE) -> None:
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    set_cell_fill(cell, fill)
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(4)
    r = p.add_run(title)
    r.bold = True
    r.font.color.rgb = NAVY
    r.font.size = Pt(10)
    p2 = cell.add_paragraph()
    p2.paragraph_format.space_after = Pt(0)
    p2.add_run(body)
    doc.add_paragraph()


def page_break(doc: Document) -> None:
    doc.add_page_break()


def setup_styles(doc: Document) -> None:
    section = doc.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.top_margin = Inches(1)
    section.bottom_margin = Inches(1)
    section.left_margin = Inches(1)
    section.right_margin = Inches(1)
    section.header_distance = Inches(0.49)
    section.footer_distance = Inches(0.49)

    styles = doc.styles
    normal = styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(11)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.1

    for name, size, color, before, after in [
        ("Heading 1", 16, BLUE, 16, 8),
        ("Heading 2", 13, BLUE, 12, 6),
        ("Heading 3", 12, DARK_BLUE, 8, 4),
    ]:
        style = styles[name]
        style.font.name = "Calibri"
        style.font.size = Pt(size)
        style.font.color.rgb = color
        style.font.bold = True
        style.paragraph_format.space_before = Pt(before)
        style.paragraph_format.space_after = Pt(after)
        style.paragraph_format.keep_with_next = True

    footer = section.footer.paragraphs[0]
    footer.text = "Informe de estado de módulos - Noretel CRM"
    footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
    footer.runs[0].font.size = Pt(8)
    footer.runs[0].font.color.rgb = GRAY


MODULE_DETAILS = {
    "Core": {
        "state": "Base transversal funcional, responsable de autenticación, usuarios, roles, permisos, configuración, seguridad, notificaciones, workflows y auditoría.",
        "evidence": [
            "Rutas web registradas bajo el prefijo core y core/auth.",
            "Controladores especializados para Auth, Dashboard, User, Role, Audit, Configuration, SecurityPolicy, Notification y Workflow.",
            "Entidades de soporte para User, Role, Permission, AuditLog, SystemConfiguration, Workflow y Notification.",
        ],
        "risks": [
            "El login web requiere endurecimiento explícito de rate limiting.",
            "Los permisos son la columna vertebral del sistema; sin pruebas automatizadas, un cambio de middleware puede abrir o cerrar módulos accidentalmente.",
            "La configuración y los workflows tienen poder transversal y deben auditarse con mucho cuidado.",
        ],
        "next": [
            "Implementar throttling explícito para login web y API.",
            "Crear pruebas de permisos por rol: view, create, edit, delete.",
            "Probar flujos de recuperación/cambio de contraseña.",
            "Documentar matriz de permisos por módulo.",
            "Auditar acciones sensibles en AuditLog.",
        ],
    },
    "Customer": {
        "state": "Módulo de gestión comercial y atención al cliente con clientes, direcciones, documentos, versiones, contactos de emergencia, interacciones y leads.",
        "evidence": [
            "Rutas CRUD para customers, documents, interactions, leads, addresses y emergencyContacts.",
            "API autenticada para dashboard de cliente, reinicio de línea y subida de comprobante.",
            "Modelos específicos para Customer, Document, DocumentVersion, DocumentType, Lead e Interaction.",
        ],
        "risks": [
            "Los documentos y versiones implican almacenamiento, permisos de descarga y trazabilidad.",
            "La conversión de leads a clientes puede duplicar datos si no se valida DNI/RUC/teléfono.",
            "El portal cliente cruza Billing y Network; hay riesgo de acoplamiento sin contratos claros.",
        ],
        "next": [
            "Verificar flujo lead -> customer -> contrato.",
            "Validar carga, versionado y descarga de documentos.",
            "Asegurar que el cliente solo vea su propia información.",
            "Definir reglas de duplicidad para documento de identidad, correo y teléfono.",
            "Agregar pruebas funcionales para portal cliente.",
        ],
    },
    "Services": {
        "state": "Catálogo comercial activo en ajuste. Actualmente se está consolidando la regla de negocio: servicio principal Internet y tecnología Fibra Óptica + WiFi.",
        "evidence": [
            "Rutas CRUD para services, plans, promotions y additional-services.",
            "Cambio reciente centraliza opciones de servicio/tecnología y valida solo opciones activas.",
            "Migración pendiente para normalizar fibra legacy y desactivar servicios no soportados.",
        ],
        "risks": [
            "Si la migración no se ejecuta, la base puede conservar servicios activos incompatibles.",
            "Planes, contratos y APIs públicas dependen de este catálogo.",
            "TV debe modelarse como servicio adicional por ahora, no como servicio principal activo.",
        ],
        "next": [
            "Ejecutar migración de normalización del catálogo.",
            "Crear/validar servicio adicional TV asociado al servicio Internet activo.",
            "Probar request manual contra service_type y technology para confirmar rechazo backend.",
            "Revisar planes destacados y promociones públicas.",
            "Cerrar cambios pendientes y agregar pruebas de reglas comerciales.",
        ],
    },
    "Contract": {
        "state": "Módulo operativo contractual con contratos, instalaciones, rutas técnicas y SLA. Está funcional, pero depende fuertemente de Services y Customer.",
        "evidence": [
            "Rutas para contratos activos, próximos a vencer, expirados, PDF, renovación y cancelación.",
            "Instalaciones con completar/cancelar y gestión de fotos.",
            "Rutas técnicas y SLA con API interna de búsqueda.",
        ],
        "risks": [
            "Un cambio en tecnología/servicio puede afectar PDFs, vistas y contratos antiguos.",
            "La instalación debe quedar consistente con activación de red y contrato vigente.",
            "Faltan pruebas de renovación, cancelación y PDF.",
        ],
        "next": [
            "Probar contrato nuevo con plan Internet activo.",
            "Verificar PDF después del cambio de labels de tecnología.",
            "Definir estados contractuales oficiales.",
            "Conectar instalación completada con Operations y Network.",
            "Crear tests de renovación/cancelación y vencimiento.",
        ],
    },
    "Billing": {
        "state": "Módulo financiero robusto y crítico. Maneja facturas, pagos, notas de crédito, cobranzas, reportes y validación Yape/OpenWA.",
        "evidence": [
            "Rutas completas para invoices, payments, credit-notes, reports, collections y customer billing.",
            "Endpoints API para Yape client evidences, receiver events, OpenWA webhook y health.",
            "Entidades para Invoice, Payment, CreditNote, PaymentReminder y evidencias/intentos Yape.",
        ],
        "risks": [
            "Cualquier error impacta dinero real y confianza del cliente.",
            "OpenWA/OCR remoto requiere configuración productiva precisa.",
            "Los webhooks deben protegerse con token, firma o mecanismo equivalente.",
        ],
        "next": [
            "Validar flujo Yape end-to-end en producción controlada.",
            "Confirmar variables .env de OpenWA/OCR remoto.",
            "Probar generación programada de facturas.",
            "Auditar anulación de pagos/facturas/notas de crédito.",
            "Agregar pruebas unitarias para conciliación y payloads.",
        ],
    },
    "Network": {
        "state": "Módulo técnico avanzado para routers, nodos, métricas, logs, service requests, automatizaciones y terminal SSH.",
        "evidence": [
            "Rutas web para routers, nodes, service-requests, automation, reports y SSH.",
            "Rutas API admin/customer para estado de router, reinicio de línea y solicitudes.",
            "Dependencia phpseclib instalada para operaciones SSH.",
        ],
        "risks": [
            "Es el módulo de mayor riesgo operacional: puede afectar infraestructura real.",
            "SSH, reboot y automation necesitan permisos estrictos, auditoría y límites.",
            "Sin tests ni ambiente sandbox de routers, un cambio puede ser peligroso.",
        ],
        "next": [
            "Separar modo simulación y modo real para acciones de router.",
            "Auditar permisos de reboot, SSH y automation.",
            "Registrar cada comando y resultado técnico.",
            "Definir límites de comandos permitidos.",
            "Probar service requests con usuarios cliente/admin.",
        ],
    },
    "Operations": {
        "state": "Módulo de orquestación operativa inicial para onboarding, órdenes, activación y generación de contratos.",
        "evidence": [
            "Rutas para onboarding, crear orden, solicitar activación, completar activación y generar contrato.",
            "Vista de flujo por cliente.",
            "Modelo OnboardingOrder ubicado en Network, lo que revela acoplamiento entre operación y red.",
        ],
        "risks": [
            "Depende de Customer, Services, Contract, Billing y Network; si esos módulos no cierran reglas, Operations queda frágil.",
            "Faltan estados formales y pruebas de transición.",
            "Puede duplicar lógica si cada módulo decide su propio flujo.",
        ],
        "next": [
            "Definir máquina de estados del onboarding.",
            "Documentar responsables por etapa.",
            "Conectar activación con contrato e instalación.",
            "Decidir si genera facturación inicial.",
            "Agregar tablero de seguimiento y pruebas de transición.",
        ],
    },
    "Public": {
        "state": "Módulo API público parcial. Existe en código, pero no aparece habilitado en modules_statuses.json; además convive con endpoints públicos del módulo Services.",
        "evidence": [
            "Tiene api.php con coverage, coverage request y plans.",
            "No tiene web.php.",
            "Services también expone APIs públicas para services, plans y promotions.",
        ],
        "risks": [
            "Duplicidad de APIs públicas y posible doble fuente de verdad.",
            "Al no figurar en modules_statuses.json, su carga debe confirmarse por service provider/rutas.",
            "Cobertura pública puede quedar desconectada del flujo comercial real.",
        ],
        "next": [
            "Decidir si Public será el único BFF/API público.",
            "Consolidar endpoints públicos de planes/servicios.",
            "Documentar contrato API para frontend.",
            "Probar cobertura por zona/dirección.",
            "Alinear solicitudes públicas con Customer/Operations.",
        ],
    },
}


def build_report() -> None:
    OUT.parent.mkdir(parents=True, exist_ok=True)
    doc = Document()
    setup_styles(doc)

    inventory = module_inventory()
    statuses = modules_statuses()
    changes = git_status()
    tests = test_state()
    route_summary = route_count()

    # Cover
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(90)
    r = p.add_run("INFORME FORMAL DE ESTADO DE MÓDULOS")
    r.bold = True
    r.font.size = Pt(22)
    r.font.color.rgb = NAVY
    p2 = doc.add_paragraph()
    p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p2.add_run("Noretel CRM - Evaluación técnica, riesgos y hoja de ruta")
    r.font.size = Pt(14)
    r.font.color.rgb = GRAY
    doc.add_paragraph()
    add_table(
        doc,
        ["Campo", "Detalle"],
        [
            ["Proyecto", "C:\\xampp\\htdocs\\noretelCRMfinal\\noretel"],
            ["Fecha de elaboración", datetime.now().strftime("%d/%m/%Y")],
            ["Alcance", "Módulos Laravel: Core, Customer, Services, Contract, Billing, Network, Operations y Public"],
            ["Método", "Inspección estática, inventario de rutas, estado Git, archivos de módulo y disponibilidad de pruebas"],
            ["Resultado general", "Sistema amplio y encaminado, pero todavía requiere cierre técnico y pruebas antes de declararse estable."],
        ],
        [1.7, 4.8],
    )
    add_callout(
        doc,
        "Conclusión ejecutiva",
        "El proyecto tiene una base funcional importante y 348 rutas registradas; sin embargo, la ausencia de un test runner operativo y la cantidad de cambios pendientes reducen la confianza de liberación. La prioridad no es seguir agregando pantallas, sino cerrar reglas de negocio, estabilizar pruebas y ordenar módulos críticos.",
        WARNING_FILL,
    )

    page_break(doc)
    doc.add_heading("1. Resumen ejecutivo", level=1)
    add_para(doc, "Este informe formal consolida el estado real de los módulos del CRM Noretel según evidencia disponible en el código local. No se trata de una estimación visual de pantallas, sino de una lectura estructurada de rutas, entidades, controladores, módulos habilitados, dependencias y pruebas.")
    add_para(doc, "La conclusión principal es clara: el sistema ya tiene arquitectura modular y superficies funcionales amplias, pero todavía no debe considerarse cerrado. La falta de pruebas ejecutables y los cambios pendientes en Git impiden afirmar estabilidad productiva sin una fase de verificación formal.")
    add_table(
        doc,
        ["Área", "Estado", "Lectura ejecutiva"],
        [
            ["Arquitectura modular", "Avanzada", "Ocho módulos presentes; siete habilitados explícitamente."],
            ["Rutas", "Operativas", route_summary],
            ["Pruebas", "Crítico", "php artisan test no está definido y no hay binario PHPUnit en vendor/bin."],
            ["Worktree", "Riesgo", f"{len(changes)} archivos o entradas aparecen modificados/no versionados."],
            ["Negocio", "En consolidación", "Services se está alineando a Internet + Fibra Óptica + WiFi."],
        ],
        [1.6, 1.4, 3.5],
    )
    add_bullets(doc, [
        "Core, Customer, Services, Contract, Billing, Network y Operations están habilitados.",
        "Public existe como módulo API, pero no aparece en modules_statuses.json.",
        "Billing, Network y Operations son los módulos de mayor riesgo operativo.",
        "Services es el módulo que más condiciona al resto por catálogo, planes y contratos.",
        "La siguiente fase debe ser estabilización, pruebas y cierre de reglas, no crecimiento desordenado.",
    ])

    page_break(doc)
    doc.add_heading("2. Metodología y alcance", level=1)
    add_para(doc, "La evaluación se realizó sobre el repositorio local del proyecto. El análisis considera estructura de carpetas, rutas registradas, modelos, controladores, archivos modificados y disponibilidad del entorno de pruebas. No se ejecutaron flujos funcionales de navegador ni pruebas end-to-end, por lo que el informe distingue entre evidencia estructural y validación funcional.")
    add_para(doc, "La clasificación de estado usa tres criterios: cobertura estructural, criticidad de negocio y riesgo de cambio. Un módulo puede tener muchas rutas y aun así no estar listo si carece de pruebas, auditoría o reglas de negocio cerradas.")
    add_table(
        doc,
        ["Criterio", "Qué mide", "Implicancia"],
        [
            ["Cobertura estructural", "Controladores, entidades, rutas, vistas y repositorios", "Indica avance de implementación, no necesariamente calidad."],
            ["Criticidad", "Impacto en dinero, clientes, red o seguridad", "Determina prioridad de pruebas y auditoría."],
            ["Riesgo", "Acoplamiento, permisos, infraestructura o datos sensibles", "Define qué debe cerrarse antes de producción."],
        ],
        [1.8, 2.4, 2.3],
    )
    add_callout(doc, "Advertencia de alcance", "Este informe no reemplaza QA funcional. Sirve para decidir qué módulos cerrar primero y qué validaciones ejecutar.", LIGHT_BLUE)

    page_break(doc)
    doc.add_heading("3. Inventario general del sistema", level=1)
    add_para(doc, "El sistema está organizado con nwidart/laravel-modules. Los módulos principales representan áreas de negocio y operación: núcleo administrativo, clientes, catálogo comercial, contratos, facturación, red y operación.")
    inv_rows = []
    for row in inventory:
        inv_rows.append([
            str(row["module"]),
            str(row["controllers"]),
            str(row["entities"]),
            str(row["requests"]),
            str(row["views"]),
            "Sí" if row["web_routes"] else "No",
            "Sí" if row["api_routes"] else "No",
        ])
    add_table(doc, ["Módulo", "Ctrl.", "Ent.", "Req.", "Vistas", "Web", "API"], inv_rows, [1.2, .55, .55, .55, .65, .65, .65])
    add_para(doc, "Este inventario muestra avance significativo. Aun así, el conteo de archivos no prueba comportamiento correcto. Un CRM de esta escala necesita pruebas automáticas y criterios de aceptación por módulo.")

    page_break(doc)
    doc.add_heading("4. Estado de habilitación y rutas", level=1)
    status_rows = [[name, "Habilitado" if active else "Inactivo"] for name, active in statuses.items()]
    status_rows.append(["Public", "Existe en código; no listado en modules_statuses.json"])
    add_table(doc, ["Módulo", "Estado declarado"], status_rows, [2.0, 4.5])
    add_para(doc, "La verificación de rutas confirma que Laravel puede registrar una superficie extensa de endpoints web y API. Esto es positivo porque reduce sospechas de errores graves de carga de rutas, pero también exige disciplina: 348 rutas sin pruebas suficientes representan un mapa amplio de posible regresión.")
    add_callout(doc, "Lectura técnica", "La aplicación carga rutas, pero la confianza real debe venir de tests y pruebas funcionales. Arquitectura sin pruebas es apenas una promesa.", WARNING_FILL)

    page_break(doc)
    doc.add_heading("5. Estado del entorno de pruebas", level=1)
    add_para(doc, "El punto más débil del proyecto hoy es la verificabilidad. El repositorio declara PHPUnit en composer.json, pero el binario no está disponible en vendor/bin y el comando php artisan test no existe.")
    add_table(
        doc,
        ["Elemento", "Resultado"],
        [
            ["php artisan test", "No definido en esta instalación."],
            ["vendor/bin/phpunit", "No encontrado."],
            ["Cantidad de tests PHP detectados", str(len(tests["tests"]))],
            ["Tests relevantes detectados", ", ".join(tests["tests"]) if tests["tests"] else "No se detectaron tests."],
        ],
        [2.0, 4.5],
    )
    add_para(doc, "Esto no significa que el sistema no funcione; significa que no puede demostrarse con suficiente confianza. En un CRM que toca pagos, red, clientes y contratos, esa diferencia importa muchísimo.")
    add_numbered(doc, [
        "Restaurar dependencias de desarrollo con Composer.",
        "Habilitar un runner estándar de tests.",
        "Crear pruebas mínimas por módulo crítico.",
        "Ejecutar pruebas antes de cada migración o despliegue.",
    ])

    page_break(doc)
    doc.add_heading("6. Estado del worktree y control de cambios", level=1)
    add_para(doc, f"El repositorio tiene {len(changes)} entradas modificadas o no versionadas según git status --short. Esto no es necesariamente malo, pero sí impide separar con claridad qué está terminado, qué está a medio camino y qué corresponde a experimentos.")
    add_bullets(doc, [
        "Hay cambios en Billing relacionados con Yape/OpenWA.",
        "Hay cambios en Services vinculados al catálogo, planes, promociones y servicios adicionales.",
        "Hay cambios en Customer y documentos.",
        "Hay cambios en herramientas OpenWA y Android.",
        "Existe una migración nueva pendiente para restringir el catálogo de Services.",
    ])
    add_callout(doc, "Riesgo de gestión", "Si se despliega con cambios mezclados, después es difícil saber qué rompió qué. Primero hay que ordenar work units.", RISK_FILL)

    for module, detail in MODULE_DETAILS.items():
        page_break(doc)
        doc.add_heading(f"7. Módulo {module}", level=1)
        add_para(doc, f"Situación actual: {detail['state']}", bold_lead="Situación actual:")
        doc.add_heading("Evidencia observada", level=2)
        add_bullets(doc, detail["evidence"])
        doc.add_heading("Riesgos y puntos de atención", level=2)
        add_bullets(doc, detail["risks"])
        doc.add_heading("Qué sigue", level=2)
        add_numbered(doc, detail["next"])
        add_para(doc, f"Lectura arquitectónica: {module} debe evaluarse no solo por cantidad de pantallas, sino por su impacto sobre el flujo integral del negocio. Un módulo está listo cuando su comportamiento principal es verificable, sus permisos son claros y sus relaciones con otros módulos están documentadas.")
        if module in {"Billing", "Network", "Core"}:
            add_callout(doc, "Prioridad alta", f"{module} debe tener pruebas y auditoría antes de considerarse estable porque afecta seguridad, dinero o infraestructura.", RISK_FILL)
        elif module == "Services":
            add_callout(doc, "Decisión activa", "El catálogo debe quedar cerrado alrededor de Internet + Fibra Óptica + WiFi; TV queda como servicio adicional mientras no se habilite como línea principal.", WARNING_FILL)
        else:
            add_callout(doc, "Próxima madurez", f"{module} requiere validar flujos reales y dependencias con otros módulos antes de ampliar alcance.", LIGHT_BLUE)

    page_break(doc)
    doc.add_heading("8. Dependencias entre módulos", level=1)
    add_para(doc, "El sistema no debe gestionarse como ocho islas. Cada módulo impacta a otro: Customer alimenta Contract, Services define planes, Contract activa instalaciones, Billing cobra, Network ejecuta servicio y Operations orquesta el proceso.")
    add_table(
        doc,
        ["Origen", "Depende de / impacta", "Implicancia"],
        [
            ["Customer", "Contract, Billing, Network", "Datos de cliente deben ser consistentes antes de contrato, cobro y soporte."],
            ["Services", "Contract, Billing, Public", "Planes y servicios son la base comercial del contrato y venta pública."],
            ["Contract", "Customer, Services, Operations", "Formaliza relación comercial y habilita instalación/activación."],
            ["Billing", "Customer, Contract, Yape/OpenWA", "Cobra obligaciones y concilia pagos reales."],
            ["Network", "Customer, Operations", "Ejecuta acciones técnicas sobre infraestructura."],
            ["Operations", "Todos", "Debe ordenar el ciclo completo de alta y activación."],
        ],
        [1.4, 2.0, 3.1],
    )
    add_para(doc, "La prioridad técnica debe seguir el flujo de valor: captar cliente, definir servicio, contratar, instalar, activar, cobrar y soportar. Si se desarrolla fuera de ese flujo, se acumulan pantallas, pero no sistema.")

    page_break(doc)
    doc.add_heading("9. Matriz de riesgos priorizada", level=1)
    add_table(
        doc,
        ["Riesgo", "Severidad", "Módulos", "Acción recomendada"],
        [
            ["Sin test runner operativo", "Alta", "Todos", "Restaurar PHPUnit/runner antes de nuevos cambios grandes."],
            ["Pagos Yape/OpenWA incompletos", "Alta", "Billing", "Validar end-to-end con evidencias reales controladas."],
            ["Acciones SSH/router sin guardrails", "Alta", "Network", "Auditoría, permisos y modo simulación."],
            ["Catálogo comercial inconsistente", "Media/Alta", "Services, Contract, Public", "Ejecutar migración y cerrar regla Internet + Fibra/WiFi."],
            ["APIs públicas duplicadas", "Media", "Public, Services", "Consolidar fuente oficial para frontend."],
            ["Cambios Git mezclados", "Media", "Todos", "Separar por work units antes de commit/despliegue."],
        ],
        [2.0, .9, 1.4, 2.2],
    )

    page_break(doc)
    doc.add_heading("10. Hoja de ruta recomendada", level=1)
    add_para(doc, "La hoja de ruta propone estabilizar primero la base verificable y después cerrar módulos críticos. Es tentador seguir agregando funcionalidad, pero ese camino agranda deuda. Acá conviene disciplina: cerrar, probar, documentar y recién después ampliar.")
    doc.add_heading("Fase 1 - Estabilización técnica", level=2)
    add_numbered(doc, [
        "Restaurar test runner y dependencias de desarrollo.",
        "Ejecutar route:list y migraciones en ambiente controlado.",
        "Separar cambios actuales por unidad revisable.",
        "Crear tests mínimos de Core, Services y Billing.",
    ])
    doc.add_heading("Fase 2 - Cierre de negocio", level=2)
    add_numbered(doc, [
        "Cerrar catálogo Services.",
        "Validar contrato con plan Internet activo.",
        "Modelar TV como adicional.",
        "Validar portal cliente y carga de comprobantes.",
    ])
    doc.add_heading("Fase 3 - Operación y red", level=2)
    add_numbered(doc, [
        "Definir flujo oficial de onboarding.",
        "Auditar acciones Network.",
        "Conectar instalación, activación y contrato.",
        "Agregar monitoreo operativo y reportes.",
    ])

    page_break(doc)
    doc.add_heading("11. Criterios de listo por módulo", level=1)
    add_para(doc, "Para evitar subjetividad, cada módulo debería considerarse listo solo si cumple criterios mínimos. Esto obliga a pasar de 'parece funcionar' a 'podemos demostrar que funciona'.")
    add_table(
        doc,
        ["Criterio", "Condición mínima"],
        [
            ["Rutas", "route:list sin errores y endpoints principales documentados."],
            ["Permisos", "Acciones protegidas por rol y caso denegado probado."],
            ["Datos", "Migraciones aplicadas y relaciones consistentes."],
            ["UI", "Flujo principal probado manualmente."],
            ["API", "Contrato request/response definido."],
            ["Tests", "Pruebas mínimas para casos felices y fallos críticos."],
            ["Auditoría", "Acciones sensibles registradas."],
            ["Rollback", "Cambios reversibles o plan de recuperación claro."],
        ],
        [1.7, 4.8],
    )

    page_break(doc)
    doc.add_heading("12. Recomendación final", level=1)
    add_para(doc, "La recomendación técnica es pausar crecimiento funcional amplio y ejecutar una etapa corta de estabilización. El proyecto ya tiene suficiente superficie como para que la prioridad cambie: ahora importa gobernar el sistema.")
    add_para(doc, "El orden recomendado es: pruebas, catálogo de servicios, pagos, seguridad, operaciones y red. Ese orden responde al riesgo real del negocio: primero asegurar la base, luego cerrar venta/contrato/cobro y finalmente automatizar infraestructura.")
    add_callout(
        doc,
        "Decisión recomendada",
        "Convertir este informe en plan de trabajo por módulos, con tareas verificables, responsables y criterios de aceptación. Sin eso, el sistema va a crecer, pero no necesariamente madurar.",
        OK_FILL,
    )

    page_break(doc)
    doc.add_heading("Anexo A - Archivos modificados detectados", level=1)
    if changes:
        for chunk_start in range(0, len(changes), 35):
            for line in changes[chunk_start: chunk_start + 35]:
                add_para(doc, line)
            if chunk_start + 35 < len(changes):
                page_break(doc)
                doc.add_heading("Anexo A - Archivos modificados detectados (continuación)", level=1)
    else:
        add_para(doc, "No se detectaron cambios pendientes.")

    page_break(doc)
    doc.add_heading("Anexo B - Observaciones de calidad", level=1)
    add_bullets(doc, [
        "El repositorio usa Laravel 10, Sanctum, nwidart/laravel-modules, DomPDF, phpseclib y spatie/laravel-permission.",
        "La separación por módulos es una buena base arquitectónica, pero algunos módulos muestran acoplamiento operativo inevitable.",
        "La ausencia de pruebas ejecutables debe resolverse antes de declarar estabilidad.",
        "Las APIs públicas deben consolidarse para no duplicar responsabilidad entre Public y Services.",
        "Network y Billing requieren el mayor nivel de auditoría por impacto real.",
    ])

    doc.save(OUT)
    print(OUT)


if __name__ == "__main__":
    build_report()
