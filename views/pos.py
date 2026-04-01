"""Interface Point de Vente (POS) pour les caissiers."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QFileDialog,
    QGridLayout,
    QHBoxLayout,
    QHeaderView,
    QLabel,
    QMessageBox,
    QPushButton,
    QScrollArea,
    QSpinBox,
    QTableWidget,
    QTableWidgetItem,
    QVBoxLayout,
    QWidget,
)

from config import CATEGORIES, CURRENCY_SYMBOL
from controllers.export import generate_ticket_text, save_ticket_pdf, save_ticket_txt
from controllers.pos_controller import CartController
from models.product import list_products


class POSWidget(QWidget):
    """Interface principale du point de vente."""

    def __init__(self, auth_controller):
        super().__init__()
        self.auth = auth_controller
        self.cart = CartController()
        self._current_category = None
        self._setup_ui()
        self._load_products()

    def _setup_ui(self):
        main_layout = QHBoxLayout(self)
        main_layout.setContentsMargins(0, 0, 0, 0)
        main_layout.setSpacing(0)

        # === Panneau gauche: produits ===
        left_panel = QWidget()
        left_layout = QVBoxLayout(left_panel)
        left_layout.setContentsMargins(15, 15, 10, 15)
        left_layout.setSpacing(10)

        # Titre
        title = QLabel("Point de Vente")
        title.setObjectName("page_title")
        left_layout.addWidget(title)

        # Barre de catégories
        cat_layout = QHBoxLayout()
        cat_layout.setSpacing(8)

        # Bouton "Tous"
        btn_all = QPushButton("Tous")
        btn_all.setObjectName("btn_category")
        btn_all.setCheckable(True)
        btn_all.setChecked(True)
        btn_all.setCursor(Qt.PointingHandCursor)
        btn_all.clicked.connect(lambda: self._on_category_selected(None))
        cat_layout.addWidget(btn_all)
        self._category_buttons = [btn_all]

        for cat in CATEGORIES:
            btn = QPushButton(cat)
            btn.setObjectName("btn_category")
            btn.setCheckable(True)
            btn.setCursor(Qt.PointingHandCursor)
            btn.clicked.connect(lambda checked, c=cat: self._on_category_selected(c))
            cat_layout.addWidget(btn)
            self._category_buttons.append(btn)

        cat_layout.addStretch()
        left_layout.addLayout(cat_layout)

        # Grille de produits (scrollable)
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setHorizontalScrollBarPolicy(Qt.ScrollBarAlwaysOff)

        self._product_container = QWidget()
        self._product_grid = QGridLayout(self._product_container)
        self._product_grid.setSpacing(10)
        self._product_grid.setAlignment(Qt.AlignTop | Qt.AlignLeft)
        scroll.setWidget(self._product_container)

        left_layout.addWidget(scroll)
        main_layout.addWidget(left_panel, stretch=65)

        # === Panneau droit: panier ===
        right_panel = QWidget()
        right_panel.setObjectName("cart_panel")
        right_layout = QVBoxLayout(right_panel)
        right_layout.setContentsMargins(15, 15, 15, 15)
        right_layout.setSpacing(10)

        # Titre panier
        cart_title = QLabel("Panier")
        cart_title.setObjectName("page_title")
        right_layout.addWidget(cart_title)

        # Tableau du panier
        self.cart_table = QTableWidget()
        self.cart_table.setColumnCount(5)
        self.cart_table.setHorizontalHeaderLabels(
            ["Produit", "Prix", "Qté", "Sous-total", ""]
        )
        header = self.cart_table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Stretch)
        header.setSectionResizeMode(1, QHeaderView.Fixed)
        header.setSectionResizeMode(2, QHeaderView.Fixed)
        header.setSectionResizeMode(3, QHeaderView.Fixed)
        header.setSectionResizeMode(4, QHeaderView.Fixed)
        self.cart_table.setColumnWidth(1, 80)
        self.cart_table.setColumnWidth(2, 70)
        self.cart_table.setColumnWidth(3, 100)
        self.cart_table.setColumnWidth(4, 50)
        self.cart_table.verticalHeader().setVisible(False)
        self.cart_table.setSelectionMode(QTableWidget.NoSelection)
        self.cart_table.setEditTriggers(QTableWidget.NoEditTriggers)
        right_layout.addWidget(self.cart_table)

        # Total
        self.lbl_total = QLabel(f"Total: 0.00 {CURRENCY_SYMBOL}")
        self.lbl_total.setObjectName("total_label")
        self.lbl_total.setAlignment(Qt.AlignRight)
        right_layout.addWidget(self.lbl_total)

        # Boutons d'action
        btn_layout = QHBoxLayout()
        btn_layout.setSpacing(10)

        btn_clear = QPushButton("Vider le panier")
        btn_clear.setObjectName("btn_secondary")
        btn_clear.setCursor(Qt.PointingHandCursor)
        btn_clear.setMinimumHeight(45)
        btn_clear.clicked.connect(self._on_clear_cart)
        btn_layout.addWidget(btn_clear)

        btn_pay = QPushButton("Payer")
        btn_pay.setObjectName("btn_pay")
        btn_pay.setCursor(Qt.PointingHandCursor)
        btn_pay.clicked.connect(self._on_pay)
        btn_layout.addWidget(btn_pay)

        right_layout.addLayout(btn_layout)
        main_layout.addWidget(right_panel, stretch=35)

    def _on_category_selected(self, category):
        """Filtre les produits par catégorie."""
        self._current_category = category
        # Mettre à jour les boutons
        for btn in self._category_buttons:
            if category is None:
                btn.setChecked(btn.text() == "Tous")
            else:
                btn.setChecked(btn.text() == category)
        self._load_products()

    def _load_products(self):
        """Charge et affiche les produits dans la grille."""
        # Nettoyer la grille
        while self._product_grid.count():
            item = self._product_grid.takeAt(0)
            if item.widget():
                item.widget().deleteLater()

        products = list_products(category=self._current_category)
        cols = 4
        for i, product in enumerate(products):
            row, col = divmod(i, cols)
            btn = QPushButton(f"{product['name']}\n{product['price']:.2f} {CURRENCY_SYMBOL}")
            btn.setObjectName("btn_product")
            btn.setCursor(Qt.PointingHandCursor)
            btn.setMinimumSize(140, 90)
            btn.clicked.connect(lambda checked, p=product: self._on_product_clicked(p))
            self._product_grid.addWidget(btn, row, col)

    def _on_product_clicked(self, product):
        """Ajoute un produit au panier."""
        self.cart.add_item(product)
        self._refresh_cart()

    def _refresh_cart(self):
        """Met à jour l'affichage du panier."""
        items = self.cart.items
        self.cart_table.setRowCount(len(items))

        for row, item in enumerate(items):
            # Nom du produit
            name_item = QTableWidgetItem(item["product_name"])
            self.cart_table.setItem(row, 0, name_item)

            # Prix unitaire
            price_item = QTableWidgetItem(f"{item['unit_price']:.2f}")
            price_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.cart_table.setItem(row, 1, price_item)

            # Quantité (SpinBox)
            spin = QSpinBox()
            spin.setMinimum(1)
            spin.setMaximum(999)
            spin.setValue(item["quantity"])
            spin.setAlignment(Qt.AlignCenter)
            spin.valueChanged.connect(
                lambda val, r=row: self._on_quantity_changed(r, val)
            )
            self.cart_table.setCellWidget(row, 2, spin)

            # Sous-total
            subtotal = item["unit_price"] * item["quantity"]
            st_item = QTableWidgetItem(f"{subtotal:.2f}")
            st_item.setTextAlignment(Qt.AlignRight | Qt.AlignVCenter)
            self.cart_table.setItem(row, 3, st_item)

            # Bouton supprimer
            btn_del = QPushButton("X")
            btn_del.setObjectName("btn_danger")
            btn_del.setCursor(Qt.PointingHandCursor)
            btn_del.setFixedSize(40, 32)
            btn_del.clicked.connect(lambda checked, r=row: self._on_remove_item(r))
            self.cart_table.setCellWidget(row, 4, btn_del)

        total = self.cart.get_total()
        self.lbl_total.setText(f"Total: {total:.2f} {CURRENCY_SYMBOL}")

    def _on_quantity_changed(self, row, value):
        """Met à jour la quantité d'un article."""
        self.cart.update_quantity(row, value)
        self._refresh_cart()

    def _on_remove_item(self, row):
        """Supprime un article du panier."""
        self.cart.remove_item(row)
        self._refresh_cart()

    def _on_clear_cart(self):
        """Vide le panier."""
        if self.cart.is_empty():
            return
        reply = QMessageBox.question(
            self,
            "Vider le panier",
            "Voulez-vous vraiment vider le panier ?",
            QMessageBox.Yes | QMessageBox.No,
        )
        if reply == QMessageBox.Yes:
            self.cart.clear()
            self._refresh_cart()

    def _on_pay(self):
        """Lance le processus de paiement."""
        if self.cart.is_empty():
            QMessageBox.warning(self, "Panier vide", "Ajoutez des produits au panier.")
            return

        from views.dialogs import PaymentDialog

        total = self.cart.get_total()
        dialog = PaymentDialog(total, self)
        if dialog.exec_():
            amount_paid = dialog.get_amount_paid()
            try:
                order_id, change_due = self.cart.validate_order(
                    self.auth.user_id, amount_paid
                )
                self._refresh_cart()
                self._show_ticket(order_id, change_due)
            except Exception as e:
                QMessageBox.critical(self, "Erreur", str(e))

    def _show_ticket(self, order_id, change_due):
        """Affiche le ticket et propose la sauvegarde."""
        ticket_text = generate_ticket_text(order_id)

        msg = QMessageBox(self)
        msg.setWindowTitle("Commande validée")
        msg.setText(f"Commande #{order_id:05d} enregistrée avec succès !")
        msg.setDetailedText(ticket_text)
        msg.setIcon(QMessageBox.Information)

        btn_txt = msg.addButton("Sauvegarder TXT", QMessageBox.ActionRole)
        btn_pdf = msg.addButton("Sauvegarder PDF", QMessageBox.ActionRole)
        msg.addButton("Fermer", QMessageBox.RejectRole)

        msg.exec_()

        clicked = msg.clickedButton()
        if clicked == btn_txt:
            path, _ = QFileDialog.getSaveFileName(
                self, "Sauvegarder le ticket", f"ticket_{order_id:05d}.txt",
                "Fichiers texte (*.txt)"
            )
            if path:
                save_ticket_txt(order_id, path)
        elif clicked == btn_pdf:
            path, _ = QFileDialog.getSaveFileName(
                self, "Sauvegarder le ticket", f"ticket_{order_id:05d}.pdf",
                "Fichiers PDF (*.pdf)"
            )
            if path:
                try:
                    save_ticket_pdf(order_id, path)
                except ImportError:
                    QMessageBox.warning(
                        self, "Module manquant",
                        "Installez reportlab pour l'export PDF:\npip install reportlab"
                    )

    def refresh(self):
        """Rafraîchit la liste des produits."""
        self._load_products()
