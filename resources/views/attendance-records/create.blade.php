@extends('layouts.app')

@section('title', 'Record Attendance')
@section('heading', 'Record Attendance')

@section('content')
    @include('attendance-records._form')
@endsection
