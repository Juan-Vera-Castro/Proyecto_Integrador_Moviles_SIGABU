<?php

namespace App\Models\Inclusion;

/**
 * Estados con los que un formato de Inclusión aparece en el expediente (sección 3 de la spec).
 * No se guardan: cada documento los calcula a partir de sus registros.
 */
final class EstadoDocumento
{
    public const BORRADOR = 'Borrador';

    public const COMPLETADO = 'Completado';

    public const FIRMADO = 'Firmado';

    public const REVOCADO = 'Revocado';
}
