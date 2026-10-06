@extends('errors.brand')

@section('code', '403')
@section('message', $exception->getMessage() ?: 'You do not have access to this page.')
