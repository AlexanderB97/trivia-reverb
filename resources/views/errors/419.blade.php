@extends('errors.layout')

@section('codigo', '419')
@section('emoji', '⏰')
@section('titulo', '¡Se acabó el tiempo!')
@section('mensaje', 'La página estuvo abierta mucho rato y la sesión venció. Recargá y seguí jugando: no perdés tu nombre ni tus puntos.')

@section('boton')
  <a class="btn" href="" data-recargar>Recargar</a>
@endsection
