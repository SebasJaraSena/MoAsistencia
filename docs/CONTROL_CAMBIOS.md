# Control de Cambios y Validación de Efectos

## Estructura del Documento

Cada cambio registrado incluye:
- **ID**: Identificador único del cambio
- **Fecha**: Fecha de implementación
- **Descripción**: Detalle del cambio realizado
- **Componentes Afectados**: Módulos o archivos modificados
- **Impacto**: Nivel de impacto en el sistema
- **Validación**: Pruebas realizadas para confirmar funcionamiento
- **Estado**: Estado actual del cambio

## Registro de Cambios

### Cambio #001
- **ID**: CHG-001
- **Fecha**: 26/12/2024
- **Descripción**: Implementación inicial del sistema de asistencia
- **Componentes Afectados**: 
  - Estructura básica del plugin
  - Base de datos inicial
  - Interfaz de usuario base
- **Impacto**: Alto
- **Validación**:
  - Verificación de instalación
  - Comprobación de tablas en BD
  - Validación de permisos básicos
- **Estado**: Completado

### Cambio #002
- **ID**: CHG-002
- **Fecha**: 26/12/2024
- **Descripción**: Implementación del sistema de caché para listas de estudiantes
- **Componentes Afectados**:
  - Archivo: `index.php`
  - Archivo: `attendance.php`
  - Archivo: `history.php`
  - Archivo: `previous_attendance.php`
- **Impacto**: Medio
- **Validación**:
  - Pruebas de rendimiento con/sin caché
  - Verificación de TTL (10 segundos)
  - Pruebas de carga con múltiples cursos
- **Estado**: Completado

### Cambio #003
- **ID**: CHG-003
- **Fecha**: 26/12/2024
- **Descripción**: Implementación de validación de asistencia y retrasos
- **Componentes Afectados**:
  - Archivo: `classes/external/fetch_students.php`
  - Archivo: `externallib.php`
  - Archivo: `attendance.php`
- **Impacto**: Alto
- **Validación**:
  - Pruebas de cierre de asistencia
  - Verificación de permisos
  - Validación de fechas
  - Validación de retrasos
- **Estado**: Completado

### Cambio #004
- **ID**: CHG-004
- **Fecha**: 26/12/2024
- **Descripción**: Implementación de exportación de reportes detallados
- **Componentes Afectados**:
  - Archivo: `classes/util/report_downloader.php`
  - Archivo: `classes/util/detail_report_downloader.php`
  - Archivo: `detailed_report_downloader.php`
- **Impacto**: Medio
- **Validación**:
  - Verificación de formatos de exportación (CSV y PDF)
  - Pruebas con diferentes conjuntos de datos
  - Validación de permisos de descarga
  - Pruebas de generación de reportes detallados
- **Estado**: Completado

### Cambio #005
- **ID**: CHG-005
- **Fecha**: 26/12/2024
- **Descripción**: Integración con base de datos externa y gestión de actividades
- **Componentes Afectados**:
  - Archivo: `classes/external/foreing_db_connection.php`
  - Archivo: `manage.php`
  - Archivo: `activities.php`
- **Impacto**: Alto
- **Validación**:
  - Pruebas de conexión
  - Validación de consultas
  - Manejo de errores
  - Pruebas de gestión de actividades
- **Estado**: Completado

### Cambio #006
- **ID**: CHG-006
- **Fecha**: 26/12/2024
- **Descripción**: Implementación de validación de formularios y observaciones
- **Componentes Afectados**:
  - Archivo: `classes/form/edit.php`
  - Archivo: `attendance.php`
  - Archivo: `amd/src/attendance_observations.js`
- **Impacto**: Medio
- **Validación**:
  - Pruebas de validación de datos
  - Verificación de mensajes de error
  - Pruebas de seguridad de entrada
  - Validación de sistema de observaciones
- **Estado**: Completado

### Cambio #007
- **ID**: CHG-007
- **Fecha**: 26/12/2024
- **Descripción**: Implementación de interfaz JavaScript para asistencia y navegación
- **Componentes Afectados**:
  - Archivo: `amd/src/attendance_observations.js`
  - Archivo: `amd/src/attendance_views.js`
  - Archivo: `lib.php`
- **Impacto**: Medio
- **Validación**:
  - Pruebas de interfaz
  - Validación de selección de horas
  - Pruebas de compatibilidad de navegadores
  - Validación de sistema de navegación
- **Estado**: Completado

### Cambio #008
- **ID**: CHG-008
- **Fecha**: 07/06/2024
- **Descripción**: 
  - Mejoras en el frontend, incluyendo la miga de pan adaptada a la plataforma Zajuna.
  - Implementación de paginación en los listados de asistencia y asistencia anterior.
  - El módulo de actividades fue reemplazado por logs de descarga, con opción de búsqueda y descarga de reportes según criterios.
  - Mejoras en la descarga de reportes Excel en el historial: ahora el nombre del archivo incluye la fecha de descarga en vez de un rango.
  - Actualización de nomenclatura:
    - "no asistió" → "INCUMPLIMIENTO_INJUSTIFICADO"
    - "llegó tarde" → "INASISTENCIA_NO_PROGRAMADA"
    - "excusa médica" → "INASISTENCIA_PROGRAMADA"
  - Correcciones ortográficas generales.
- **Componentes Afectados**:
  - Archivos JS de frontend (`amd/src/attendance_views.js`, `amd/src/attendance_observations.js`)
  - Plantillas Mustache (`templates/activities.mustache`, `templates/history.mustache`, `templates/previous_attendance.mustache`, `templates/studentslist.mustache`)
  - Archivos PHP relacionados con asistencia y reportes (`attendance.php`, `previous_attendance.php`, `download_history.php`, `downloader.php`, `detailed_report_downloader.php`, `classes/util/report_downloader.php`)
  - Archivos de idioma (`lang/en/local_asistencia.php`, `lang/es/local_asistencia.php`)
- **Impacto**: Alto
- **Validación**:
  - Pruebas de navegación y visualización en el frontend.
  - Verificación de paginación y búsqueda en listados.
  - Pruebas de descarga de reportes Excel y validación de nombres de archivo.
  - Revisión de nomenclatura y ortografía en la interfaz y reportes.
- **Estado**: Completado

## Matriz de Impacto de Cambios

| ID | Componente | Rendimiento | Usabilidad | Seguridad | Integridad |
|----|------------|-------------|------------|-----------|------------|
| CHG-001 | Base del sistema | ✓ | ✓ | ✓ | ✓ |
| CHG-002 | Sistema de caché | ✓✓✓ | - | - | ✓ |
| CHG-003 | Validación asistencia | - | ✓ | ✓✓ | ✓✓✓ |
| CHG-004 | Exportación reportes | ✓ | ✓✓✓ | - | ✓ |
| CHG-005 | DB externa y actividades | ✓ | ✓ | ✓✓ | ✓✓ |
| CHG-006 | Validación formularios | - | ✓ | ✓✓✓ | ✓✓ |
| CHG-007 | JavaScript UI y navegación | ✓ | ✓✓✓ | - | ✓ |
| CHG-008 | Mejoras front, paginación, logs, nomenclatura | ✓✓ | ✓✓✓ | - | ✓✓ |

*Nota: ✓=Impacto bajo, ✓✓=Impacto medio, ✓✓✓=Impacto alto*

## Proceso de Validación de Cambios

### 1. Pruebas Unitarias
- Validación de componentes individuales
- Verificación de retorno correcto
- Pruebas con datos inválidos

### 2. Pruebas de Integración
- Verificación de interacción entre componentes
- Pruebas con flujos completos
- Validación de consistencia

### 3. Pruebas de Rendimiento
- Medición de tiempos de respuesta
- Evaluación de uso de recursos
- Pruebas de carga

### 4. Pruebas de Seguridad
- Validación de control de acceso
- Pruebas de inyección
- Verificación de sanitización

## Procedimiento de Control de Cambios

### 1. Solicitud de Cambio
- Documentación del cambio requerido
- Evaluación de impacto
- Aprobación

### 2. Implementación
- Desarrollo del cambio
- Documentación técnica
- Control de versiones

### 3. Validación
- Ejecución de pruebas
- Verificación de resultados
- Documentación de hallazgos

### 4. Implementación
- Despliegue controlado
- Monitoreo post-implementación
- Retroalimentación

---
**Nota**: Este documento debe ser actualizado con cada cambio implementado en el sistema. 