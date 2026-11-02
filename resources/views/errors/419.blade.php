@extends('errors.layout')

@section('code', 'Sessie verlopen')
@section('title', 'Log opnieuw in')
@section('message', 'De sessie is verlopen omdat de pagina te lang openstond. Wat net is ingevoerd, is niet opgeslagen. Log opnieuw in en probeer het nog een keer.')
@section('href', route('login'))
@section('action', 'Opnieuw inloggen')
