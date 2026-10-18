/*
  ARCHIVO: public/recursos/js/configuracion.example.js
  FASE: 6

  QUÉ ES:
    La plantilla de la configuración de la página. Se versiona (se sube a Git)
    porque no tiene la clave real.

  QUÉ DEBES HACER:
    Copia este archivo con el nombre "configuracion.js" (en esta misma carpeta)
    y pon la MISMA clave que escribiste en "clave_api" de config.php.
    "configuracion.js" está en .gitignore: no se sube a Git.

  NOTA DE SEGURIDAD (para explicar en el video):
    La página corre en el navegador, así que esta clave se puede ver en el
    código de la página. Para esta prueba es lo que pide el enunciado (la
    interfaz consume la API con X-API-Key). En un sistema real, la clave no
    iría en el navegador: se usaría un inicio de sesión del usuario.
*/
const CONFIGURACION_API = { // Datos que usa la página para hablar con la API
  urlBase: 'api', // Dirección de la API, relativa a la página: funciona igual en XAMPP y en Docker
  claveApi: 'PON-AQUI-LA-MISMA-CLAVE-DE-config.php', // Debe ser igual a "clave_api" de config.php
};
