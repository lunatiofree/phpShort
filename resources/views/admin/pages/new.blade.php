@section('site_title', formatTitle([__('New'), __('Page'), config('settings.title')]))

@include('shared.breadcrumbs', ['breadcrumbs' => [
    ['url' => route('admin.dashboard'), 'title' => __('Admin')],
    ['url' => route('admin.pages'), 'title' => __('Pages')],
    ['title' => __('New')],
]])

<h1 class="h2 mb-3 d-inline-block">{{ __('New') }}</h1>

<div class="card border-0 shadow-sm">
    <div class="card-header"><div class="font-weight-medium py-1">{{ __('Page') }}</div></div>
    <div class="card-body">
        @include('shared.message')

        <form action="{{ route('admin.pages.new') }}" method="post" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label for="i-name">{{ __('Name') }}</label>
                <input type="text" name="name" id="i-name" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" value="{{ old('name') }}">
                @if ($errors->has('name'))
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $errors->first('name') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="i-slug">{{ __('Slug') }}</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text d-block align-items-center text-truncate max-width-52 max-width-md-full">{{ str_replace(['http://', 'https://'], '', route('pages.show', ['id' => '/'])) }}/</span>
                    </div>
                    <input type="text" name="slug" id="i-slug" class="form-control{{ $errors->has('slug') ? ' is-invalid' : '' }}" value="{{ old('slug') }}">
                </div>
                @if ($errors->has('slug'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('slug') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="i-visibility">{{ __('Visibility') }}</label>
                <select name="visibility" id="i-visibility" class="custom-select{{ $errors->has('visibility') ? ' is-invalid' : '' }}">
                    @foreach([0 => __('Unlisted'), 1 => __('Footer')] as $key => $value)
                        <option value="{{ $key }}" @if (old('visibility') == $key) selected @endif>{{ $value }}</option>
                    @endforeach
                </select>
                @if ($errors->has('visibility'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('visibility') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="i-language">{{ __('Language') }}</label>
                <select name="language" id="i-language" class="custom-select{{ $errors->has('language') ? ' is-invalid' : '' }}">
                    @foreach(config('app.locales') as $key => $value)
                        <option value="{{ $key }}" @if (old('language') == $key) selected @endif>{{ $value['name'] }}</option>
                    @endforeach
                </select>
                @if ($errors->has('language'))
                    <span class="invalid-feedback d-block" role="alert">
                        <strong>{{ $errors->first('language') }}</strong>
                    </span>
                @endif
            </div>

            <div class="form-group">
                <label for="i-content">{{ __('Content') }}</label>
                <textarea name="content" id="i-content" class="form-control{{ $errors->has('content') ? ' is-invalid' : '' }}">{{ old('content') }}</textarea>
                @if ($errors->has('content'))
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $errors->first('content') }}</strong>
                    </span>
                @endif
            </div>

            <button type="submit" name="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>
    </div>
</div>
