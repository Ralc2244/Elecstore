import sys
import qrcode
import os

usuario_id = sys.argv[1]
orden_id = sys.argv[2]
email_cliente = sys.argv[3]

qr_folder = 'C:\\xampp\\htdocs\\elecstore\\qrs'
os.makedirs(qr_folder, exist_ok=True)

data = f"Usuario ID: {usuario_id}\nOrden ID: {orden_id}\nEmail: {email_cliente}"
qr = qrcode.make(data)

qr_filename = f"{qr_folder}\\qr_{orden_id}.png"
qr.save(qr_filename)

print(qr_filename)
