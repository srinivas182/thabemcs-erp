@extends('website.layout')

@section('content')
    @foreach ($blocks as $block)
        @includeIf('website.blocks.'.$block['type'], ['data' => $block['data']])
    @endforeach
@endsection
