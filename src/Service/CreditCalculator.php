<?php

namespace Gentor\SmartUcf\Service;


/**
 * Class CreditCalculator
 * @package Gentor\SmartUcf\Service
 */
class CreditCalculator
{
    /**
     *
     */
    const FINANCIAL_ACCURACY = 1.0e-6;
    /**
     *
     */
    const FINANCIAL_MAX_ITERATIONS = 100;

    /**
     * DATEDIFF
     * Returns the number of date and time boundaries crossed between two specified dates.
     *
     * @param  string $datepart is the parameter that specifies on which part of the date to calculate the difference.
     * @param  integer $startdate is the beginning date (Unix timestamp) for the calculation.
     * @param  integer $enddate is the ending date (Unix timestamp) for the calculation.
     *
     * @return integer the number between the two dates.
     */
    public static function DATEDIFF($datepart, $startdate, $enddate)
    {
        switch (strtolower($datepart)) {
            case 'yy':
            case 'yyyy':
            case 'year':
                $di = getdate($startdate);
                $df = getdate($enddate);
                return $df['year'] - $di['year'];
                break;
            case 'q':
            case 'qq':
            case 'quarter':
                die("Unsupported operation");
                break;
            case 'n':
            case 'mi':
            case 'minute':
                return ceil(($enddate - $startdate) / 60);
                break;
            case 'hh':
            case 'hour':
                return ceil(($enddate - $startdate) / 3600);
                break;
            case 'd':
            case 'dd':
            case 'day':
                return ceil(($enddate - $startdate) / 86400);
                break;
            case 'wk':
            case 'ww':
            case 'week':
                return ceil(($enddate - $startdate) / 604800);
                break;
            case 'm':
            case 'mm':
            case 'month':
                $di = getdate($startdate);
                $df = getdate($enddate);
                return ($df['year'] - $di['year']) * 12 + ($df['mon'] - $di['mon']);
                break;
            default:
                die("Unsupported operation");
        }
    }

    /**
     * XNPV
     * Returns the net present value for a schedule of cash flows that
     * is not necessarily periodic. To calculate the net present value
     * for a series of cash flows that is periodic, use the NPV function.
     *
     *        n   /                values(i)               \
     * NPV = SUM | ---------------------------------------- |
     *       i=1 |           ((dates(i) - dates(1)) / 365)  |
     *            \ (1 + rate)                             /
     *
     */
    public static function XNPV($rate, $values, $dates)
    {
        if ((!is_array($values)) || (!is_array($dates))) {
            return null;
        }
        if (count($values) != count($dates)) {
            return null;
        }
        $xnpv = 0.0;
        for ($i = 0; $i < count($values); $i++) {
            $xnpv += $values[$i] / pow(1 + $rate, static::DATEDIFF('day', $dates[0], $dates[$i]) / 365);
        }
        return (is_finite($xnpv) ? $xnpv : null);
    }

    /**
     * XIRR
     * Returns the internal rate of return for a schedule of cash flows
     * that is not necessarily periodic. To calculate the internal rate
     * of return for a series of periodic cash flows, use the IRR function.
     *
     * Adapted from routine in Numerical Recipes in C, and translated
     * from the Bernt A Oedegaard algorithm in C
     *
     */
    public static function XIRR($values, $dates, $guess = 0.1)
    {
        if ((!is_array($values)) && (!is_array($dates))) {
            return null;
        }
        if (count($values) != count($dates)) {
            return null;
        }
        // create an initial bracket, with a root somewhere between bot and top
        $x1 = 0.0;
        $x2 = $guess;
        $f1 = static::XNPV($x1, $values, $dates);
        $f2 = static::XNPV($x2, $values, $dates);
        for ($i = 0; $i < static::FINANCIAL_MAX_ITERATIONS; $i++) {
            if (($f1 * $f2) < 0.0) {
                break;
            }
            if (abs($f1) < abs($f2)) {
                $f1 = static::XNPV($x1 += 1.6 * ($x1 - $x2), $values, $dates);
            } else {
                $f2 = static::XNPV($x2 += 1.6 * ($x2 - $x1), $values, $dates);
            }
        }
        if (($f1 * $f2) > 0.0) {
            return null;
        }
        $f = static::XNPV($x1, $values, $dates);
        if ($f < 0.0) {
            $rtb = $x1;
            $dx = $x2 - $x1;
        } else {
            $rtb = $x2;
            $dx = $x1 - $x2;
        }
        for ($i = 0; $i < static::FINANCIAL_MAX_ITERATIONS; $i++) {
            $dx *= 0.5;
            $x_mid = $rtb + $dx;
            $f_mid = static::XNPV($x_mid, $values, $dates);
            if ($f_mid <= 0.0) {
                $rtb = $x_mid;
            }
            if ((abs($f_mid) < static::FINANCIAL_ACCURACY) || (abs($dx) < static::FINANCIAL_ACCURACY)) {
                return $x_mid;
            }
        }
        return null;
    }

    /**
     * @param $amount
     * @param $month
     * @param $payment
     * @return float|int
     */
    public static function rate($amount, $month, $payment)
    {
        // make an initial guess
        $error = 0.0000001;
        $high = 1.00;
        $low = 0.00;
        $rate = (2.0 * ($month * $payment - $amount)) / ($amount * $month);

        while (true) {
            // check for error margin
            $calc = pow(1 + $rate, $month);
            $calc = ($rate * $calc) / ($calc - 1.0);
            $calc -= $payment / $amount;
            if ($calc > $error) {
                // guess too high, lower the guess
                $high = $rate;
                $rate = ($high + $low) / 2;
            } elseif ($calc < -$error) {
                // guess too low, higher the guess
                $low = $rate;
                $rate = ($high + $low) / 2;
            } else {
                // acceptable guess
                break;
            }
        }

        return $rate * 100;
    }

    /**
     * @param $total
     * @param $months
     * @param $installmentAmount
     * @return float|int
     */
    public static function getGPR($total, $months, $installmentAmount)
    {
        if (date('d') > 15) {
            if (date('d') <= 25) {
                $firstPaymentDay = '25';
            } else {
                $firstPaymentDay = '5';
            }
        } else {
            if (date('d') < 6) {
                $firstPaymentDay = '5';
            } else {
                $firstPaymentDay = '15';
            }
        }

        if (date('d') > 25) {
            $firstPaymentDate = strtotime(date('Y') . '-' . date('m') . '-' . $firstPaymentDay . ' + ' . '2 months');
            $lastPaymentDate = strtotime(date('Y') . '-' . date('m') . '-' . $firstPaymentDay . ' + ' . ($months + 1) . ' months');
        } else {
            $firstPaymentDate = strtotime(date('Y') . '-' . date('m') . '-' . $firstPaymentDay . ' + ' . '1 months');
            $lastPaymentDate = strtotime(date('Y') . '-' . date('m') . '-' . $firstPaymentDay . ' + ' . $months . ' months');
        }

        $dates = [
            time(),
            $firstPaymentDate
        ];
        for ($i = 2; $i < $months; $i++) {
            $dates[] = strtotime('+' . ($i - 1) . ' month', $firstPaymentDate);
        }
        $dates[] = $lastPaymentDate;

        $monthlyPayments[] = $total * -1;
        for ($i = 1; $i <= $months; $i++) {
            $monthlyPayments[] = $installmentAmount;
        }

        return static::XIRR($monthlyPayments, $dates) * 100;
    }
}