# Filtro por Estado de Asistencia

## Descripción
Se ha implementado un filtro adicional en la página de historial de asistencia que permite filtrar estudiantes por su estado de asistencia específico.

## Funcionalidad

### Tipos de Estado de Asistencia
- **0**: Incumplimiento injustificado
- **1**: Asistió
- **2**: Inasistencia no programada
- **3**: Inasistencia programada
- **-8**: NA (No aplica)

### Cómo Funciona

1. **Filtro en el Frontend**: El usuario puede seleccionar un tipo de asistencia específico desde el dropdown en la interfaz.

2. **Filtro en el Backend**: El sistema filtra los estudiantes que tienen al menos una asistencia del tipo seleccionado en el rango de fechas especificado.

3. **Modos de Visualización**:
   - **Modo Consolidado** (`range=1`): Muestra asistencias de todos los instructores
   - **Modo Personal** (`range=0`): Solo muestra asistencias del instructor actual

### Implementación Técnica

#### En `history.php`:
- Se captura el parámetro `attendancefilter` de la URL
- Se filtra la lista de estudiantes usando `array_filter()`
- Se verifica que cada estudiante tenga al menos una asistencia del tipo seleccionado
- Se respeta el rango de fechas y el modo de visualización

#### En `history.mustache`:
- Se agregó un select con las opciones de filtro
- Se incluye el parámetro en todos los enlaces de navegación
- Se mantiene el estado del filtro seleccionado

### Parámetros de URL
- `attendancefilter`: Tipo de asistencia a filtrar (0, 1, 2, 3, -8)
- Se mantiene en todos los enlaces de paginación y navegación

### Variables de Contexto del Template
- `attendancefilter`: Valor actual del filtro
- `isattendanceAll`: `true` si no hay filtro aplicado
- `isattendance0`: `true` si se filtra por incumplimiento injustificado
- `isattendance1`: `true` si se filtra por asistió
- `isattendance2`: `true` si se filtra por inasistencia no programada
- `isattendance3`: `true` si se filtra por inasistencia programada

## Uso

1. Navegar a la página de historial de asistencia
2. Seleccionar un tipo de asistencia del dropdown "Filtrar asistencia"
3. Hacer clic en "Filtrar asistencia"
4. Los resultados mostrarán solo estudiantes con al menos una asistencia del tipo seleccionado

## Descargas

El filtro de asistencia también se aplica a las descargas de reportes:

1. Aplicar el filtro de asistencia en la página de historial
2. Hacer clic en "Descargar reporte"
3. El archivo descargado contendrá solo los estudiantes que cumplan con el filtro aplicado
4. El filtro se mantiene al redirigir de vuelta a la página de historial

## Compatibilidad
- Funciona con todos los filtros existentes (fechas, búsqueda, estado del estudiante)
- Se mantiene al navegar entre páginas
- Se incluye en las descargas de reportes
- Se aplica en el archivo `downloader.php` para filtrar los datos del reporte descargado 