@extends('layouts/basic')

{{-- Page title --}}
@section('title')
    {{ trans('general.accept', ['asset' => $acceptance->checkoutable->display_name]) }}
    @parent
@stop

@section('content')

    <link rel="stylesheet" href="{{ url('css/signature-pad.min.css') }}">

    <style>
        .form-horizontal .control-label, .form-horizontal .radio, .form-horizontal .checkbox, .form-horizontal .radio-inline, .form-horizontal .checkbox-inline {
            padding-top: 17px;
            padding-right: 10px;
        }

        .m-signature-pad--body {
            border-style: dashed;
            border-color: grey;
            border-width: thick;
            padding-top: 0px;
        }

        .m-signature-pad {
            box-shadow: none;
            background-color: inherit;
            border: none;
        }

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

        .declined-warning {
            display: none;
            color: #a94442;
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>

    <div class="container">
        <div class="acceptance-box">
            <div class="panel box box-default">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">
                        {{ $acceptance->checkoutable->display_name }}
                    </h2>
                    @if ($acceptance->checkoutable->asset_tag)
                        <br><small class="text-muted">{{ trans('general.asset_tag') }}: {{ $acceptance->checkoutable->asset_tag }}</small>
                    @endif
                    @if ($acceptance->checkoutable->model)
                        <br><small class="text-muted">{{ trans('general.asset_model') }}: {{ $acceptance->checkoutable->model->name }}</small>
                    @endif
                    @if ($acceptance->checkoutable->serial)
                        <br><small class="text-muted">{{ trans('general.serial_number') }}: {{ $acceptance->checkoutable->serial }}</small>
                    @endif
                </div>

                <form class="form-horizontal" method="post" action="{{ route('direct.acceptance.store', $acceptance->validation_token) }}" autocomplete="off">
                    @csrf

                    <div class="box-body">

                        {{-- EULA --}}
                        @if ($acceptance->checkoutable->getEula())
                            <div class="col-md-12" style="padding-top: 5px; padding-bottom: 15px;">
                                <h4>{{ trans('general.eula') }}</h4>
                                <div style="background-color: rgba(211,211,211,0.25); padding: 10px; border: var(--box-header-bottom-border-color) 1px solid; max-height: 300px; overflow-y: auto;">
                                    {!! str_replace('<p>', '<p dir="auto">', Helper::parseEscapedMarkedown($acceptance->checkoutable->getEula())) !!}
                                </div>
                            </div>
                        @endif

                        {{-- Acceptance / Declination Radio --}}
                        <div class="col-md-12">
                            <label class="form-control">
                                <input type="radio" name="asset_acceptance" id="accepted" value="accepted">
                                {{ trans('general.i_accept') }}
                            </label>
                            <label class="form-control">
                                <input type="radio" name="asset_acceptance" id="declined" value="declined">
                                {{ trans('general.i_decline') }}
                            </label>
                        </div>

                        {{-- Decline Warning --}}
                        <div class="col-md-12 declined-warning" id="declinedWarning">
                            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                            {{ trans('admin/users/general.assigned_item_declined') }}
                        </div>

                        {{-- Note field --}}
                        <div class="col-md-12" style="margin-top: 10px;">
                            <label id="note_label" for="note">{{ trans('admin/settings/general.acceptance_note') }}</label>
                            <textarea id="note" name="note" rows="3" class="form-control" style="width:100%">{{ old('note') }}</textarea>
                        </div>

                        {{-- Signature Pad --}}
                        @if ($snipeSettings->require_accept_signature == '1')
                            <div class="col-md-12">
                                <h3 style="padding-top: 20px">{{ trans('general.sign_tos') }}</h3>
                                <div id="signature-pad" class="m-signature-pad">
                                    <div class="m-signature-pad--body col-md-12 col-sm-12 col-lg-12 col-xs-12">
                                        <canvas style="width:100%;"></canvas>
                                        <input type="hidden" name="signature_output" id="signature_output">
                                    </div>
                                    <div class="col-md-12 col-sm-12 col-lg-12 col-xs-12 text-left">
                                        <button type="button" class="btn btn-sm btn-theme clear" data-action="clear" id="clear_button">{{ trans('general.clear_signature') }}</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Send Copy Checkbox --}}
                        <div class="col-md-12" style="margin-top: 10px;">
                            <label class="form-control">
                                <input type="checkbox" value="1" name="send_copy" id="send_copy" checked="checked" aria-label="send_copy">
                                {{ trans('mail.send_pdf_copy') }} ({{ $assignedUser->email }})
                            </label>
                        </div>

                    </div>

                    {{-- Submit --}}
                    <div class="box-footer" style="display: none;" id="showSubmit">
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="submit" class="btn btn-success" id="submit-button">
                                    <i class="fa fa-check icon-white" aria-hidden="true" id="submitIcon"></i>
                                    <span id="buttonText">{{ trans('general.i_accept_item') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop

@section('moar_scripts')

    <script src="{{ url('js/signature_pad.min.js') }}" nonce="{{ csrf_token() }}"></script>

    <script nonce="{{ csrf_token() }}">

        @if ($snipeSettings->require_accept_signature == '1')

        var wrapper = document.getElementById("signature-pad"),
            canvas = wrapper.querySelector("canvas"),
            signaturePad;

        signaturePad = new SignaturePad(canvas);

        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }
        window.onresize = resizeCanvas;
        resizeCanvas();

        $('#clear_button').on("click", function (event) {
            signaturePad.clear();
        });

        $('#submit-button').on("click", function (event) {
            if (signaturePad.isEmpty()) {
                alert("{{ trans('general.sign_tos') }}");
                return false;
            } else {
                $('#signature_output').val(signaturePad.toDataURL());
            }
        });
        @endif

        $('[name="asset_acceptance"]').on('change', function() {
            if ($(this).is(':checked') && $(this).attr('id') === 'declined') {
                $("#showSubmit").show();
                $("#declinedWarning").show();
                $("#submit-button").removeClass("btn-success").addClass("btn-danger").show();
                $("#submitIcon").removeClass("fa-check").addClass("fa-times");
                $("#buttonText").text('{{ trans_choice('general.i_decline_item', 1) }}');
                $("#note").prop('required', true);

            } else if ($(this).is(':checked') && $(this).attr('id') === 'accepted') {
                $("#showSubmit").show();
                $("#declinedWarning").hide();
                $("#submit-button").removeClass("btn-danger").addClass("btn-success").show();
                $("#submitIcon").removeClass("fa-times").addClass("fa-check");
                $("#buttonText").text('{{ trans_choice('general.i_accept_item', 1) }}');
                $("#note").prop('required', false);
            }
        });
    </script>
@stop