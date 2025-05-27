import sys
import qrcode
import os
from datetime import datetime

# Obtener argumentos
usuario_id = sys.argv[1]
orden_id = sys.argv[2]
email_cliente = sys.argv[3]

# Configurar rutas
qr_folder = 'C:\\xampp\\htdocs\\elecstore\\qrs'
os.makedirs(qr_folder, exist_ok=True)

# Datos para el QR
fecha_actual = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
data = f"""ELECSTORE - Reserva
------------------------
Usuario ID: {usuario_id}
Orden ID: {orden_id}
Email: {email_cliente}
Fecha: {fecha_actual}
------------------------
Presentar este código en tienda"""

# Generar QR
qr = qrcode.QRCode(
    version=1,
    error_correction=qrcode.constants.ERROR_CORRECT_H,
    box_size=10,
    border=4,
)
qr.add_data(data)
qr.make(fit=True)

img = qr.make_image(fill_color="black", back_color="white")

# Guardar imagen
qr_filename = f"{qr_folder}\\qr_{orden_id}.png"
img.save(qr_filename)

# Devolver la ruta completa para PHP
print(qr_filename)