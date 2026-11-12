-- SIGABU en PostgreSQL propio · Paso 5: verificación.
-- Ejecutarlo en Supabase (antes de apagarla) y en el servidor nuevo, y comparar las salidas:
-- deben coincidir fila por fila.
--
--   psql "<conexión>" -At -f 05_verificar.sql > conteos_<origen>.txt
--   diff conteos_supabase.txt conteos_nuevo.txt

-- 1. Filas por tabla (conteo exacto, no estimado).
SELECT 'filas ' || t.table_name || ' = ' ||
       (xpath('/row/n/text()', query_to_xml(format('SELECT count(*) AS n FROM public.%I', t.table_name), false, true, '')))[1]::text
  FROM information_schema.tables t
 WHERE t.table_schema = 'public' AND t.table_type = 'BASE TABLE'
 ORDER BY t.table_name;

-- 2. Expedientes: el último consecutivo por prefijo debe ser el mismo.
SELECT 'ultimo_expediente ' || left(codigo_expediente, 8) || ' = ' || max(codigo_expediente)
  FROM public.casos
 GROUP BY left(codigo_expediente, 8)
 ORDER BY 1;

-- 3. Cada solicitud tiene exactamente un caso (el trigger no debe duplicarlos al cargar).
SELECT 'solicitudes_sin_caso = ' || count(*)
  FROM public.solicitudes_atencion s
 WHERE NOT EXISTS (SELECT 1 FROM public.casos c WHERE c.id_solicitud_origen = s.id_solicitud);

-- 4. Migraciones registradas (Laravel no debe volver a ejecutarlas).
SELECT 'migraciones = ' || count(*) || ' · última = ' || max(migration) FROM public.migrations;
