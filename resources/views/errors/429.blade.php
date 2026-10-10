@extends('errors.layout')

@section('codigo', '429')
@section('emoji', '🥵')
@section('titulo', '¡Pará un poco!')
@section('mensaje', 'Hiciste demasiados intentos seguidos. Esperá un minuto, tomá aire y probá de nuevo.')

@section('boton')
  <a class="btn" href="" data-recargar>Volver a intentar</a>
@endsection
