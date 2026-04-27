/* 1. Si la columna no existe, la añadimos */
ALTER TABLE ubicaciones ADD COLUMN IF NOT EXISTS almacen_id INT AFTER id;

/* 2. Aseguramos que sea una clave foránea correcta */
ALTER TABLE ubicaciones ADD CONSTRAINT fk_ubicacion_almacen 
FOREIGN KEY (almacen_id) REFERENCES almacenes(id) 
ON DELETE CASCADE ON UPDATE CASCADE;
