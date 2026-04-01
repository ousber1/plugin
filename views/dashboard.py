"""Tableau de bord administrateur."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QFrame,
    QHBoxLayout,
    QHeaderView,
    QLabel,
    QTableWidget,
    QTableWidgetItem,
    QVBoxLayout,
    QWidget,
)

from config import CURRENCY_SYMBOL
from models.order import get_daily_stats, get_top_products


class DashboardWidget(QWidget):
    """Tableau de bord avec statistiques du jour."""

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self.refresh()

    def _setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(25, 20, 25, 20)
        layout.setSpacing(20)

        # Titre
        title = QLabel("Tableau de bord")
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Cartes statistiques
        cards_layout = QHBoxLayout()
        cards_layout.setSpacing(15)

        # Carte: Ventes du jour
        self.card_sales = self._create_card("0.00 " + CURRENCY_SYMBOL, "Ventes du jour")
        cards_layout.addWidget(self.card_sales)

        # Carte: Nombre de commandes
        self.card_orders = self._create_card("0", "Commandes du jour")
        cards_layout.addWidget(self.card_orders)

        # Carte: Panier moyen
        self.card_avg = self._create_card("0.00 " + CURRENCY_SYMBOL, "Panier moyen")
        cards_layout.addWidget(self.card_avg)

        layout.addLayout(cards_layout)

        # Tableau des produits les plus vendus
        lbl_top = QLabel("Produits les plus vendus aujourd'hui")
        lbl_top.setStyleSheet("font-size: 16px; font-weight: bold; padding-top: 10px;")
        layout.addWidget(lbl_top)

        self.table_top = QTableWidget()
        self.table_top.setColumnCount(3)
        self.table_top.setHorizontalHeaderLabels(["Produit", "Quantité vendue", f"Revenu ({CURRENCY_SYMBOL})"])
        header = self.table_top.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Stretch)
        header.setSectionResizeMode(1, QHeaderView.Fixed)
        header.setSectionResizeMode(2, QHeaderView.Fixed)
        self.table_top.setColumnWidth(1, 150)
        self.table_top.setColumnWidth(2, 150)
        self.table_top.verticalHeader().setVisible(False)
        self.table_top.setEditTriggers(QTableWidget.NoEditTriggers)
        layout.addWidget(self.table_top)

    def _create_card(self, value_text, label_text):
        """Crée une carte de statistique."""
        card = QFrame()
        card.setObjectName("card")
        card_layout = QVBoxLayout(card)
        card_layout.setAlignment(Qt.AlignCenter)
        card_layout.setSpacing(8)

        lbl_value = QLabel(value_text)
        lbl_value.setObjectName("card_value")
        lbl_value.setAlignment(Qt.AlignCenter)
        card_layout.addWidget(lbl_value)

        lbl_label = QLabel(label_text)
        lbl_label.setObjectName("card_label")
        lbl_label.setAlignment(Qt.AlignCenter)
        card_layout.addWidget(lbl_label)

        # Stocker les refs pour la mise à jour
        card._value_label = lbl_value
        card._label_label = lbl_label

        return card

    def refresh(self):
        """Rafraîchit les données du tableau de bord."""
        stats = get_daily_stats()
        total_ventes = stats["total_ventes"]
        nb_commandes = stats["nb_commandes"]
        panier_moyen = total_ventes / nb_commandes if nb_commandes > 0 else 0

        self.card_sales._value_label.setText(f"{total_ventes:.2f} {CURRENCY_SYMBOL}")
        self.card_orders._value_label.setText(str(nb_commandes))
        self.card_avg._value_label.setText(f"{panier_moyen:.2f} {CURRENCY_SYMBOL}")

        # Top produits
        top = get_top_products(5)
        self.table_top.setRowCount(len(top))
        for row, product in enumerate(top):
            self.table_top.setItem(row, 0, QTableWidgetItem(product["product_name"]))
            qty_item = QTableWidgetItem(str(product["total_qty"]))
            qty_item.setTextAlignment(Qt.AlignCenter)
            self.table_top.setItem(row, 1, qty_item)
            rev_item = QTableWidgetItem(f"{product['total_revenue']:.2f}")
            rev_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.table_top.setItem(row, 2, rev_item)
