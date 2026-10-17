<?php

return [ 
    'base_datos' => [ 
        'servidor'         => '127.0.0.1',       
        'puerto'           => 3306,              
        'nombre'           => 'soporte_tickets', 
        'usuario'          => 'root',             
        'clave'            => '',                 
        'juego_caracteres' => 'utf8mb4',         
    ],
    'clave_api' => 'cambia-esta-clave-por-una-larga-y-secreta', // Cámbiala por una clave tuya
    // ---- Zona horaria (las fechas del SLA dependen de esto) ----
    'zona_horaria' => 'America/Bogota', 
    // ---- Reglas del negocio (aquí para no escribirlas fijas en el código) ----
    'maximo_tickets_activos_por_tecnico' => 5,  
    'registros_por_pagina_por_defecto'   => 10, 
    'registros_por_pagina_maximo'        => 50, 
    // ---- Depuración ----
    // true  = guarda también la traza técnica del error en el log (solo para tu PC).
    // false = guarda solo el mensaje. En ningún caso se muestra al cliente.
    'depuracion' => false, // Déjalo en false salvo que estés buscando un error
];
