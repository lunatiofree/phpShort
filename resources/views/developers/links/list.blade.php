@php
    $parameters = [
        [
            'name' => 'search',
            'type' => 0,
            'format' => 'string',
            'description' => __('Search query') . '.'
        ], [
            'name' => 'search_by',
            'type' => 0,
            'format' => 'string',
            'description' => __('Search by') . '. ' . __('Possible values are: :values.', [
                'values' => implode(', ', [
                    __(':value for :name', ['value' => '<code>title</code>', 'name' => '<span class="font-weight-medium">'.__('Title').'</span>']),
                    __(':value for :name', ['value' => '<code>alias</code>', 'name' => '<span class="font-weight-medium">'.__('Alias').'</span>']),
                    __(':value for :name', ['value' => '<code>url</code>', 'name' => '<span class="font-weight-medium">'.__('URL').'</span>'])
                    ])
                ]) .' ' . __('Defaults to: :value.', ['value' => '<code>title</code>'])
        ], [
            'name' => 'status',
            'type' => 0,
            'format' => 'integer',
            'description' => __('Status') . '. ' . __('Possible values are: :values.', [
                'values' => implode(', ', [
                    __(':value for :name', ['value' => '<code>0</code>', 'name' => '<span class="font-weight-medium">'.__('All').'</span>']),
                    __(':value for :name', ['value' => '<code>1</code>', 'name' => '<span class="font-weight-medium">'.__('Active').'</span>']),
                    __(':value for :name', ['value' => '<code>2</code>', 'name' => '<span class="font-weight-medium">'.__('Expired').'</span>']),
                    __(':value for :name', ['value' => '<code>3</code>', 'name' => '<span class="font-weight-medium">'.__('Disabled').'</span>'])
                    ])
                ]) .' ' . __('Defaults to: :value.', ['value' => '<code>0</code>'])
        ], [
            'name' => 'space_id',
            'type' => 0,
            'format' => 'integer',
            'description' => __('Space ID') . '.'
        ], [
            'name' => 'domain_id',
            'type' => 0,
            'format' => 'integer',
            'description' => __('Domain ID') . '.'
        ], [
            'name' => 'pixel_id',
            'type' => 0,
            'format' => 'integer',
            'description' => __('Pixel ID') . '.'
        ], [
            'name' => 'sort_by',
            'type' => 0,
            'format' => 'string',
            'description' => __('Sort by') . '. ' . __('Possible values are: :values.', [
                'values' => implode(', ', [
                    __(':value for :name', ['value' => '<code>id</code>', 'name' => '<span class="font-weight-medium">'.__('Date created').'</span>']),
                    __(':value for :name', ['value' => '<code>clicks</code>', 'name' => '<span class="font-weight-medium">'.__('Clicks').'</span>']),
                    __(':value for :name', ['value' => '<code>title</code>', 'name' => '<span class="font-weight-medium">'.__('Title').'</span>']),
                    __(':value for :name', ['value' => '<code>alias</code>', 'name' => '<span class="font-weight-medium">'.__('Alias').'</span>']),
                    __(':value for :name', ['value' => '<code>url</code>', 'name' => '<span class="font-weight-medium">'.__('URL').'</span>'])
                    ])
                ]) .' ' . __('Defaults to: :value.', ['value' => '<code>id</code>'])
        ], [
            'name' => 'sort',
            'type' => 0,
            'format' => 'string',
            'description' => __('Sort') . '. ' . __('Possible values are: :values.', [
                'values' => implode(', ', [
                    __(':value for :name', ['value' => '<code>desc</code>', 'name' => '<span class="font-weight-medium">'.__('Descending').'</span>']),
                    __(':value for :name', ['value' => '<code>asc</code>', 'name' => '<span class="font-weight-medium">'.__('Ascending').'</span>'])
                    ])
                ]) .' ' . __('Defaults to: :value.', ['value' => '<code>desc</code>'])
        ], [
            'name' => 'per_page',
            'type' => 0,
            'format' => 'integer',
            'description' => __('Results per page') . '. '. __('Possible values are: :values.', [
                'values' => '<code>' . implode('</code>, <code>', [10, 25, 50, 100]) . '</code>'
                ]) .' ' . __('Defaults to: :value.', ['value' => '<code>'.config('settings.paginate').'</code>'])
        ]
    ];
@endphp

@include('developers.parameters')