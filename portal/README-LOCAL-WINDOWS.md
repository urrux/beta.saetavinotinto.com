# PHP local en Windows

Esta instalación sirve para validar y ejecutar el portal localmente. El sitio publicado
seguirá usando PHP y MySQL de cPanel.

1. Instala **Microsoft Visual C++ Redistributable 2022 x64** si todavía no está presente.
2. Descarga la compilación oficial **PHP x64 Non Thread Safe** desde:
   `https://windows.php.net/download/`
3. Descomprime el archivo ZIP en `C:\php`.
4. Copia `C:\php\php.ini-development` como `C:\php\php.ini`.
5. Edita `php.ini` y confirma estas líneas:

```ini
extension_dir = "ext"
extension=pdo_sqlite
extension=sqlite3
extension=mbstring
extension=pdo_mysql
```

6. Busca **Editar las variables de entorno del sistema** en Windows.
7. En la variable `Path`, agrega `C:\php`.
8. Cierra y abre una terminal nueva.
9. Verifica:

```powershell
php -v
php -m
```

Para abrir el portal localmente desde la carpeta del proyecto:

```powershell
php -S localhost:8080
```

Luego visita `http://localhost:8080/portal/login.php`.

Para validar la sintaxis de todos los archivos PHP:

```powershell
Get-ChildItem portal -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```
