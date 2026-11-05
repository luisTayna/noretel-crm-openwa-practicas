<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud de cobertura recibida</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; background-color: #f3f4f6; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; padding: 32px;">
        <h1 style="font-size: 20px; margin-top: 0;">Hola {{ $name }},</h1>

        <p>Hemos recibido tu solicitud de cobertura para la siguiente dirección:</p>

        <p style="background-color: #f9fafb; border-radius: 6px; padding: 12px 16px;">
            {{ $address }}
        </p>

        <p>
            Nuestro equipo la está revisando y te contactaremos en cuanto tengamos
            novedades sobre la disponibilidad del servicio en tu zona.
        </p>

        <p style="margin-top: 32px; color: #6b7280; font-size: 13px;">
            Este es un mensaje automático, por favor no respondas a este correo.
        </p>
    </div>
</body>
</html>
