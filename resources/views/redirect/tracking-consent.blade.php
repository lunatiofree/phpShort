@extends('layouts.redirect')

@section('site_title', __($link->title))

@section('head_content')
    <meta name="robots" content="noindex">

    @if($link->title)
        <meta property="og:title" content="{{ $link->title }}">
    @endif

    @if($link->description)
        <meta name="description" content="{{ $link->description }}">
        <meta property="og:description" content="{{ $link->description }}">
    @endif

    @if($link->image)
        <meta property="og:image" content="{{ $link->image }}">
    @endif

    <meta property="og:url" content="{{ $link->url }}">

@endsection

@section('content')
<div class="bg-base-1 d-flex align-items-center flex-fill">
    <div class="container">
        <div class="row h-100 justify-content-center align-items-center py-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="font-weight-medium">
                            {{ __('This website uses cookies, to customise content and advertising, to analyse traffic and provide a better experience on it.') }}
                        </div>

                        <div class="mt-3 text-muted">
                            {!! __('By clicking :button, you agree to the storing of first and third party cookies on your device.', ['button' => '<strong>'.__('Accept').'</strong>']) !!}
                        </div>

                        <div class="text-muted font-weight-medium mt-3" data-toggle="collapse">
                            {{ __('Cookies') }}
                        </div>

                        <div class="my-3">
                            @foreach(config('pixels') as $key => $value)
                                @if($link->pixels->contains('type', $key))
                                    <div class="d-flex align-items-center text-truncate mt-3">
                                        <img src="{{ asset('img/icons/pixels/' . md5(strtolower($key))) }}.svg" rel="noreferrer" class="width-4 height-4 {{ (__('lang_dir') == 'rtl' ? 'ml-3' : 'mr-3') }}">
                                        <div class="text-truncate {{ (__('lang_dir') == 'rtl' ? 'ml-3' : 'mr-3') }}">{{ $value['name'] }} <span class="text-muted">({{ $value['type'] ? __('Statistics') : __('Marketing') }})</span></div>
                                        <a href="{{ $value['policy'] }}" target="_blank" rel="nofollow noreferrer noopener" class="text-secondary {{ (__('lang_dir') == 'rtl' ? 'mr-auto' : 'ml-auto') }}">{{ __('View policy') }}</a>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <form action="{{ route(Route::currentRouteName(), $link->id) }}/tracking-consent" method="post">
                            @csrf
                            <div class="form-row m-n2">
                                <div class="col-12 col-md-6 p-2">
                                    <button name="tracking" type="submit" value="0" class="btn btn-block btn-secondary">{{ __('Decline') }}</button>
                                </div>

                                <div class="col-12 col-md-6 p-2">
                                    <button name="tracking" type="submit" value="1" class="btn btn-block btn-primary">{{ __('Accept') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection