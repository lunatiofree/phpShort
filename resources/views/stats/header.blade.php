@include('shared.breadcrumbs', ['breadcrumbs' => [
    ['url' => route('dashboard'), 'title' => __('Home')],
    ['title' => __('Stats')]
]])

<div class="d-flex align-items-end mb-3">
    <h1 class="h2 mb-0 flex-grow-1 text-truncate">{{ $link->displayShortUrl }}</h1>

    <div class="d-flex align-items-center flex-grow-0">
        <div class="form-row flex-nowrap">
            <div class="col">
                @include('shared.buttons.copy-link', ['class' => 'text-secondary'])
            </div>

            <div class="col">
                <a href="#" class="btn text-secondary d-flex align-items-center" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">@include('icons.more-horiz', ['class' => 'fill-current width-4 height-4'])&#8203;</a>

                @include('links.partials.menu')
            </div>

            @if($link->user)
                <div class="col">
                    <a href="#" class="btn border text-secondary" id="date-range-selector">
                        <div class="d-flex align-items-center cursor-pointer">
                            @include('icons.date-range', ['class' => 'fill-current width-4 height-4 flex-shrink-0'])&#8203;

                            <span class="{{ (__('lang_dir') == 'rtl' ? 'mr-2' : 'ml-2') }} d-none d-lg-block text-nowrap" id="date-range-value">
                                @if($range['from'] == $range['to'])
                                    @if(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->isToday())
                                        {{ __('Today') }}
                                    @elseif(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->isYesterday())
                                        {{ __('Yesterday') }}
                                    @else
                                        {{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }} - {{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }}
                                    @endif
                                @else
                                    @if(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->subDays(6)->format('Y-m-d') && \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == $now->format('Y-m-d'))
                                        {{ __('Last :days days', ['days' => 7]) }}
                                    @elseif(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->subDays(29)->format('Y-m-d') && \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == $now->format('Y-m-d'))
                                        {{ __('Last :days days', ['days' => 30]) }}
                                    @elseif(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->startOfMonth()->format('Y-m-d') && \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->endOfMonth()->format('Y-m-d'))
                                        {{ __('This month') }}
                                    @elseif(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->subMonthNoOverflow()->startOfMonth()->format('Y-m-d') && \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == (clone $now)->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'))
                                        {{ __('Last month') }}
                                    @elseif(\Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == $link->created_at->format('Y-m-d') && \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format('Y-m-d') == $now->format('Y-m-d'))
                                        {{ __('All time') }}
                                    @else
                                        {{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }} - {{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }}
                                    @endif
                                @endif
                            </span>

                            @include('icons.expand-more', ['class' => 'flex-shrink-0 fill-current width-3 height-3 '.(__('lang_dir') == 'rtl' ? 'mr-2' : 'ml-2')])
                        </div>
                    </a>

                    <form method="GET" name="date-range" action="{{ route(Route::currentRouteName(), ['id' => $link->id]) }}">
                        <input name="from" type="hidden">
                        <input name="to" type="hidden">
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

@if($link->user)
    @include('stats.menu')
    <script>
        'use strict';

        window.addEventListener('DOMContentLoaded', function () {
            document.querySelector('#date-range-selector') && document.querySelector('#date-range-selector').addEventListener('click', function (e) {
                e.preventDefault();
            });

            jQuery('#date-range-selector').daterangepicker({
                ranges: {
                    "{{ __('Today') }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }})],
                    "{{ __('Yesterday') }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(1, 'days'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(1, 'days')],
                    "{{ __('Last :days days', ['days' => 7]) }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(6, 'days'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }})],
                    "{{ __('Last :days days', ['days' => 30]) }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(29, 'days'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }})],
                    "{{ __('This month') }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).startOf('month'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).endOf('month')],
                    "{{ __('Last month') }}": [moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(1, 'month').startOf('month'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }}).subtract(1, 'month').endOf('month')],
                    "{{ __('All time') }}": [moment('{{ $link->created_at->format('Y-m-d') }}'), moment().utcOffset({{ (clone $now)->tz(Auth::user()->timezone ?? config('settings.timezone'))->utcOffset() }})]
                },
                locale: {
                    direction: "{{ (__('lang_dir') == 'rtl' ? 'rtl' : 'ltr') }}",
                    format: "{{ str_ireplace(['y', 'm', 'd'], ['YYYY', 'MM', 'DD'], __('Y-m-d')) }}",
                    separator: " - ",
                    applyLabel: "{{ __('Apply') }}",
                    cancelLabel: "{{ __('Cancel') }}",
                    customRangeLabel: "{{ __('Custom') }}",
                    daysOfWeek: [
                        "{{ __('Su') }}",
                        "{{ __('Mo') }}",
                        "{{ __('Tu') }}",
                        "{{ __('We') }}",
                        "{{ __('Th') }}",
                        "{{ __('Fr') }}",
                        "{{ __('Sa') }}"
                    ],
                    monthNames: [
                        "{{ __('January') }}",
                        "{{ __('February') }}",
                        "{{ __('March') }}",
                        "{{ __('April') }}",
                        "{{ __('May') }}",
                        "{{ __('June') }}",
                        "{{ __('July') }}",
                        "{{ __('August') }}",
                        "{{ __('September') }}",
                        "{{ __('October') }}",
                        "{{ __('November') }}",
                        "{{ __('December') }}"
                    ]
                },
                startDate : "{{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['from'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }}",
                endDate : "{{ \Carbon\Carbon::createFromFormat('Y-m-d', $range['to'], Auth::user()->timezone ?? config('settings.timezone'))->format(__('Y-m-d')) }}",
                opens: "{{ (__('lang_dir') == 'rtl' ? 'right' : 'left') }}",
                applyClass: "btn-primary",
                cancelClass: "btn-secondary",
                linkedCalendars: false,
                alwaysShowCalendars: true
            });

            jQuery('#date-range-selector').on('apply.daterangepicker', function (ev, picker) {
                document.querySelector('input[name="from"]').value = picker.startDate.format('YYYY-MM-DD');
                document.querySelector('input[name="to"]').value = picker.endDate.format('YYYY-MM-DD');

                document.querySelector('form[name="date-range"]').submit();
            });

            jQuery('#date-range-selector').on('hide.daterangepicker', function (ev, picker) {
                document.querySelector('#date-range-selector').classList.remove('active');
            });

            jQuery('#date-range-selector').on('show.daterangepicker', function (ev, picker) {
                document.querySelector('#date-range-selector').classList.add('active');
            });
        });
    </script>
@endif