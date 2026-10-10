@extends('errors.layout')

@section('codigo', '403')
@section('emoji', '🔒')
@section('titulo', '¡Zona del host!')
@section('mensaje', 'Esta pantalla es solo para quien conduce la trivia. Si venís a jugar, es por el otro lado.')

@section('boton')
  <a class="btn" href="/jugar">Ir a jugar</a>
  <a class="link" href="/host">Soy el host</a>
@endsection
