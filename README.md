# Aplicación de Gestión de Reservas — Prueba Técnica

*   **Enlace de la aplicación en producción**: [URL disponible tras el deploy]

## Introducción
Este proyecto consiste en una pequeña aplicación para la gestión, filtrado y cancelación asíncrona de reservas de alojamiento. El sistema se ha construido en PHP nativo sin utilizar ningún tipo de framework.

## Especificaciones y tecnologías utilizadas
*   **Backend**: PHP 8.x con arquitectura orientada a objetos y autocarga de clases bajo estándar PSR-4.
*   **Motor de plantillas**: Twig v3.x utilizando herencia de bloques y políticas de escape automático.
*   **Base de datos**: MySQL 8.0 / MariaDB relacional.
*   **Frontend**: HTML, estilos CSS nativos con diseño responsive adaptado a dispositivos móviles y JavaScript Vanilla.
*   **Gestor de dependencias**: Composer.

## Guía de instalación local

### Requisitos previos
*   Servidor local con PHP 8.x (WAMP, MAMP o XAMPP).
*   Motor de base de datos MySQL o MariaDB.
*   **Composer** instalado en el equipo.

### Paso 1: Descargar el proyecto e instalar dependencias
Mueva la carpeta raíz del proyecto al directorio de su servidor local y ejecute en su terminal para descargar el motor de plantillas Twig y el entorno de pruebas: `composer install`.

### Paso 2: Importar la base de datos
Importe el archivo `schema.sql`, ubicado en la raíz del proyecto, en su gestor de bases de datos. El script cuenta con la cláusula condicional `CREATE DATABASE IF NOT EXISTS reservas_db` por lo que, si no existe, creará la base de datos `reservas_db` de forma automática, estructurará las tablas y las poblará con datos de prueba realistas.

### Paso 3: Configurar el entorno y arrancar
Si las credenciales de su MySQL local difieren de los valores estándar (Host: `localhost`, Usuario: `root`, sin contraseña, Puerto: `3306`), defina las variables de entorno correspondientes en la configuración de su servidor web Apache: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`. Tras esto, encienda su servidor local y acceda a la URL del proyecto a través del navegador.

---

## Decisiones de diseño y seguridad

### Estructura del código
*   **Arquitectura orientada a objetos**: El proyecto se estructuró siguiendo el principio de responsabilidad única, separando las tareas de control de las tareas de persistencia. La clase `ReservaRepository` concentra las consultas SQL aislando la base de datos, `ReservaController` procesa las peticiones y valida, e `index.php` actúa como Front Controller para el enrutamiento.
*   **Elección de la base de datos**: Se seleccionó MySQL por ser una base de datos relacional robusta y estándar en el sector. Además, permite el uso de transacciones atómicas (`beginTransaction`/`commit`), asegurando que nunca se cree una reserva o cambio de estado sin guardar su respectivo registro de evento.
*   **Manejo de errores centralizado**: Se creó `src/Config/ErrorHandler.php` conectado a `set_exception_handler`. Los errores controlados de negocio (400, 404) se gestionan con `if` en el controlador, mientras que los fallos críticos de infraestructura (500) se elevan con `throw $e` para que el manejador global los registre en el log del servidor de forma limpia.
*   **Uso de POST para cambios de estado**: Se eligió usar `POST` en lugar de `PUT` para simplificar el procesado de datos en PHP nativo, y porque la confirmación/cancelación de una reserva genera un efecto crítico en el negocio (un registro en el historial), comportándose semánticamente como una creación.

### Consideraciones de seguridad
*   **Inyecciones SQL**: Desactivación de consultas preparadas emuladas en PDO (`PDO::ATTR_EMULATE_PREPARES => false`).
*   **XSS**: Uso del escape automático de Twig sin recurrir al filtro `|raw` en textos planos, y saneamiento de salida en JavaScript.
*   **Validación**: Comprobación estricta de campos y coherencia temporal en el servidor.

## Posibles mejoras con más tiempo

*   **Vista independiente para crear reservas**: Actualmente, el formulario de inserción se encuentra en la misma pantalla del listado general para agilizar la actualización asíncrona de la tabla en una interfaz compacta. Con más tiempo, aislaría este formulario en una vista independiente de Twig (`crear.html.twig`) controlada bajo su propia ruta (`index.php?action=nueva`) para la usabilidad en dispositivos móviles.
*   **Enrutamiento semántico**: Reemplazaría los parámetros tradicionales de la URL (`index.php?api=reservas&id=X`) por un sistema basado en expresiones regulares para dar soporte a URLs amigables y limpias (por ejemplo: `POST /api/reservas/1/cancelar`).

---

## Escalabilidad

Si la aplicación tuviera que soportar tráfico masivo, implementaría los siguientes cambios estructurales:

1.  **Refactorización hacia un Modelo Reserva en POO**: Actualmente la capa de datos transfiere arrays asociativos crudos mapeados desde las consultas PDO. Para soportar un volumen alto de negocio, transformaría el sistema hacia una entidad o **Modelo de Objeto Reserva** en programación orientada a objetos pura:
    ```php
    namespace App\Model;
    use DateTime;

    class Reserva {
        private ?int $id;
        private string $nombreHuesped;
        private string $emailHuesped;
        private DateTime $fechaEntrada;
        private DateTime $fechaSalida;
        private string $estado;
        private float $importe;

        public function __construct(array $datos) {
            $this->id = $datos['id'] ?? null;
            $this->nombreHuesped = $datos['nombre_huesped'];
            $this->emailHuesped = $datos['email_huesped'];
            $this->fechaEntrada = new DateTime($datos['fecha_entrada']);
            $this->fechaSalida = new DateTime($datos['fecha_salida']);
            $this->estado = $datos['estado'] ?? 'pendiente';
            $this->importe = (float)$datos['importe'];
        }

        public function esValida(): bool {
            return $this->fechaEntrada < $this->fechaSalida && filter_var($this->emailHuesped, FILTER_VALIDATE_EMAIL);
        }

        public function confirmar(): void {
            $this->estado = 'confirmada';
        }
    }
    ```
    Esto aislaría las reglas de control dentro de la propia entidad y facilitaría las pruebas unitarias automatizadas con PHPUnit, sin necesidad de simular llamadas de red.
2.  **Optimización e indexación de base de datos**: Para que el filtrado no colapse las lecturas al realizar barridos completos de tablas, el archivo `schema.sql` presentado ya incluye un **Índice Compuesto** en (`fecha_entrada`, `fecha_salida`) y un índice regular en `estado`, lo que acelera las consultas simultáneas en la API.
3.  **Capa de caché intermedia**: Implementaría Redis en memoria para almacenar los resultados del listado general y los detalles de las reservas (con alta tasa de lectura y baja modificación), sirviendo las peticiones recurrentes de forma instantánea.
4.  **Colas asíncronas**: Separaría la lógica de grabación del historial. En lugar de bloquear la transacción HTTP del cliente escribiendo en las tablas `reserva` y `reserva_evento` en la misma petición, delegando el registro de los eventos a una cola de RabbitMQ en segundo plano que lo inserte de forma diferida, liberando la respuesta al usuario.

## Cobertura de tests

### 1. Pruebas unitarias (PHPUnit)
Para ejecutar los archivos de pruebas unitarias en una consola local, ejecute:
```bash
./vendor/bin/phpunit tests
```
*Resultado:* `OK (3 tests, 9 assertions)`. Valida de forma aislada mediante dobles de prueba (*Mocks*) que el controlador intercepte correos inválidos, bloquee fechas invertidas y responda con los códigos HTTP semánticos correctos (400 y 200).

### 2. Pruebas manuales en el navegador
Realizadas en las herramientas de desarrollo del navegador (F12) para comprobar el comportamiento dinámico y las alertas visuales de éxito/error del JavaScript:

*   **Test de fechas invertidas (HTTP 400):** Envía una fecha de salida anterior a la de entrada:
    ```javascript
    fetch('index.php?api=reservas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre_huesped: 'Test', email_huesped: 'u@email.com', nombre_alojamiento: 'Hotel', fecha_entrada: '2026-11-20', fecha_salida: '2026-11-10', importe: 120 })
    }).then(r => r.json()).then(data => console.log('Respuesta 400:', data));
    ```
    *Resultado:* El servidor rechaza la transacción y devuelve un `400 Bad Request`. El frontend interceptará el fallo de red limpiamente y pintará los errores en rojo dentro del contenedor `#errorSummary`.

*   **Test de confirmación asíncrona (HTTP 200):** Ejecute en la **Consola** el siguiente script para forzar el cambio de estado de la reserva número 1:
    ```javascript
    fetch('index.php?api=reservas&id=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ estado: 'confirmada' })
    }).then(r => r.json()).then(data => console.log('Respuesta:', data));
    ```
    *Resultado:* El servidor aplicará el `UPDATE` en la base de datos, el badge pasará a verde ("confirmada") y el historial de eventos se actualizará al instante.
