# Portal privado Saeta Vinotinto en cPanel

## Requisitos

- PHP 8.1 o superior con extensiones PDO y PDO MySQL.
- Base de datos MySQL o MariaDB.
- HTTPS activo para `www.saetavinotinto.com`.
- Una cuenta de correo como `socios@saetavinotinto.com`.

## Instalación

1. En cPanel, abre **MySQL Databases**.
2. Crea una base de datos y un usuario dedicado.
3. Asigna el usuario a la base con todos los privilegios.
4. Copia `portal/config.example.php` como `portal/config.local.php`.
5. Completa en `config.local.php` el nombre de base, usuario, contraseña y URL.
6. Sube la carpeta `portal` dentro de `public_html`.
7. Desde **Terminal** de cPanel, crea el primer administrador:

```bash
SAETA_ADMIN_NAME="Nombre Administrador" \
SAETA_ADMIN_EMAIL="correo@saetavinotinto.com" \
SAETA_ADMIN_PASSWORD="una-contraseña-larga" \
php public_html/portal/tools/create-admin.php
```

Las tablas se crean automáticamente durante el primer acceso.

## Importar las fichas existentes

1. Exporta la hoja de Google Sheets como CSV.
2. Sube el CSV a una carpeta privada fuera de `public_html`.
3. Desde Terminal de cPanel ejecuta:

```bash
SAETA_MEMBERS_CSV="/home/USUARIO/privado/miembros.csv" \
php public_html/portal/tools/import-members.php
```

Las fichas aparecerán en Administración. Al invitar un socio, selecciona su ficha y
escribe su correo; el portal conectará ambos registros sin publicar sus datos.

## Importar historial de solicitudes de entradas

Exporta las respuestas de Google Forms como CSV, súbelas fuera de `public_html` y ejecuta:

```bash
SAETA_TICKETS_CSV="/home/USUARIO/privado/entradas-historicas.csv" \
php public_html/portal/tools/import-ticket-history.php
```

El importador detecta columnas habituales de nombre, correo, partido, cantidad, estado y
fecha. Cuando el correo coincide con una cuenta, el historial aparece automáticamente al socio.

## Correo

El portal usa `mail()` de PHP para invitaciones y recuperación de contraseña. Configura
SPF, DKIM y DMARC desde **Email Deliverability** de cPanel para mejorar la entrega.

## Seguridad

- `config.local.php` contiene secretos y no debe compartirse.
- El área privada incluye `noindex`, control de sesión, contraseñas cifradas y CSRF.
- Las carpetas `database`, `includes`, `storage` y `tools` están bloqueadas por Apache.
- Realiza copias de seguridad periódicas desde **Backup** de cPanel.
