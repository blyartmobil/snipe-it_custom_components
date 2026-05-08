@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/components/general.checkin') }}
    @parent
@stop


@section('header_right')
    <a href="{{ URL::previous() }}" class="btn btn-primary pull-right">
        {{ trans('general.back') }}</a>
@stop

{{-- Page content --}}
@section('content')
    <div class="row">
        <!-- left column -->
        <div class="col-md-7">
            <form class="form-horizontal" method="post" action="{{ route('components.checkin.store', [$serial->id, 'backto' => 'asset']) }}" autocomplete="off">
                {{csrf_field()}}

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h2 class="box-title"> {{ $component->name }}</h2>
                    </div>
                    <div class="box-body">

                        <!-- Checked out to -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">{{ trans('general.checkin_from') }}</label>
                            <div class="col-md-6">
                                <p class="form-control-static">{{ $asset ? $asset->present()->fullName : trans('general.deleted') }}</p>
                            </div>
                        </div>

                        <!-- Serial Number (static display) -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">{{ trans('admin/hardware/form.serial') }}</label>
                            <div class="col-md-6">
                                <p class="form-control-static">{{ $serial->serial }}</p>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="form-group {{ $errors->has('note') ? 'error' : '' }}">
                            <label for="note" class="col-md-2 control-label">{{ trans('admin/hardware/form.notes') }}</label>
                            <div class="col-md-7">
                                <textarea class="col-md-6 form-control" id="note" name="note">{{ old('note', $component->note) }}</textarea>
                                {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                            </div>
                        </div>
                        <x-redirect_submit_options
                                index_route="components.index"
                                :button_label="trans('general.checkin')"
                                :options="[
                                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.components')]),
                                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.component')]),
                               ]"
                        />
                    </div> <!-- /.box-->
            </form>
        </div> <!-- /.col-md-7-->
    </div>


@stop
