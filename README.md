# Sistema de tickets de soporte TI

Aplicación web SPA para registrar, asignar y hacer seguimiento a tickets de soporte. El backend es una API REST en PHP 8 sin framework, con MySQL/MariaDB y un frontend en JavaScript puro con `fetch`.

## Estado del proyecto

Fases 1 a 6 implementadas: estructura, base de datos, API REST, reglas de negocio e interfaz web SPA. La colección de Postman incluida permite validar los endpoints y los casos de error solicitados en la prueba técnica.

## Funcionalidades

- Registro de tickets con usuario, categoría, prioridad y técnico.
- Consecutivo seguro por año con formato `TK-AAAA-NNNNNN`.
- Flujo `ABIERTO → EN CURSO → CERRADO`.
- Reasignación de técnicos con límite de 5 tickets activos.
- SLA de 4, 24 y 72 horas para prioridades Alta, Media y Baja.
- Historial de cambios de estado y de técnico.
- Tablero resumen, filtros, búsqueda, paginación y detalle de tickets.
- Respuestas JSON uniformes y validación en servidor.

## Requisitos

- XAMPP con Apache, PHP 8.x y MySQL/MariaDB.
- Navegador moderno.
- Conexión a Internet para cargar Bootstrap desde CDN.

## Instalación

1. Copia el proyecto dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL desde XAMPP.
3. Ejecuta en MySQL, en este orden:

   ```text
   database/BD.sql
   database/datos_prueba.sql
   ```

4. Copia `config.example.php` como `config.php` y configura la conexión y `clave_api`.
5. Copia `public/recursos/js/configuracion.example.js` como `public/recursos/js/configuracion.js` y usa la misma clave en `claveApi`.
6. Abre `http://localhost:8080/PruebaTecnica/public/`.

> **Nota sobre el puerto de Apache:** en la configuración actual de XAMPP, Apache está definido con `Listen 8080` y `ServerName localhost:8080`. Por eso debes incluir `:8080` en la dirección; `http://localhost/PruebaTecnica/public/` intenta usar el puerto 80 y puede mostrar un error o abrir otro servicio.

## API REST

Todas las peticiones requieren el encabezado:

```http
X-API-Key: la-misma-clave-de-config.php
```

Base de la API para Postman (no es la pantalla de la aplicación):

```text
http://localhost:8080/PruebaTecnica/public/api/
```

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/usuarios/{codigo}` | Consulta los datos y estado de un usuario. |
| GET | `/tecnicos` | Lista técnicos activos y carga actual. |
| GET | `/categorias` | Lista categorías y prioridades con SLA. |
| GET | `/resumen` | Devuelve totales, vencidos y carga por técnico. |
| GET | `/tickets` | Lista paginada. Filtros: `estado`, `prioridad`, `tecnico`, `q`, `page`, `per_page`. |
| GET | `/tickets/{numero}` | Devuelve detalle e historial. |
| POST | `/tickets` | Crea un ticket. Responde `201`. |
| PATCH | `/tickets/{numero}/estado` | Cambia el estado respetando el flujo. |
| PATCH | `/tickets/{numero}/tecnico` | Reasigna el técnico. |

Ejemplo de creación:

```json
{
  "codigo_usuario": "USR001",
  "categoria_id": 3,
  "descripcion": "La impresora del segundo piso no imprime nada",
  "prioridad": "Alta",
  "tecnico_id": 3
}
```

Para cerrar un ticket se debe enviar también `solucion`. Los errores tienen este formato:

```json
{
  "error": {
    "code": "VALIDACION_FALLIDA",
    "message": "Los datos no son válidos",
    "fields": {}
  }
}
```

Estados utilizados: `200`, `201`, `401`, `404`, `405`, `409`, `422` y `500`.

## Postman

Importa `Soporte-Tickets.postman_collection.json` en Postman y configura las variables `baseUrl` y `apiKey`. La colección incluye consultas, creación de tickets, cambios de estado, reasignación y casos de error.

Para repetir las pruebas que modifican datos, vuelve a ejecutar `database/BD.sql` y `database/datos_prueba.sql`.

## Estructura

```text
public/            Interfaz SPA, recursos estáticos y entrada pública de la API
src/Nucleo/        Configuración, conexión, petición, respuesta y enrutador
src/Seguridad/     Verificación de la API key
src/Controladores/ Entrada de los endpoints
src/Servicios/     Reglas de negocio, estados, carga y SLA
src/Validadores/   Validación de datos y filtros
src/Repositorios/  Consultas SQL con PDO y sentencias preparadas
database/          Esquema y datos de prueba
```

## Seguridad y decisiones técnicas

- Las credenciales reales quedan fuera del repositorio mediante `.gitignore`.
- La API key se valida con `hash_equals`.
- El acceso a datos usa PDO y sentencias preparadas.
- Los errores inesperados se registran en el servidor sin exponer trazas al cliente.
- El frontend usa `textContent` y creación de nodos para evitar insertar datos externos como HTML.
- El consecutivo, la creación, el cambio de estado y la reasignación se protegen con transacciones y bloqueos.

## Uso de IA

El proyecto se desarrolló con apoyo de Claude (Anthropic) para generar parte de la estructura inicial, scripts SQL y documentación. El código fue revisado y debe poder explicarse y modificarse durante la sustentación.
