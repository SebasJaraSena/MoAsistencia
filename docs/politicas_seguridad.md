# Políticas de Seguridad - Sistema de Asistencia

## Índice
1. [Introducción](#introducción)
2. [Control de Acceso](#control-de-acceso)
3. [Validación de Datos](#validación-de-datos)
4. [Seguridad en Base de Datos](#seguridad-en-base-de-datos)
5. [Protección de Sesiones](#protección-de-sesiones)
6. [Auditoría y Logging](#auditoría-y-logging)
7. [Respuesta a Incidentes](#respuesta-a-incidentes)
8. [Seguridad en Caché](#seguridad-en-caché)
9. [Seguridad en Observaciones](#seguridad-en-observaciones)

## Introducción

Este documento define las políticas y procedimientos de seguridad implementados en el plugin de asistencia para Moodle. Estas políticas son obligatorias y deben ser seguidas por todos los desarrolladores y administradores del sistema.

## Control de Acceso

### 1. Autenticación
```php
// Implementado en attendance.php
require_login(); // Obligatorio en todos los puntos de entrada

// Verificación de capacidades
require_capability('local/asistencia:view', $context);
```

### 2. Roles y Permisos
```php
// Definido en db/access.php
$capabilities = [
    'local/asistencia:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'local/asistencia:edit' => [
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW
        ]
    ]
];
```

### 3. Validación de Contexto
```php
public static function validate_context($contextid) {
    // Verificar que el contexto existe y es válido
    $context = context::instance_by_id($contextid);
    self::validate_context($context);
    
    // Verificar permisos en el contexto
    if (!has_capability('local/asistencia:view', $context)) {
        throw new required_capability_exception($context, 'local/asistencia:view', 'nopermissions', 'local_asistencia');
    }
}
```

## Validación de Datos

### 1. Entrada de Usuario
```php
class input_validator {
    public static function validate_attendance_data($data) {
        // Validar formato de fecha
        if (!self::is_valid_date($data['date'])) {
            throw new invalid_parameter_exception('Formato de fecha inválido');
        }
        
        // Validar estado de asistencia
        if (!in_array($data['attendance'], [-1, 0, 1, 2, 3, -8])) {
            throw new invalid_parameter_exception('Estado de asistencia inválido');
        }
        
        // Validar horas
        if ($data['amounthours'] < 0 || $data['amounthours'] > 24) {
            throw new invalid_parameter_exception('Cantidad de horas inválida');
        }
        
        // Sanitizar observaciones
        $data['observations'] = clean_param($data['observations'], PARAM_TEXT);
        
        // Validar retrasos
        if (isset($data['retard'])) {
            self::validate_retard($data['retard']);
        }
        
        return $data;
    }
}
```

### 2. Sanitización de Salida
```php
// En templates y salida HTML
$output = format_text($data, FORMAT_HTML, ['trusted' => false]);
echo clean_text($output);

// En observaciones
$observations = clean_param($observations, PARAM_TEXT);
```

## Seguridad en Base de Datos

### 1. Prevención de SQL Injection
```php
public static function get_attendance_records($courseid, $studentid) {
    global $DB;
    
    // Usar parámetros nombrados
    $params = [
        'courseid' => $courseid,
        'studentid' => $studentid
    ];
    
    // Usar API de Moodle para consultas
    return $DB->get_records('local_asistencia_permanente', $params);
}
```

### 2. Transacciones
```php
public static function save_attendance_batch($records) {
    global $DB;
    
    try {
        $transaction = $DB->start_delegated_transaction();
        
        foreach ($records as $record) {
            self::validate_attendance_data($record);
            $DB->insert_record('local_asistencia_permanente', $record);
        }
        
        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
    }
}
```

## Protección de Sesiones

### 1. Manejo de Sesiones
```php
// Implementado en cada archivo PHP
require_sesskey(); // Para operaciones POST

// Validación en formularios
$mform->addElement('hidden', 'sesskey', sesskey());

// Manejo de breadcrumbs
if (!isset($SESSION->asistencia_breadcrumb)) {
    $SESSION->asistencia_breadcrumb = [];
}
```

### 2. Prevención de CSRF
```php
// En formularios
$url = new moodle_url('/local/asistencia/attendance.php');
$mform = new attendance_form($url);

// Validación
if ($fromform = $mform->get_data()) {
    require_sesskey();
    // Procesar datos
}
```

## Auditoría y Logging

### 1. Registro de Eventos
```php
class attendance_logger {
    public static function log_attendance_change($courseid, $userid, $action) {
        $event = \local_asistencia\event\attendance_updated::create([
            'contextid' => context_course::instance($courseid)->id,
            'userid' => $userid,
            'other' => [
                'action' => $action
            ]
        ]);
        $event->trigger();
    }
}
```

### 2. Monitoreo de Actividades
```php
public static function monitor_suspicious_activity($userid) {
    $attempts = get_user_preferences('local_asistencia_failed_attempts', 0, $userid);
    
    if ($attempts > 5) {
        self::notify_admin("Actividad sospechosa detectada para el usuario $userid");
        self::block_temporary_access($userid);
    }
}
```

## Seguridad en Caché

### 1. Manejo de Caché
```php
// Implementación segura de caché
$cache = cache::make('local_asistencia', 'coursestudentslist');
$cache->set("course$courseid.user$userid", $condition);

// Limpieza de caché
$cache->delete("course$courseid.user$userid");
```

### 2. Protección de Datos en Caché
```php
// Sanitización de datos antes de almacenar en caché
$cachedata = clean_param($data, PARAM_RAW);
$cache->set($key, $cachedata);
```

## Seguridad en Observaciones

### 1. Validación de Observaciones
```php
public static function validate_observations($observations) {
    // Limitar longitud
    if (strlen($observations) > 500) {
        throw new invalid_parameter_exception('Las observaciones exceden el límite de caracteres');
    }
    
    // Sanitizar contenido
    return clean_param($observations, PARAM_TEXT);
}
```

### 2. Manejo de Observaciones en JavaScript
```javascript
// En attendance_observations.js
define(['jquery'], function($) {
    return {
        init: function() {
            // Validación en cliente
            $('#observations').on('input', function() {
                if ($(this).val().length > 500) {
                    // Mostrar error
                }
            });
        }
    };
});
```

## Respuesta a Incidentes

### 1. Manejo de Errores
```php
public static function handle_security_incident($error, $context) {
    // Registrar incidente
    self::log_security_incident($error, $context);
    
    // Limpiar caché si es necesario
    if ($error->getCode() === 'cache_compromise') {
        self::clear_compromised_cache();
    }
    
    // Notificar administradores
    self::notify_admins($error);
}
```

### 2. Procedimientos de Recuperación
```php
public static function recover_from_incident($incident_type) {
    switch ($incident_type) {
        case 'cache_compromise':
            self::clear_all_cache();
            self::rebuild_cache();
            break;
        case 'data_corruption':
            self::restore_from_backup();
            self::validate_data_integrity();
            break;
    }
}
```

## Recomendaciones de Implementación

1. **Actualizaciones de Seguridad**
   - Mantener Moodle actualizado
   - Revisar regularmente las dependencias
   - Aplicar parches de seguridad

2. **Configuración Segura**
   - Usar HTTPS
   - Configurar headers de seguridad
   - Implementar políticas de contraseñas fuertes

3. **Monitoreo Continuo**
   - Revisar logs regularmente
   - Configurar alertas automáticas
   - Realizar auditorías periódicas

## Checklist de Seguridad

- [ ] Validación de entrada en todos los formularios
- [ ] Sanitización de salida en todas las vistas
- [ ] Control de acceso basado en roles
- [ ] Protección contra CSRF
- [ ] Logging de eventos críticos
- [ ] Manejo seguro de sesiones
- [ ] Prevención de SQL Injection
- [ ] Backup regular de datos

## Contacto de Seguridad

Para reportar vulnerabilidades de seguridad:
- Email: security@ejemplo.com
- Teléfono de emergencia: +XX XXX XXX XXX
- Sistema de tickets: [URL]

---

**Nota**: Este documento debe ser revisado y actualizado regularmente para mantener su efectividad y relevancia. 