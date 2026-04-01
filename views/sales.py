"""Historique des ventes (panneau admin)."""

from datetime import date

from PyQt5.QtCore import QDate, Qt
from PyQt5.QtWidgets import (
    QDateEdit,
    QFileDialog,
    QHBoxLayout,
    QHeaderView,
    QLabel,
    QMessageBox,
    QPushButton,
    QTableWidget,
    QTableWidgetItem,
    QVBoxLayout,
    QWidget,
)

from config import CURRENCY_SYMBOL
from controllers.export import export_orders_csv
from models.order import get_order_details, get_orders
from views.dialogs import OrderDetailDialog


class SalesHistoryWidget(QWidget):
    """Interface de l'historique des ventes."""

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self.refresh()

    def _setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(25, 20, 25, 20)
        layout.setSpacing(15)

        # Titre
        title = QLabel("Historique des ventes")
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Filtres
        filter_layout = QHBoxLayout()
        filter_layout.setSpacing(10)

        lbl_from = QLabel("Du:")
        filter_layout.addWidget(lbl_from)

        self.date_from = QDateEdit()
        self.date_from.setCalendarPopup(True)
        self.date_from.setDate(QDate.currentDate())
        self.date_from.setDisplayFormat("dd/MM/yyyy")
        filter_layout.addWidget(self.date_from)

        lbl_to = QLabel("Au:")
        filter_layout.addWidget(lbl_to)

        self.date_to = QDateEdit()
        self.date_to.setCalendarPopup(True)
        self.date_to.setDate(QDate.currentDate())
        self.date_to.setDisplayFormat("dd/MM/yyyy")
        filter_layout.addWidget(self.date_to)

        btn_filter = QPushButton("Filtrer")
        btn_filter.setCursor(Qt.PointingHandCursor)
        btn_filter.clicked.connect(self.refresh)
        filter_layout.addWidget(btn_filter)

        filter_layout.addStretch()

        # Bouton export CSV
        btn_export = QPushButton("Exporter CSV")
        btn_export.setObjectName("btn_secondary")
        btn_export.setCursor(Qt.PointingHandCursor)
        btn_export.clicked.connect(self._on_export_csv)
        filter_layout.addWidget(btn_export)

        layout.addLayout(filter_layout)

        # Tableau des commandes
        self.table = QTableWidget()
        self.table.setColumnCount(6)
        self.table.setHorizontalHeaderLabels(
            ["N°", "Date", "Caissier", f"Total ({CURRENCY_SYMBOL})",
             f"Payé ({CURRENCY_SYMBOL})", "Détails"]
        )
        header = self.table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Fixed)
        header.setSectionResizeMode(1, QHeaderView.Stretch)
        header.setSectionResizeMode(2, QHeaderView.Fixed)
        header.setSectionResizeMode(3, QHeaderView.Fixed)
        header.setSectionResizeMode(4, QHeaderView.Fixed)
        header.setSectionResizeMode(5, QHeaderView.Fixed)
        self.table.setColumnWidth(0, 70)
        self.table.setColumnWidth(2, 130)
        self.table.setColumnWidth(3, 110)
        self.table.setColumnWidth(4, 110)
        self.table.setColumnWidth(5, 90)
        self.table.verticalHeader().setVisible(False)
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        layout.addWidget(self.table)

        # Résumé
        self.lbl_summary = QLabel()
        self.lbl_summary.setStyleSheet("font-size: 14px; font-weight: bold; padding: 5px;")
        layout.addWidget(self.lbl_summary)

    def refresh(self):
        """Rafraîchit la liste des commandes."""
        date_from = self.date_from.date().toString("yyyy-MM-dd")
        date_to = self.date_to.date().toString("yyyy-MM-dd")

        orders = get_orders(date_from=date_from, date_to=date_to)
        self.table.setRowCount(len(orders))

        total_sum = 0
        for row, order in enumerate(orders):
            self.table.setItem(row, 0, QTableWidgetItem(f"{order['id']:05d}"))
            self.table.setItem(row, 1, QTableWidgetItem(order["created_at"]))
            self.table.setItem(row, 2, QTableWidgetItem(order["caissier"]))

            total_item = QTableWidgetItem(f"{order['total']:.2f}")
            total_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.table.setItem(row, 3, total_item)

            paid_item = QTableWidgetItem(f"{order['amount_paid']:.2f}")
            paid_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.table.setItem(row, 4, paid_item)

            btn_detail = QPushButton("Voir")
            btn_detail.setObjectName("btn_secondary")
            btn_detail.setCursor(Qt.PointingHandCursor)
            btn_detail.clicked.connect(
                lambda checked, oid=order["id"]: self._on_view_details(oid)
            )
            self.table.setCellWidget(row, 5, btn_detail)

            total_sum += order["total"]

        self.lbl_summary.setText(
            f"Total: {len(orders)} commande(s) — {total_sum:.2f} {CURRENCY_SYMBOL}"
        )

    def _on_view_details(self, order_id):
        """Affiche les détails d'une commande."""
        details = get_order_details(order_id)
        if details:
            dialog = OrderDetailDialog(details, parent=self)
            dialog.exec_()

    def _on_export_csv(self):
        """Exporte les commandes en CSV."""
        path, _ = QFileDialog.getSaveFileName(
            self, "Exporter les ventes", "ventes.csv",
            "Fichiers CSV (*.csv)"
        )
        if path:
            date_from = self.date_from.date().toString("yyyy-MM-dd")
            date_to = self.date_to.date().toString("yyyy-MM-dd")
            try:
                export_orders_csv(path, date_from=date_from, date_to=date_to)
                QMessageBox.information(
                    self, "Export réussi",
                    f"Les ventes ont été exportées vers:\n{path}"
                )
            except Exception as e:
                QMessageBox.critical(self, "Erreur d'export", str(e))
