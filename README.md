# proyectoPPCH
PPCH - Sistema de Gestión de Recursos y Flota


# Sistema de Gestión - HECSO

Plataforma web para la gestión de trabajadores, flota vehicular y control de rutas.

## 🔒 Confidencialidad
Este software es propiedad exclusiva de HECSO. Su acceso, distribución y modificación están restringidos al personal autorizado. Contiene información sensible protegida por acuerdos de confidencialidad.

## 📋 Requisitos Previos
*   PHP 8.0 o superior
*   MySQL / MariaDB
*   Composer (Gestor de dependencias)
*   Tesseract OCR (Para lectura de documentos)

## 🚀 Instalación (Entorno Local)

1.  **Clonar el repositorio:**
    ```bash
    git clone https://github.com/usuario/repo-hecso.git
    ```

2.  **Instalar dependencias:**
    ```bash
    composer install
    ```

3.  **Configurar base de datos:**
    *   Crear una base de datos vacía.
    *   Importar el archivo `scripts/database.sql`.
    *   Crear el archivo `config/db.php` basado en el ejemplo y configurar las credenciales.

4.  **Configurar permisos:**
    *   Asegurar permisos de escritura en la carpeta `assets/uploads/`.

## 🛠 Funcionalidades Principales
*   **Portal Trabajador:** Versión móvil para registro de rutas y firmas.
*   **Gestión Documental:** Lectura automática de PDFs y control de vencimientos.
*   **Flota:** Control de vehículos, mantenciones y combustible.

## 📞 Soporte
Para problemas técnicos o accesos, contactar al departamento de TI o al administrador del sistema.
