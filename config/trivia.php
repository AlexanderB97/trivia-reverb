<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PIN del host de la trivia
    |--------------------------------------------------------------------------
    |
    | Se pide al entrar a /host. Si está vacío, nadie puede entrar como host.
    |
    */

    'host_pin' => env('TRIVIA_HOST_PIN', ''),

    /*
    |--------------------------------------------------------------------------
    | URL que codifica el QR del host (adonde entran los celulares)
    |--------------------------------------------------------------------------
    |
    | Ejemplo: http://192.168.1.50:8000/jugar
    | Si está vacía se arma sola: con el origen de la petición si el host no se abrió
    | desde localhost, o si no con REVERB_HOST (la IP de la notebook) y el puerto actual.
    |
    */

    'url_jugar' => env('TRIVIA_URL_JUGAR', ''),

    /*
    |--------------------------------------------------------------------------
    | Temas de las preguntas (Programación IV - TUP UTN FRRe, sede Formosa)
    |--------------------------------------------------------------------------
    |
    | GeneradorPreguntas elige uno al azar para cada pregunta. Si la lista
    | queda vacía, la IA pregunta sobre Laravel y bases de datos en general.
    |
    */

    'temas' => [
        // Unidad 1: Fundamentos y setup (clases 1 a 5)
        'Configuración del entorno de desarrollo backend (PHP, Composer, XAMPP, MySQL)',
        'Gestión de dependencias y paquetes con Composer y npm',
        'APIs y arquitectura cliente-servidor',
        'Entrada/salida y manipulación de datos en PHP',
        'Estructuras de control y manejo de archivos en PHP',
        'Transformación de datos entre JSON, CSV y XML',
        'Validaciones básicas y manejo de errores',
        'Documentación técnica y buenas prácticas de desarrollo',

        // Unidad 2: CRUD y diseño de APIs (clases 5 a 8)
        'Principios de diseño de APIs RESTful',
        'Operaciones CRUD y verbos HTTP (GET, POST, PUT/PATCH, DELETE)',
        'Modelado de datos y relaciones entre entidades',
        'Validaciones de datos y reglas de negocio en Laravel',
        'ORM y Eloquent (modelos, consultas, relaciones)',
        'Migraciones y esquema de base de datos en Laravel',
        'Patrón Repository y capa de acceso a datos',
        'Serialización y deserialización (API Resources, JSON)',
        'Códigos de estado HTTP (200, 201, 204, 400, 401, 403, 404, 422, 429, 500)',
        'Paginación, filtrado y ordenamiento de resultados',

        // Unidad 3: Arquitectura y seguridad (clases 11 a 19)
        'Separación de responsabilidades (controladores, servicios, repositorios)',
        'Integración con múltiples fuentes de datos',
        'Manejo centralizado de errores y excepciones',
        'Autenticación y autorización',
        'Tokens de acceso y refresh (JWT)',
        'Sesiones y estado de usuario',
        'Roles y permisos',
        'Autenticación con proveedores externos (OAuth, OpenID)',
        'Middleware de seguridad y protección de rutas',
        'OWASP Top 10 en APIs',
        'Rate limiting y prevención de ataques',
        'Políticas CORS',
        'Validación y sanitización de entrada de datos',
        'Logging y auditoría de seguridad',
        'Manejo seguro de credenciales y variables de entorno (.env)',

        // Unidad 4: Tiempo real y WebSockets (clases 19 a 23)
        'HTTP tradicional vs WebSockets',
        'Arquitecturas orientadas a eventos',
        'Protocolo WebSocket (handshake, conexión persistente, full-duplex)',
        'Canales públicos, privados y de presencia',
        'Laravel Reverb y Laravel Echo',
        'Broadcast de eventos en Laravel (ShouldBroadcast, broadcastOn, broadcastAs)',
        'Colas y QUEUE_CONNECTION en el broadcasting',
        'Gestión de conexiones concurrentes',
        'Sincronización de datos en tiempo real',
        'Reconexión automática y estado de conexión',
        'Sistemas de presencia y estado de usuarios',

        // ---- Todavía no dados: descomentar cuando se vean ----
        // 'Notificaciones push y eventos de negocio',
        // 'Escalabilidad en aplicaciones de tiempo real',
        // 'GraphQL vs REST',
        // 'Schemas, queries y mutations en GraphQL',
        // 'Resolvers en GraphQL',
        // 'Integración continua (CI) y despliegue continuo (CD)',
        // 'Entornos de desarrollo, staging y producción',
        // 'Despliegue en plataformas cloud',
        // 'Versionado y rollback',
        // 'Monitoreo y logging en producción',
        // 'Testing automatizado (unitario, integración, end-to-end)',
    ],

];
