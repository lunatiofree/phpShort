<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateRangeTrait
{
    /**
     * Generate the range for dates.
     *
     * @param $from
     * @param $to
     * @param bool $ignoreGetParams
     * @return array
     */
    private function range($from = null, $to = null, bool $ignoreGetParams = false)
    {
        $now = Carbon::now()->startOfDay();

        try {
            $to = request()->input('to') && $ignoreGetParams === false ? Carbon::createFromFormat('Y-m-d', request()->input('to')) : ($to ? $to->startOfDay() : $now);
        } catch (\Exception $e) {
            $to = $now;
        }

        try {
            $from = request()->input('from') && $ignoreGetParams === false ? Carbon::createFromFormat('Y-m-d', request()->input('from')) : ($from ? $from->startOfDay() : $to);

            $from = $from->gt($to) ? $to : $from;
        } catch (\Exception $exception) {
            $from = $to;
        }

        if ($from->diffInDays($to) < 1) {
            $unit = 'hour';
            $format = 'Y-m-d H';
        } elseif($from->diffInDays($to) < 90) {
            $unit = 'day';
            $format = 'Y-m-d';
        } elseif ($from->diffInDays($to) < 730) {
            $unit = 'month';
            $format = 'Y-m';
        } else {
            $unit = 'year';
            $format = 'Y';
        }

        // Reset the date range if it exceeds the limits
        if ($from->diffInDays($to) >= 36500) {
            $to = $now;
            $from = $to;
        }

        // Get the old period date range
        $to_old = (clone $from)->subDays(1);
        $from_old = (clone $to_old)->subDays($from->diffInDays($to));

        return ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'from_old' => $from_old->format('Y-m-d'), 'to_old' => $to_old->format('Y-m-d'), 'unit' => $unit, 'format' => $format];
    }

    /**
     * Calculate all the possible dates between two time frames.
     *
     * @param $to
     * @param $from
     * @param $unit
     * @param $format
     * @param mixed $output
     * @return mixed
     */
    private function calcAllDates($from, $to, $unit, $format, $output = 0)
    {
        if ($unit == 'second') {
            $from = Carbon::createFromFormat($format, $from);
            $to = Carbon::createFromFormat($format, $to);
        } else {
            $from = Carbon::createFromFormat($format, $from)->startOfDay();
            $to = Carbon::createFromFormat($format, $to)->endOfDay();
        }

        $possibleDateResults[$from->copy()->format($format)] = $output;

        while ($from->lt($to)) {
            if ($unit == 'year') {
                $from = $from->startOfYear()->addYears(1);
            } elseif ($unit == 'month') {
                $from = $from->startOfMonth()->addMonths(1);
            } elseif ($unit == 'day') {
                $from = $from->addDays(1);
            } elseif ($unit == 'hour') {
                $from = $from->addHours(1);
            } elseif ($unit == 'second') {
                $from = $from->addSeconds(1);
            }

            if ($from->lte($to)) {
                $possibleDateResults[$from->copy()->format($format)] = $output;
            }
        }

        return $possibleDateResults;
    }
}
