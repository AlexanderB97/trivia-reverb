@extends('errors.layout')

@section('codigo', '503')
@section('emoji', '🛠️')
@section('titulo', '¡Entretiempo!')
@section('mensaje', 'Estamos actualizando la trivia, volvé en unos segundos. Esta página se recarga sola.')

{{-- Se recarga sola cada 15 segundos: cuando termina la actualización, entra directo --}}
@section('head')
  <meta http-equiv="refresh" content="15">
@endsection

@section('extra')
  <div class="puntos" role="img" aria-label="Actualizando"><i></i><i></i><i></i></div>
@endsection

@section('boton')
  <a class="btn" href="" data-recargar>Probar ahora</a>
@endsection
