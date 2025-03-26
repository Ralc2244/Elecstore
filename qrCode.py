import sys
import qrcode
import os

# Obtener los parámetros de la línea de comandos
usuario_id = sys.argv[1]
order_id = sys.argv[2]
email_cliente = sys.argv[3]

# Crear el string con la información del pedido
data = f"Usuario ID: {usuario_id}\nOrden ID: {order_id}\nEmail: {email_cliente}"

# Generar el código QR
qr = qrcode.make(data)

# Definir la ruta para guardar el QR en la carpeta "qrs"
qr_filename = f"qrs/qr_{order_id}.png"

# Guardar el código QR en la carpeta "qrs"
qr.save(qr_filename)

# Imprimir la ruta del archivo para que PHP lo reciba
print(qr_filename)
