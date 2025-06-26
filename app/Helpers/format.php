<?php

/**
 * Format the page titles.
 *
 * @param null $value
 * @return string|null
 */
function formatTitle($value = null)
{
    if (is_array($value)) {
        return implode(" - ", $value);
    }

    return $value;
}

/**
 * Format money.
 *
 * @param $amount
 * @param $currency
 * @param bool $separator
 * @param bool $translate
 * @return string
 */
function formatMoney($amount, $currency, $separator = true, $translate = true)
{
    if (in_array(strtoupper($currency), config('currencies.zero_decimals'))) {
        return number_format($amount, 0, $translate ? __('.') : '.', $separator ? ($translate ? __(',') : ',') : false);
    } else {
        return number_format($amount, 2, $translate ? __('.') : '.', $separator ? ($translate ? __(',') : ',') : false);
    }
}

/**
 * Get and format the favicon URL.
 *
 * @param $url
 * @return string
 */
function favicon($url)
{
    return 'https://icons.duckduckgo.com/ip3/' . (parse_url($url ?? '', PHP_URL_HOST) ?? parse_url('http://' . $url, PHP_URL_HOST)). '.ico';
}

/**
 * Convert a number into a readable one.
 *
 * @param   int   $number  The number to be transformed
 * @return  string
 */
function shortenNumber($number)
{
    $suffix = ["", "K", "M", "B"];
    $precision = 1;
    for($i = 0; $i < count($suffix); $i++) {
        $divide = $number / pow(1000, $i);
        if($divide < 1000) {
            return round($divide, $precision).$suffix[$i];
        }
    }

    return $number;
}

/**
 * Format the captcha field name.
 *
 * @return string
 */
function formatCaptchaFieldName()
{
    $fields = [
        'turnstile' => 'cf-turnstile-response',
        'hcaptcha' => 'h-captcha-response',
        'recaptcha' => 'g-recaptcha-response'
    ];

    if (array_key_exists(mb_strtolower(config('settings.captcha_driver')), $fields)) {
        return $fields[mb_strtolower(config('settings.captcha_driver'))];
    }

    return '';
}

/**
 * Format the spaces codes.
 *
 * @return array
 */
function formatSpace()
{
    return [
        1 => 'success',
        2 => 'danger',
        3 => 'warning',
        4 => 'info',
        5 => 'dark',
        6 => 'primary'
    ];
}

/**
 * Format the browser icon.
 *
 * @param $key
 * @return mixed|string
 */
function formatBrowser($key)
{
    $browser = [
        'Chrome' => 'chrome',
        'Chromium' => 'chromium',
        'Firefox' => 'firefox',
        'Firefox Mobile' => 'firefox',
        'Edge' => 'edge',
        'Internet Explorer' => 'ie',
        'Mobile Internet Explorer' => 'ie',
        'Vivaldi' => 'vivaldi',
        'Brave' => 'brave',
        'Safari' => 'safari',
        'Opera' => 'opera',
        'Opera Mini' => 'opera',
        'Opera Mobile' => 'opera',
        'Opera Touch' => 'operatouch',
        'Yandex Browser' => 'yandex',
        'UC Browser' => 'ucbrowser',
        'Samsung Internet' => 'samsung',
        'QQ Browser' => 'qq',
        'BlackBerry Browser' => 'bbbrowser',
        'Maxthon' => 'maxthon'
    ];

    if (array_key_exists($key, $browser)) {
        return $browser[$key];
    } else {
        return 'unknown';
    }
}

/**
 * Format the operating system icon.
 *
 * @param $key
 * @return mixed|string
 */
function formatOperatingSystem($key)
{
    $platforms = [
        'Windows' => 'windows',
        'Linux' => 'linux',
        'Ubuntu' => 'ubuntu',
        'Windows Phone' => 'windows',
        'iOS' => 'apple',
        'OS X' => 'apple',
        'FreeBSD' => 'freebsd',
        'Android' => 'android',
        'Chrome OS' => 'chromeos',
        'BlackBerry OS' => 'bbos',
        'Tizen' => 'tizen',
        'KaiOS' => 'kaios',
        'BlackBerry Tablet OS' => 'bbos',
        'Fedora' => 'fedora'
    ];

    if (array_key_exists($key, $platforms)) {
        return $platforms[$key];
    } else {
        return 'unknown';
    }
}

/**
 * Format the device icon.
 *
 * @param $key
 * @return mixed|string
 */
function formatDevice($key)
{
    $devices = [
        'desktop' => 'desktop',
        'mobile' => 'mobile',
        'tablet' => 'tablet',
        'television' => 'tv',
        'gaming' => 'gaming',
        'watch' => 'watch'
    ];

    if (array_key_exists($key, $devices)) {
        return $devices[$key];
    } else {
        return 'unknown';
    }
}

/**
 * Format the flag icon.
 *
 * @param $value
 * @return string
 */
function formatFlag($value)
{
    $country = explode(':', $value);

    if (isset($country[0]) && !empty($country[0])) {
        // Return the country code
        return strtolower($country[0]);
    } else {
        return 'unknown';
    }
}
