@extends('layouts/default')

{{-- Page title --}}
@section('title')
 {{ trans('admin/components/general.checkout') }}
@parent
@stop

{{-- Page content --}}
@section('content')

<div class="row">
  <div class="col-md-8">
    <form class="form-horizontal" id="checkout_form" method="post" action="" autocomplete="off">
      <!-- CSRF Token -->
      {{ csrf_field() }}

      <div class="box box-default">
        @if ($component->id)
        <div class="box-header with-border">
          <div class="box-heading">
            <h2 class="box-title">{{ $component->name }}  ({{ $component->numRemaining()  }}  {{ trans('admin/components/general.remaining') }})</h2>
          </div>
        </div><!-- /.box-header -->
        @endif

        <div class="box-body">

            @if ($component->company)
                <!-- accessory name -->
                <div class="form-group">
                    <label class="col-sm-3 control-label">{{ trans('general.company') }}</label>
                    <div class="col-md-6">
                        <p class="form-control-static">{!! $component->company->present()->formattedNameLink  !!}</p>
                    </div>
                </div>
            @endif


            @if ($component->category)
                <!-- accessory name -->
                <div class="form-group">
                    <label class="col-sm-3 control-label">{{ trans('general.category') }}</label>
                    <div class="col-md-6">
                        <p class="form-control-static">{!! $component->category->present()->formattedNameLink  !!}</p>
                    </div>
                </div>
            @endif

          <!-- Asset -->
            @include ('partials.forms.edit.asset-select', ['translated_name' => trans('general.select_asset'), 'fieldname' => 'asset_id', 'company_id' => $component->company_id, 'required' => 'true', 'value' => old('asset_id')])

            <!-- Available Serials -->
            <div class="form-group {{ $errors->has('serial_ids') ? ' has-error' : '' }}">
              <label for="serial_ids" class="col-md-3 control-label">
                {{ trans('admin/hardware/form.serial') }}
              </label>
              <div class="col-md-8">
                @php $availableSerials = $component->availableSerials; @endphp
                @if ($availableSerials->isEmpty())
                  <div class="callout callout-warning">
                    <p>{{ trans('admin/components/general.no_available_serials') }}</p>
                  </div>
                @else
                  <table class="table table-striped table-condensed">
                    <thead>
                      <tr>
                        <th><input type="checkbox" id="select_all_serials"></th>
                        <th>{{ trans('admin/hardware/form.serial') }}</th>
                        <th>{{ trans('general.notes') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($availableSerials as $serial)
                        <tr>
                          <td><input type="checkbox" name="serial_ids[]" value="{{ $serial->id }}" class="serial-checkbox"></td>
                          <td>{{ $serial->serial }}</td>
                          <td>{{ $serial->notes }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @endif
              </div>
              @if ($errors->first('serial_ids'))
                <div class="col-md-9 col-md-offset-3">
                  {!! $errors->first('serial_ids', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
                </div>
              @endif
            </div>
            @if ($component->requireAcceptance() || $component->getEula() || ($snipeSettings->webhook_endpoint!=''))
              <div class="form-group notification-callout">
                <div class="col-md-8 col-md-offset-3">
                  <div class="callout callout-info">

                    @if ($component->category->require_acceptance=='1')
                      <i class="far fa-envelope"></i>
                      {{ trans('admin/categories/general.required_acceptance') }}
                      <br>
                    @endif

                    @if ($component->getEula())
                      <i class="far fa-envelope"></i>
                      {{ trans('admin/categories/general.required_eula') }}
                      <br>
                    @endif

                    @if ($snipeSettings->webhook_endpoint!='')
                      <i class="fab fa-slack"></i>
                      {{ trans('general.webhook_msg_note') }}
                    @endif
                  </div>
                </div>
              </div>
            @endif

            <!-- Note -->
            <div class="form-group{{ $errors->has('note') ? ' error' : '' }}">
              <label for="note" class="col-md-3 control-label">{{ trans('admin/hardware/form.notes') }}</label>
              <div class="col-md-7">
                <textarea class="col-md-6 form-control" id="note" name="note">{{ old('note', $component->note) }}</textarea>
                {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!}
              </div>
            </div>


        </div> <!-- .BOX-BODY-->
          <x-redirect_submit_options
                  index_route="components.index"
                  :button_label="trans('general.checkout')"
                  :options="[
                                'index' => trans('admin/hardware/form.redirect_to_all', ['type' => trans('general.components')]),
                                'item' => trans('admin/hardware/form.redirect_to_type', ['type' => trans('general.component')]),
                                'target' => trans('admin/hardware/form.redirect_to_checked_out_to'),

                               ]"
          />
      </div> <!-- .box-default-->
    </form>
  </div> <!-- .col-md-9-->
</div> <!-- .row -->

@stop
