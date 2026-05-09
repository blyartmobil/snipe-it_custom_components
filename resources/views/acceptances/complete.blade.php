@extends('layouts/basic')

{{-- Page title --}}
@section('title')
    {{ trans('admin/users/message.accepted') }}
    @parent
@stop

@section('content')

    <style>
        .acceptance-box {
            max-width: 800px;
            margin: 30px auto;
        }

        .acceptance-box .box {
            border-top: 3px solid #d2d6de;
        }

        .acceptance-box .box-header {
            background-color: #f9f9f9;
        }
    </style>

    <div class="container">
        <div class="acceptance-box">
            <div class="panel box box-default">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">
                        <i class="fa fa-check-circle text-success" aria-hidden="true" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                        {{ session('success') }}
                    </h2>
                </div>
                <div class="box-body text-center" style="padding: 30px;">
                    <p>{{ trans('admin/users/message.accepted') }}</p>
                    <p class="text-muted">{{ trans('general.you_may_close_this_page') }}</p>
                </div>
            </div>
        </div>
    </div>

@stop