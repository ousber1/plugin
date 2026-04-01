"""Export: génération de tickets (TXT/PDF) et export CSV."""

import csv
import os
from datetime import datetime

from config import CURRENCY_SYMBOL, TICKET_WIDTH
from models.order import get_order_details, get_orders
from models.settings import get_all_settings


def generate_ticket_text(order_id):
    """Génère le texte du ticket pour une commande."""
    settings = get_all_settings()
    details = get_order_details(order_id)
    if not details:
        return ""

    order = details["order"]
    items = details["items"]
    w = TICKET_WIDTH
    sep = "=" * w
    sep_thin = "-" * w

    lines = []
    lines.append(sep)
    lines.append(settings.get("cafe_name", "").center(w))
    lines.append(settings.get("cafe_address", "").center(w))
    lines.append(f"Tél: {settings.get('cafe_phone', '')}".center(w))
    lines.append(sep)

    # Date et numéro de ticket
    date_str = datetime.now().strftime("%d/%m/%Y %H:%M")
    lines.append(f"Date: {date_str}     Ticket #: {order_id:05d}")
    lines.append(f"Caissier: {order['caissier']}")
    lines.append(sep_thin)

    # En-tête des articles
    header = f"{'Produit':<20} {'Qté':>4} {'Prix':>8} {'S/Total':>10}"
    lines.append(header)
    lines.append(sep_thin)

    # Articles
    for item in items:
        name = item["product_name"][:20]
        line = f"{name:<20} {item['quantity']:>4} {item['unit_price']:>8.2f} {item['subtotal']:>10.2f}"
        lines.append(line)

    lines.append(sep_thin)
    lines.append(f"{'TOTAL:':>34} {order['total']:>8.2f} {CURRENCY_SYMBOL}")
    lines.append(f"{'Payé:':>34} {order['amount_paid']:>8.2f} {CURRENCY_SYMBOL}")
    lines.append(f"{'Rendu:':>34} {order['change_due']:>8.2f} {CURRENCY_SYMBOL}")
    lines.append(sep_thin)

    footer = settings.get("ticket_footer", "")
    if footer:
        lines.append(footer.center(w))

    lines.append(sep)
    lines.append("")

    return "\n".join(lines)


def save_ticket_txt(order_id, path):
    """Sauvegarde le ticket en fichier TXT."""
    text = generate_ticket_text(order_id)
    with open(path, "w", encoding="utf-8") as f:
        f.write(text)


def save_ticket_pdf(order_id, path):
    """Sauvegarde le ticket en fichier PDF (format reçu thermique 80mm)."""
    try:
        from reportlab.lib.units import mm
        from reportlab.pdfgen import canvas
    except ImportError:
        raise ImportError("reportlab est requis pour l'export PDF")

    text = generate_ticket_text(order_id)
    ticket_lines = text.split("\n")

    # Format reçu thermique: 80mm de large
    page_width = 80 * mm
    line_height = 4 * mm
    margin_top = 8 * mm
    margin_left = 4 * mm
    page_height = margin_top + (len(ticket_lines) + 2) * line_height

    c = canvas.Canvas(path, pagesize=(page_width, page_height))
    c.setFont("Courier", 8)

    y = page_height - margin_top
    for line in ticket_lines:
        c.drawString(margin_left, y, line)
        y -= line_height

    c.save()


def export_orders_csv(path, date_from=None, date_to=None):
    """Exporte les commandes en fichier CSV."""
    orders = get_orders(date_from=date_from, date_to=date_to)

    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.writer(f, delimiter=";")
        writer.writerow([
            "N° Commande",
            "Date",
            "Caissier",
            f"Total ({CURRENCY_SYMBOL})",
            f"Payé ({CURRENCY_SYMBOL})",
            f"Rendu ({CURRENCY_SYMBOL})",
        ])
        for order in orders:
            writer.writerow([
                order["id"],
                order["created_at"],
                order["caissier"],
                f"{order['total']:.2f}",
                f"{order['amount_paid']:.2f}",
                f"{order['change_due']:.2f}",
            ])
