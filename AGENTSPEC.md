# Especificación de Desarrollo: Sorteo y Cuestionario para Eventos

## 1. Resumen y Objetivo
Desarrollar una aplicación web en **PHP >= 8.2** y **MySQL** para gestionar el registro de usuarios en eventos mediante email y nombre, persistir su sesión durante 7 días mediante cookies (sin contraseñas), mostrar preguntas dinámicas con opciones múltiples (A, B, C, D o numéricas) según la configuración del evento, y permitir al administrador realizar un sorteo filtrado al finalizar.

---

## 2. Configuración del Entorno (.env)
La configuración general de la aplicación y de los eventos activos debe residir en un archivo `.env` o en un archivo de configuración dedicado (ej. `config.php`). 

Ejemplo de estructura de configuración:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eventos_sorteo
DB_USER=root
DB_PASS=secret

# Configuración del Evento Actual
EVENT_SLUG=devfest-2026
EVENT_NAME="DevFest Ponferrada 2026"

# Definición de preguntas y respuestas válidas (puede estructurarse en JSON o array PHP)
# Formato: Pregunta | Opciones | Respuesta Correcta
EVENT_QUESTIONS='[
  {
    "id": 1,
    "pregunta": "¿Qué framework de PHP se utiliza habitualmente con FilamentPHP?",
    "opciones": {"A": "Symfony", "B": "Laravel", "C": "CodeIgniter", "D": "CakePHP"},
    "correcta": "B"
  },
  {
    "id": 2,
    "pregunta": "¿En qué año se fundó el evento actual (aproximadamente)?",
    "opciones": {"A": "2020", "B": "2024", "C": "2026", "D": "2018"},
    "correcta": "C"
  }
]'

## 3. Esquema de Base de Datos (MySQL)
Para permitir que un mismo email participe en varios eventos y asociar cada registro unívocamente al evento, se utilizará una relación de N:M o una tabla de registros vinculada por el identificador del evento (event_slug).

SQL
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_slug VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_email (event_slug, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_slug VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    question_id INT NOT NULL,
    selected_answer VARCHAR(50) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_question_per_event (event_slug, email, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
## 4. Requerimientos Funcionales y Lógica
### A. Registro y Autenticación por Cookie (7 días)
Formulario Inicial: Solicita name y email.

Validación y Persistencia: Al enviar el formulario, se comprueba si el usuario ya existe para el event_slug actual en event_registrations. Si no existe, se inserta.

Cookie de Sesión: Se establece una cookie encriptada o firmada (por ejemplo, event_user_session) que almacena el email y el evento, con una duración de time() + (7 * 24 * 60 * 60) (7 días). No se usan contraseñas.

### B. Dinámica de Preguntas
Leer las preguntas desde la configuración del evento (.env o archivo de configuración).

Mostrar las preguntas de forma progresiva o en lista con opciones tasadas (A, B, C, D o numéricas).

Al enviar la respuesta de una pregunta, se valida contra la respuesta correcta configurada, guardando el resultado (is_correct = true/false) en la tabla user_answers.

### C. Panel de Sorteo (Filtros de Ganadores)
Al finalizar el evento, el administrador podrá seleccionar entre tres modalidades de sorteo:

Todos los que respondieron correctamente a todas las preguntas: Selecciona aquellos cuyo número de aciertos coincide con el total de preguntas del evento.

Los que NO respondieron correctamente a todas las preguntas: Selecciona a los participantes que tuvieron fallos o no completaron todo.

Todos los registrados: Sorteo general entre todos los inscritos en el evento sin importar sus respuestas.
El script debe seleccionar un ganador aleatorio mediante SQL (ORDER BY RAND() LIMIT 1) o lógica en PHP sobre el conjunto filtrado.

## 5. Criterios de Aceptación (Definición de Hecho)
[ ] El código utiliza características de PHP >= 8.2 (tipado estricto declare(strict_types=1);, constructores promovidos, etc.).

[ ] Las consultas a base de datos utilizan PDO con sentencias preparadas (Prepared Statements) para evitar Inyección SQL.

[ ] La cookie expira exactamente a los 7 días y persiste la identidad del usuario sin solicitar contraseña.

[ ] Un mismo email puede registrarse y participar de forma independiente en múltiples eventos (event_slug).

[ ] El script de sorteo filtra correctamente según las 3 opciones requeridas.

## 6. Estructura de Archivos Sugerida
index.php -> Punto de entrada, gestión de cookies y enrutamiento básico.

config.php -> Carga de variables de entorno y definición del evento/preguntas.

db.php -> Conexión PDO centralizada.

register.php -> Lógica de inserción en base de datos y emisión de cookie.

quiz.php -> Renderizado y validación de respuestas.

sorteo.php -> Panel de selección de ganadores según los filtros requeridos.