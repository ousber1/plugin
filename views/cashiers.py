"""Gestion des caissiers (panneau admin)."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QFormLayout,
    QHBoxLayout,
    QHeaderView,
    QLabel,
    QLineEdit,
    QMessageBox,
    QPushButton,
    QTableWidget,
    QTableWidgetItem,
    QVBoxLayout,
    QWidget,
)

from models.user import create_user, delete_user, list_cashiers


class CashierManagerWidget(QWidget):
    """Interface de gestion des caissiers."""

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self.refresh()

    def _setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(25, 20, 25, 20)
        layout.setSpacing(15)

        # Titre
        title = QLabel("Gestion des caissiers")
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Formulaire d'ajout
        add_layout = QHBoxLayout()
        add_layout.setSpacing(10)

        form = QFormLayout()
        form.setSpacing(8)

        self.input_username = QLineEdit()
        self.input_username.setPlaceholderText("Nom d'utilisateur")
        self.input_username.setMinimumWidth(200)
        form.addRow("Nom:", self.input_username)

        self.input_password = QLineEdit()
        self.input_password.setPlaceholderText("Mot de passe")
        self.input_password.setEchoMode(QLineEdit.Password)
        self.input_password.setMinimumWidth(200)
        form.addRow("Mot de passe:", self.input_password)

        add_layout.addLayout(form)

        btn_add = QPushButton("Ajouter caissier")
        btn_add.setCursor(Qt.PointingHandCursor)
        btn_add.setMinimumHeight(45)
        btn_add.clicked.connect(self._on_add)
        add_layout.addWidget(btn_add, alignment=Qt.AlignBottom)

        add_layout.addStretch()
        layout.addLayout(add_layout)

        # Tableau des caissiers
        self.table = QTableWidget()
        self.table.setColumnCount(4)
        self.table.setHorizontalHeaderLabels(["ID", "Nom d'utilisateur", "Date de création", "Action"])
        header = self.table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Fixed)
        header.setSectionResizeMode(1, QHeaderView.Stretch)
        header.setSectionResizeMode(2, QHeaderView.Fixed)
        header.setSectionResizeMode(3, QHeaderView.Fixed)
        self.table.setColumnWidth(0, 60)
        self.table.setColumnWidth(2, 180)
        self.table.setColumnWidth(3, 120)
        self.table.verticalHeader().setVisible(False)
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        layout.addWidget(self.table)

    def _on_add(self):
        """Ajoute un nouveau caissier."""
        username = self.input_username.text().strip()
        password = self.input_password.text().strip()

        if not username or not password:
            QMessageBox.warning(
                self, "Champs requis", "Le nom et le mot de passe sont requis."
            )
            return

        if len(password) < 4:
            QMessageBox.warning(
                self, "Mot de passe trop court",
                "Le mot de passe doit contenir au moins 4 caractères."
            )
            return

        success = create_user(username, password, role="caissier")
        if success:
            self.input_username.clear()
            self.input_password.clear()
            self.refresh()
            QMessageBox.information(
                self, "Succès", f"Caissier « {username} » ajouté avec succès."
            )
        else:
            QMessageBox.critical(
                self, "Erreur",
                f"Le nom d'utilisateur « {username} » existe déjà."
            )

    def refresh(self):
        """Rafraîchit la liste des caissiers."""
        cashiers = list_cashiers()
        self.table.setRowCount(len(cashiers))

        for row, cashier in enumerate(cashiers):
            self.table.setItem(row, 0, QTableWidgetItem(str(cashier["id"])))
            self.table.setItem(row, 1, QTableWidgetItem(cashier["username"]))
            self.table.setItem(row, 2, QTableWidgetItem(cashier["created_at"]))

            btn_del = QPushButton("Supprimer")
            btn_del.setObjectName("btn_danger")
            btn_del.setCursor(Qt.PointingHandCursor)
            btn_del.clicked.connect(
                lambda checked, uid=cashier["id"], uname=cashier["username"]: self._on_delete(uid, uname)
            )
            self.table.setCellWidget(row, 3, btn_del)

    def _on_delete(self, user_id, username):
        """Supprime un caissier après confirmation."""
        reply = QMessageBox.question(
            self,
            "Confirmer la suppression",
            f"Voulez-vous vraiment supprimer le caissier « {username} » ?",
            QMessageBox.Yes | QMessageBox.No,
        )
        if reply == QMessageBox.Yes:
            delete_user(user_id)
            self.refresh()
