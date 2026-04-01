"""Fenêtre de connexion."""

from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtWidgets import (
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QMessageBox,
    QPushButton,
    QVBoxLayout,
    QWidget,
)

from controllers.auth import AuthController


class LoginWindow(QWidget):
    """Interface de connexion avec nom d'utilisateur et mot de passe."""

    login_success = pyqtSignal(dict)  # Émet le dict utilisateur après connexion

    def __init__(self, auth_controller: AuthController):
        super().__init__()
        self.auth = auth_controller
        self._setup_ui()

    def _setup_ui(self):
        self.setWindowTitle("CafePOS - Connexion")
        self.setFixedSize(420, 520)

        layout = QVBoxLayout(self)
        layout.setAlignment(Qt.AlignCenter)
        layout.setSpacing(16)
        layout.setContentsMargins(50, 40, 50, 40)

        # Titre
        title = QLabel("CafePOS")
        title.setObjectName("page_title")
        title.setAlignment(Qt.AlignCenter)
        title.setStyleSheet("font-size: 32px; font-weight: bold;")
        layout.addWidget(title)

        subtitle = QLabel("Système de caisse")
        subtitle.setAlignment(Qt.AlignCenter)
        subtitle.setStyleSheet("font-size: 14px; color: #757575;")
        layout.addWidget(subtitle)

        layout.addSpacing(30)

        # Nom d'utilisateur
        lbl_user = QLabel("Nom d'utilisateur")
        lbl_user.setStyleSheet("font-weight: bold;")
        layout.addWidget(lbl_user)

        self.input_username = QLineEdit()
        self.input_username.setPlaceholderText("Entrez votre nom d'utilisateur")
        layout.addWidget(self.input_username)

        # Mot de passe
        lbl_pass = QLabel("Mot de passe")
        lbl_pass.setStyleSheet("font-weight: bold;")
        layout.addWidget(lbl_pass)

        self.input_password = QLineEdit()
        self.input_password.setPlaceholderText("Entrez votre mot de passe")
        self.input_password.setEchoMode(QLineEdit.Password)
        layout.addWidget(self.input_password)

        layout.addSpacing(20)

        # Bouton connexion
        self.btn_login = QPushButton("Se connecter")
        self.btn_login.setCursor(Qt.PointingHandCursor)
        self.btn_login.setMinimumHeight(50)
        self.btn_login.setStyleSheet("font-size: 16px;")
        self.btn_login.clicked.connect(self._on_login)
        layout.addWidget(self.btn_login)

        layout.addStretch()

        # Copyright
        footer = QLabel("© 2026 CafePOS - Tous droits réservés")
        footer.setAlignment(Qt.AlignCenter)
        footer.setStyleSheet("font-size: 11px; color: #9E9E9E;")
        layout.addWidget(footer)

        # Entrée = connexion
        self.input_password.returnPressed.connect(self._on_login)
        self.input_username.returnPressed.connect(self.input_password.setFocus)

    def _on_login(self):
        """Gère le clic sur le bouton de connexion."""
        username = self.input_username.text().strip()
        password = self.input_password.text().strip()

        if not username or not password:
            QMessageBox.warning(
                self,
                "Champs requis",
                "Veuillez remplir tous les champs.",
            )
            return

        user = self.auth.login(username, password)
        if user:
            self.login_success.emit(user)
        else:
            QMessageBox.critical(
                self,
                "Erreur de connexion",
                "Nom d'utilisateur ou mot de passe incorrect.",
            )
            self.input_password.clear()
            self.input_password.setFocus()

    def reset(self):
        """Réinitialise les champs de connexion."""
        self.input_username.clear()
        self.input_password.clear()
        self.input_username.setFocus()
