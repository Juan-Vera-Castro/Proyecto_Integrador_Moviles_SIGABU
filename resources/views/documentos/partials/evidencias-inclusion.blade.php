{{-- Evidencias en un PDF: los archivos por nombre y los links por su dirección; nada se incrusta. Variable: $evidencias. --}}
@foreach ($evidencias as $evidencia)
    <div>{{ $evidencia->esArchivo() ? $evidencia->nombre.' (adjunto en SIGABU)' : $evidencia->url }}</div>
@endforeach
