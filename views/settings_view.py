"""Paramètres du ticket et de l'application (panneau admin)."""

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import (
    QFormLayout,
    QLabel,
    QLineEdit,
    QMessageBox,
    QPushButton,
    QTextEdit,
    QVBoxLayout,
    QWidget,
)

from models.settings import get_all_settings, set_setting


class SettingsWidget(QWidget):
    """Interface des paramètres du ticket."""

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self.refresh()

    def _setup_ui(self):
        layout = QVBoxLayout(self)
        layout.setContentsMargins(25, 20, 25, 20)
        layout.setSpacing(20)

        # Titre
        title = QLabel("Paramètres du ticket")
        title.setObjectName("page_title")
        layout.addWidget(title)

        # Formulaire
        form = QFormLayout()
        form.setSpacing(15)
        form.setLabelAlignment(Qt.AlignRight)

        self.input_name = QLineEdit()
        self.input_name.setPlaceholderText("Nom de votre café / restaurant")
        self.input_name.setMinimumWidth(400)
        form.addRow("Nom du café:", self.input_name)

        self.input_address = QLineEdit()
        self.input_address.setPlaceholderText("Adresse complète")
        form.addRow("Adresse:", self.input_address)

        self.input_phone = QLineEdit()
        self.input_phone.setPlaceholderText("Numéro de téléphone")
        form.addRow("Téléphone:", self.input_phone)

        self.input_footer = QTextEdit()
        self.input_footer.setPlaceholderText("Message affiché en bas du ticket")
        self.input_footer.setMaximumHeight(80)
        form.addRow("Message ticket:", self.input_footer)

        layout.addLayout(form)

        # Bouton sauvegarder
        btn_save = QPushButton("Enregistrer les paramètres")
        btn_save.setCursor(Qt.PointingHandCursor)
        btn_save.setMinimumHeight(50)
        btn_save.setMaximumWidth(300)
        btn_save.clicked.connect(self._on_save)
        layout.addWidget(btn_save)

        layout.addStretch()

    def refresh(self):
        """Charge les paramètres actuels."""
        settings = get_all_settings()
        self.input_name.setText(settings.get("cafe_name", ""))
        self.input_address.setText(settings.get("cafe_address", ""))
        self.input_phone.setText(settings.get("cafe_phone", ""))
        self.input_footer.setPlainText(settings.get("ticket_footer", ""))

    def _on_save(self):
        """Enregistre les paramètres."""
        cafe_name = self.input_name.text().strip()
        if not cafe_name:
            QMessageBox.warning(self, "Champ requis", "Le nom du café est requis.")
            return

        set_setting("cafe_name", cafe_name)
        set_setting("cafe_address", self.input_address.text().strip())
        set_setting("cafe_phone", self.input_phone.text().strip())
        set_setting("ticket_footer", self.input_footer.toPlainText().strip())

        QMessageBox.information(
            self, "Paramètres enregistrés",
            "Les paramètres du ticket ont été mis à jour."
        )
