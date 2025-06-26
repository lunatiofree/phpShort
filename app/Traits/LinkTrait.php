<?php

namespace App\Traits;

use App\Models\Link;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait LinkTrait
{
    /**
     * Store the Link.
     *
     * @param Request $request
     * @return Link
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function linkStore(Request $request)
    {
        return $this->model($request, new Link, $request->input('url'), 0);
    }

    /**
     * Store the Links.
     *
     * @param Request $request
     * @return array
     * @throws GuzzleException
     */
    protected function linksStore(Request $request)
    {
        $urls = preg_split('/\n|\r/', $request->input('urls'), -1, PREG_SPLIT_NO_EMPTY);

        $data = [];
        foreach ($urls as $url) {
            $data[] = $this->model($request, new Link, $url, 0);
        }

        return $data;
    }

    /**
     * Update the Link.
     *
     * @param Request $request
     * @param Link $link
     * @return Link
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function linkUpdate(Request $request, Link $link)
    {
        return $this->model($request, $link, $request->input('url'), 1);
    }

    /**
     * Create or update the model.
     *
     * @param Request $request
     * @param Link $link
     * @param string $url The URL to be shortened
     * @param int $type
     * @return Link
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function model(Request $request, Link $link, $url, int $type)
    {
        $metadata = $this->parseUrl($url);

        if ($url) {
            $link->url = $url;
            $link->title = !empty($metadata) && isset($metadata['title']) ? trim(Str::limit($metadata['title'], 128)) : null;
            $link->description = !empty($metadata) && isset($metadata['description']) ? trim(Str::limit($metadata['description'], 512)) : null;
            $link->image = !empty($metadata) && isset($metadata['og:image']) ? trim($metadata['og:image']) : null;
        }

        if ($type == 0) {
            $link->user_id = ($request->user()->id ?? 0);
            $link->alias = $request->input('alias') ?? $this->generateAlias();
        } else {
            if ($request->has('alias') && !$request->input('multiple_links')) {
                $link->alias = $request->input('alias');
            }
        }

        if ($request->has('privacy')) {
            $link->privacy = $request->input('privacy');
        }

        if ($request->has('password')) {
            $link->password = $request->input('password');
        }

        if ($request->has('redirect_password')) {
            $link->redirect_password = $request->input('redirect_password');
        }

        if ($request->has('space_id')) {
            $link->space_id = $request->input('space_id');
        }

        if ($type == 0) {
            if ($request->has('domain_id')) {
                $link->domain_id = $request->input('domain_id');
            }
        }

        if ($request->has('expiration_url')) {
            $link->expiration_url = $request->input('expiration_url');
        }

        if ($request->has('active_period_start_at')) {
            $link->active_period_start_at = $request->input('active_period_start_at') ? Carbon::parse($request->input('active_period_start_at'), $request->user()->timezone ?? config('settings.timezone'))->tz(config('app.timezone'))->toDateTimeString() : null;
        }

        if ($request->has('active_period_end_at')) {
            $link->active_period_end_at = $request->input('active_period_end_at') ? Carbon::parse($request->input('active_period_end_at'), $request->user()->timezone ?? config('settings.timezone'))->tz(config('app.timezone'))->toDateTimeString() : null;
        }

        if ($request->has('clicks_limit')) {
            $link->clicks_limit = $request->input('clicks_limit');
        }

        if ($request->has('sensitive_content')) {
            $link->sensitive_content = $request->input('sensitive_content');
        }

        if ($request->has('targets_type')) {
            $link->targets_type = $request->input('targets_type');
        }

        if ($request->has('targets')) {
            $link->targets = array_filter(is_array($request->input('targets')) ? $request->input('targets') : [], function($item) { return isset($item['value']); });
        }

        $link->save();

        if ($request->has('pixel_ids')) {
            $link->pixels()->sync(array_filter($request->input('pixel_ids')) ?? []);
        }

        return $link;
    }

    /**
     * Generate a random unique alias.
     *
     * @return string|null
     */
    private function generateAlias()
    {
        $alias = null;
        $unique = false;
        $fails = 0;

        while (!$unique) {
            $alias = $this->generateString(5 + $fails);

            // Check if the alias exists
            if(!Link::where('alias', '=', $alias)->exists()) {
                $unique = true;
            }

            $fails++;
        }

        return $alias;
    }

    /**
     * Generate a random string.
     *
     * @param int $length
     * @return string
     */
    private function generateString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Parse the contents of a given URL.
     *
     * @param $url
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function parseUrl($url)
    {
        $metadata = [];

        $httpClient = new HttpClient();

        try {
            $content = $httpClient->request('GET', $url, [
                'version' => config('settings.request_http_version'),
                'proxy' => [
                    'http' => getRequestProxy(),
                    'https' => getRequestProxy()
                ],
                'timeout' => config('settings.request_timeout'),
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => config('settings.request_user_agent')
                ]
            ]);

            $headerType = $content->getHeader('content-type');
            $parsed = \GuzzleHttp\Psr7\Header::parse($headerType);
            $metadata = $this->formatMetaTags(mb_convert_encoding($content->getBody(), 'UTF-8', in_array($parsed[0]['charset'], mb_list_encodings()) ? $parsed[0]['charset'] : ($parsed[0]['charset'] == 'MS949' && in_array('UHC', mb_list_encodings()) ? 'CP949' : 'UTF-8')));
        } catch (\Exception $e) {
        }

        return $metadata;
    }

    /**
     * Parse and format the meta tags.
     *
     * @param $value
     * @return array|false
     */
    public function formatMetaTags($value)
    {
        $array = [];

        // Match the meta tags
        $pattern = '
            ~<\s*meta\s
        
            # using lookahead to capture type to $1
            (?=[^>]*?
            \b(?:name|property|http-equiv)\s*=\s*
            (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
            ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
            )
        
            # capture content to $2
            [^>]*?\bcontent\s*=\s*
            (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
            ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
            [^>]*>
        
            ~ix';
        if(preg_match_all($pattern, $value, $out)) {
            $array = array_combine(array_map('strtolower', $out[1]), $out[2]);
        }

        // Match the title tags
        preg_match("/<title[^>]*>(.*?)<\/title>/is", $value, $title);
        $array['title'] = $title[1];

        // Return the result
        return $array;
    }
}