"""Dialogues partagés: paiement, formulaire produit, détails commande."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QComboBox,
    QDialog,
    QDoubleSpinBox,
    QFormLayout,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QMessageBox,
    QPushButton,
    QVBoxLayout,
)

from config import CATEGORIES, CURRENCY_SYMBOL


class PaymentDialog(QDialog):
    """Dialogue de paiement: saisie du montant et calcul de la monnaie."""

    def __init__(self, total, parent=None):
        super().__init__(parent)
        self.total = total
        self._amount_paid = 0.0
        self._setup_ui()

    def _setup_ui(self):
        self.setWindowTitle("Paiement")
        self.setFixedSize(400, 380)

        layout = QVBoxLayout(self)
        layout.setSpacing(15)
        layout.setContentsMargins(30, 25, 30, 25)

        # Titre
        title = QLabel("Paiement - Espèces")
        title.setObjectName("page_title")
        title.setAlignment(Qt.AlignCenter)
        layout.addWidget(title)

        layout.addSpacing(10)

        # Total à payer
        lbl_total = QLabel(f"Total à payer: {self.total:.2f} {CURRENCY_SYMBOL}")
        lbl_total.setStyleSheet("font-size: 20px; font-weight: bold;")
        lbl_total.setAlignment(Qt.AlignCenter)
        layout.addWidget(lbl_total)

        layout.addSpacing(10)

        # Montant donné
        lbl_amount = QLabel("Montant donné:")
        lbl_amount.setStyleSheet("font-weight: bold;")
        layout.addWidget(lbl_amount)

        self.input_amount = QDoubleSpinBox()
        self.input_amount.setMaximum(999999.99)
        self.input_amount.setDecimals(2)
        self.input_amount.setSuffix(f" {CURRENCY_SYMBOL}")
        self.input_amount.setValue(self.total)
        self.input_amount.setMinimumHeight(45)
        self.input_amount.setStyleSheet("font-size: 18px;")
        self.input_amount.valueChanged.connect(self._update_change)
        layout.addWidget(self.input_amount)

        # Monnaie à rendre
        self.lbl_change = QLabel(f"Monnaie à rendre: 0.00 {CURRENCY_SYMBOL}")
        self.lbl_change.setStyleSheet(
            "font-size: 18px; font-weight: bold; color: #2E7D32; padding: 10px;"
        )
        self.lbl_change.setAlignment(Qt.AlignCenter)
        layout.addWidget(self.lbl_change)

        layout.addStretch()

        # Boutons
        btn_layout = QHBoxLayout()
        btn_layout.setSpacing(10)

        btn_cancel = QPushButton("Annuler")
        btn_cancel.setObjectName("btn_secondary")
        btn_cancel.setCursor(Qt.PointingHandCursor)
        btn_cancel.setMinimumHeight(45)
        btn_cancel.clicked.connect(self.reject)
        btn_layout.addWidget(btn_cancel)

        self.btn_validate = QPushButton("Valider commande")
        self.btn_validate.setObjectName("btn_pay")
        self.btn_validate.setCursor(Qt.PointingHandCursor)
        self.btn_validate.clicked.connect(self._on_validate)
        btn_layout.addWidget(self.btn_validate)

        layout.addLayout(btn_layout)

        self._update_change()

    def _update_change(self):
        """Met à jour l'affichage de la monnaie à rendre."""
        amount = self.input_amount.value()
        change = max(0, amount - self.total)
        self.lbl_change.setText(f"Monnaie à rendre: {change:.2f} {CURRENCY_SYMBOL}")

        if amount < self.total:
            self.lbl_change.setStyleSheet(
                "font-size: 18px; font-weight: bold; color: #C62828; padding: 10px;"
            )
        else:
            self.lbl_change.setStyleSheet(
                "font-size: 18px; font-weight: bold; color: #2E7D32; padding: 10px;"
            )

    def _on_validate(self):
        """Valide le paiement."""
        amount = self.input_amount.value()
        if amount < self.total:
            QMessageBox.warning(
                self,
                "Montant insuffisant",
                "Le montant donné est inférieur au total.",
            )
            return
        self._amount_paid = amount
        self.accept()

    def get_amount_paid(self):
        """Retourne le montant payé."""
        return self._amount_paid


class ProductFormDialog(QDialog):
    """Dialogue pour ajouter ou modifier un produit."""

    def __init__(self, product=None, parent=None):
        super().__init__(parent)
        self.product = product
        self._setup_ui()

    def _setup_ui(self):
        is_edit = self.product is not None
        self.setWindowTitle("Modifier le produit" if is_edit else "Ajouter un produit")
        self.setFixedSize(420, 350)

        layout = QVBoxLayout(self)
        layout.setSpacing(15)
        layout.setContentsMargins(30, 25, 30, 25)

        # Titre
        title_text = "Modifier le produit" if is_edit else "Nouveau produit"
        title = QLabel(title_text)
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Formulaire
        form = QFormLayout()
        form.setSpacing(12)

        self.input_name = QLineEdit()
        self.input_name.setPlaceholderText("Nom du produit")
        if is_edit:
            self.input_name.setText(self.product["name"])
        form.addRow("Nom:", self.input_name)

        self.input_category = QComboBox()
        self.input_category.addItems(CATEGORIES)
        if is_edit:
            idx = CATEGORIES.index(self.product["category"]) if self.product["category"] in CATEGORIES else 0
            self.input_category.setCurrentIndex(idx)
        form.addRow("Catégorie:", self.input_category)

        self.input_price = QDoubleSpinBox()
        self.input_price.setMaximum(99999.99)
        self.input_price.setDecimals(2)
        self.input_price.setSuffix(f" {CURRENCY_SYMBOL}")
        if is_edit:
            self.input_price.setValue(self.product["price"])
        form.addRow("Prix:", self.input_price)

        self.input_image = QLineEdit()
        self.input_image.setPlaceholderText("Chemin de l'image (optionnel)")
        if is_edit and self.product.get("image_path"):
            self.input_image.setText(self.product["image_path"])
        form.addRow("Image:", self.input_image)

        layout.addLayout(form)
        layout.addStretch()

        # Boutons
        btn_layout = QHBoxLayout()
        btn_layout.setSpacing(10)

        btn_cancel = QPushButton("Annuler")
        btn_cancel.setObjectName("btn_secondary")
        btn_cancel.setCursor(Qt.PointingHandCursor)
        btn_cancel.clicked.connect(self.reject)
        btn_layout.addWidget(btn_cancel)

        btn_save = QPushButton("Enregistrer")
        btn_save.setCursor(Qt.PointingHandCursor)
        btn_save.clicked.connect(self._on_save)
        btn_layout.addWidget(btn_save)

        layout.addLayout(btn_layout)

    def _on_save(self):
        """Valide et enregistre le produit."""
        name = self.input_name.text().strip()
        if not name:
            QMessageBox.warning(self, "Champ requis", "Le nom du produit est requis.")
            return
        if self.input_price.value() <= 0:
            QMessageBox.warning(self, "Prix invalide", "Le prix doit être supérieur à 0.")
            return
        self.accept()

    def get_data(self):
        """Retourne les données du formulaire."""
        return {
            "name": self.input_name.text().strip(),
            "category": self.input_category.currentText(),
            "price": self.input_price.value(),
            "image_path": self.input_image.text().strip() or None,
        }


class OrderDetailDialog(QDialog):
    """Dialogue affichant les détails d'une commande."""

    def __init__(self, order_details, parent=None):
        super().__init__(parent)
        self.details = order_details
        self._setup_ui()

    def _setup_ui(self):
        self.setWindowTitle(f"Commande #{self.details['order']['id']:05d}")
        self.setFixedSize(500, 450)

        layout = QVBoxLayout(self)
        layout.setSpacing(12)
        layout.setContentsMargins(25, 20, 25, 20)

        order = self.details["order"]

        # En-tête
        title = QLabel(f"Commande #{order['id']:05d}")
        title.setObjectName("page_title")
        layout.addWidget(title)

        info_layout = QFormLayout()
        info_layout.addRow("Date:", QLabel(order["created_at"]))
        info_layout.addRow("Caissier:", QLabel(order["caissier"]))
        layout.addLayout(info_layout)

        # Articles
        layout.addWidget(QLabel("Articles:"))

        from PyQt5.QtWidgets import QTableWidget, QTableWidgetItem, QHeaderView

        table = QTableWidget()
        table.setColumnCount(4)
        table.setHorizontalHeaderLabels(["Produit", "Prix", "Qté", "Sous-total"])
        header = table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Stretch)
        table.verticalHeader().setVisible(False)
        table.setEditTriggers(QTableWidget.NoEditTriggers)

        items = self.details["items"]
        table.setRowCount(len(items))
        for row, item in enumerate(items):
            table.setItem(row, 0, QTableWidgetItem(item["product_name"]))
            price_item = QTableWidgetItem(f"{item['unit_price']:.2f}")
            price_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            table.setItem(row, 1, price_item)
            qty_item = QTableWidgetItem(str(item["quantity"]))
            qty_item.setTextAlignment(Qt.AlignCenter)
            table.setItem(row, 2, qty_item)
            st_item = QTableWidgetItem(f"{item['subtotal']:.2f}")
            st_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            table.setItem(row, 3, st_item)

        layout.addWidget(table)

        # Totaux
        totals = QFormLayout()
        totals.addRow("Total:", QLabel(f"{order['total']:.2f} {CURRENCY_SYMBOL}"))
        totals.addRow("Payé:", QLabel(f"{order['amount_paid']:.2f} {CURRENCY_SYMBOL}"))
        totals.addRow("Rendu:", QLabel(f"{order['change_due']:.2f} {CURRENCY_SYMBOL}"))
        layout.addLayout(totals)

        # Bouton fermer
        btn_close = QPushButton("Fermer")
        btn_close.setCursor(Qt.PointingHandCursor)
        btn_close.clicked.connect(self.close)
        layout.addWidget(btn_close)
