{{-- Contenido de un recurso en PDF: los mismos bloques de datos de la pantalla. Variable: $bloques. --}}
@foreach ($bloques as $bloque)
    @switch($bloque['tipo'])
        @case('titulo')
            <h2>{{ $bloque['texto'] }}</h2>
            @break

        @case('subtitulo')
            <h3>{{ $bloque['texto'] }}</h3>
            @break

        @case('parrafo')
            <p>{{ $bloque['texto'] }}</p>
            @break

        @case('lista')
            <{{ empty($bloque['ordenada']) ? 'ul' : 'ol' }} style="margin: 4px 0; padding-left: 18px">
                @foreach ($bloque['items'] as $item)
                    <li style="margin: 2px 0">{{ $item }}</li>
                @endforeach
            </{{ empty($bloque['ordenada']) ? 'ul' : 'ol' }}>
            @break

        @case('destacado')
            <div class="aviso" style="font-weight: normal">
                <strong>{{ $bloque['titulo'] }}</strong><br>{{ $bloque['texto'] }}
            </div>
            @break

        @case('tabla')
            <table class="bloque">
                <thead>
                    <tr>
                        @foreach ($bloque['encabezados'] as $encabezado)
                            <th>{{ $encabezado }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bloque['filas'] as $fila)
                        <tr class="fila">
                            @foreach ($fila as $celda)
                                <td>{{ $celda }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @break
    @endswitch
@endforeach
