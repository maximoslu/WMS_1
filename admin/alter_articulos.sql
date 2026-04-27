/* 1. Añadimos la columna estado con valor por defecto */
ALTER TABLE articulos 
ADD COLUMN estado ENUM('DISPONIBLE', 'BLOQUEADO', 'OBSOLETO') NOT NULL DEFAULT 'DISPONIBLE' AFTER descripcion;

/* 2. (Opcional) Eliminamos la columna antigua ean_upc si ya no es necesaria */
ALTER TABLE articulos DROP COLUMN ean_upc;
