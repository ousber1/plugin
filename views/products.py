"""Gestion des produits (panneau admin)."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QComboBox,
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

from config import CATEGORIES, CURRENCY_SYMBOL
from models.product import create_product, delete_product, list_products, update_product
from views.dialogs import ProductFormDialog


class ProductManagerWidget(QWidget):
    """Interface de gestion des produits."""

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self.refresh()

    def _setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(25, 20, 25, 20)
        layout.setSpacing(15)

        # Titre
        title = QLabel("Gestion des produits")
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Barre d'outils
        toolbar = QHBoxLayout()
        toolbar.setSpacing(10)

        # Filtre catégorie
        lbl_filter = QLabel("Catégorie:")
        toolbar.addWidget(lbl_filter)

        self.combo_category = QComboBox()
        self.combo_category.addItem("Toutes")
        self.combo_category.addItems(CATEGORIES)
        self.combo_category.currentTextChanged.connect(self._on_filter_changed)
        toolbar.addWidget(self.combo_category)

        toolbar.addStretch()

        # Bouton ajouter
        btn_add = QPushButton("+ Ajouter un produit")
        btn_add.setCursor(Qt.PointingHandCursor)
        btn_add.clicked.connect(self._on_add)
        toolbar.addWidget(btn_add)

        layout.addLayout(toolbar)

        # Tableau
        self.table = QTableWidget()
        self.table.setColumnCount(6)
        self.table.setHorizontalHeaderLabels(
            ["ID", "Nom", "Catégorie", f"Prix ({CURRENCY_SYMBOL})", "Modifier", "Supprimer"]
        )
        header = self.table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Fixed)
        header.setSectionResizeMode(1, QHeaderView.Stretch)
        header.setSectionResizeMode(2, QHeaderView.Fixed)
        header.setSectionResizeMode(3, QHeaderView.Fixed)
        header.setSectionResizeMode(4, QHeaderView.Fixed)
        header.setSectionResizeMode(5, QHeaderView.Fixed)
        self.table.setColumnWidth(0, 60)
        self.table.setColumnWidth(2, 120)
        self.table.setColumnWidth(3, 110)
        self.table.setColumnWidth(4, 100)
        self.table.setColumnWidth(5, 100)
        self.table.verticalHeader().setVisible(False)
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        layout.addWidget(self.table)

    def _on_filter_changed(self, text):
        """Filtre les produits par catégorie."""
        self.refresh()

    def refresh(self):
        """Rafraîchit la liste des produits."""
        category = self.combo_category.currentText()
        if category == "Toutes":
            category = None

        products = list_products(category=category, active_only=True)
        self.table.setRowCount(len(products))

        for row, product in enumerate(products):
            self.table.setItem(row, 0, QTableWidgetItem(str(product["id"])))

            name_item = QTableWidgetItem(product["name"])
            self.table.setItem(row, 1, name_item)

            self.table.setItem(row, 2, QTableWidgetItem(product["category"]))

            price_item = QTableWidgetItem(f"{product['price']:.2f}")
            price_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.table.setItem(row, 3, price_item)

            # Bouton modifier
            btn_edit = QPushButton("Modifier")
            btn_edit.setObjectName("btn_secondary")
            btn_edit.setCursor(Qt.PointingHandCursor)
            btn_edit.clicked.connect(
                lambda checked, p=product: self._on_edit(p)
            )
            self.table.setCellWidget(row, 4, btn_edit)

            # Bouton supprimer
            btn_del = QPushButton("Supprimer")
            btn_del.setObjectName("btn_danger")
            btn_del.setCursor(Qt.PointingHandCursor)
            btn_del.clicked.connect(
                lambda checked, pid=product["id"], pname=product["name"]: self._on_delete(pid, pname)
            )
            self.table.setCellWidget(row, 5, btn_del)

    def _on_add(self):
        """Ouvre le dialogue d'ajout de produit."""
        dialog = ProductFormDialog(parent=self)
        if dialog.exec_():
            data = dialog.get_data()
            create_product(data["name"], data["category"], data["price"], data["image_path"])
            self.refresh()

    def _on_edit(self, product):
        """Ouvre le dialogue de modification de produit."""
        dialog = ProductFormDialog(product=product, parent=self)
        if dialog.exec_():
            data = dialog.get_data()
            update_product(product["id"], data["name"], data["category"], data["price"], data["image_path"])
            self.refresh()

    def _on_delete(self, product_id, product_name):
        """Supprime un produit après confirmation."""
        reply = QMessageBox.question(
            self,
            "Confirmer la suppression",
            f"Voulez-vous vraiment supprimer « {product_name} » ?",
            QMessageBox.Yes | QMessageBox.No,
        )
        if reply == QMessageBox.Yes:
            delete_product(product_id)
            self.refresh()
