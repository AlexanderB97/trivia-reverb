@extends('errors.layout')

@section('codigo', '500')
@section('emoji', '💥')
@section('titulo', '¡Esta la erramos nosotros!')
@section('mensaje', 'Algo salió mal de nuestro lado, no fue culpa tuya. Probá entrar de nuevo en un ratito.')

@section('boton')
  <a class="btn" href="/jugar">Ir a jugar</a>
@endsection
