@extends('errors.layout')

@section('codigo', '404')
@section('emoji', '🤔')
@section('titulo', '¡Pregunta equivocada!')
@section('mensaje', 'Esta página no existe: ninguna de las cuatro opciones era la correcta. Volvé al juego, que ahí sí se suman puntos.')

@section('boton')
  <a class="btn" href="/jugar">Ir a jugar</a>
@endsection
