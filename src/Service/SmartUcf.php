<?php

namespace Gentor\SmartUcf\Service;

use PhpOffice\PhpSpreadsheet\Calculation\Financial;


/**
 * Class SmartUcf
 * @package Gentor\SmartUcf\Service
 *
 * @method string sessionStart(array $params)
 * @method string redirect($suosId)
 * @method \stdClass getInfo($orderNo)
 */
class SmartUcf
{
    /** @var SmartUcfClient */
    protected $client;

    /**
     * SmartUcf constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->client = new SmartUcfClient($config);
    }

    /**
     * @param float $price
     * @param array $goods
     * @param float|int $down_payment
     *
     * @return array
     */
    public function getPricingSchemes($price, array $goods, $down_payment = 0)
    {
        return [
            (object)[
                'PricingSchemeId' => 1,
                'PricingSchemeName' => '',
                'variants' => []
            ]
        ];
    }

    /**
     * @param float $price
     * @param array $goods
     * @param int $schemeId
     * @param float|int $downPayment
     * @param float|int $installment
     * @return array
     * @throws SmartUcfException
     */
    public function getPricingVariants($price, array $goods, $schemeId = null, $downPayment = 0, $installment = 0)
    {
        $price -= $downPayment;
        $onlineProductCode = array_first($goods);
        $params = !empty($onlineProductCode) ? ['onlineProductCode' => $onlineProductCode] : [];

        $pricing = $this->client->getCoeff($params);

        $variants = [];
        foreach ($pricing->coeffList as $variant) {
            $installmentAmount = round($price * $variant->coeff, 2);

            $nir = $variant->interestPercent;
            $apr = !$nir ? $nir : $this->calculateAPR($price, $variant->installmentCount, $installmentAmount);
            $totalAmount = !$nir ? $downPayment + $price : $downPayment + $installmentAmount * $variant->installmentCount;

            $variants[] = (object)[
                'PricingSchemeId' => $variant->onlineProductCode ?: $schemeId,
                'PricingSchemeName' => $variant->installmentCount . ' months',
                'PricingVariantId' => $variant->installmentCount,
                'Maturity' => $variant->installmentCount,
                'InstallmentAmount' => $installmentAmount,
                'CorrectDownPaymentAmount' => $downPayment,
                'NIR' => $nir,
                'APR' => $apr,
                'TotalRepaymentAmount' => $totalAmount,
            ];
        }

        if ($installment > 0) {
            $iMax = $installment * (1 + 20 / 100);
            $iMin = $installment * (1 - 20 / 100);
            foreach ($variants as $key => $variant) {
                if ($iMax < $variant->InstallmentAmount || $iMin > $variant->InstallmentAmount) {
                    unset($variants[$key]);
                }
            }
        }

        return $variants;
    }

    /**
     * @param array $productIds
     * @param $price
     * @param int $downPayment
     * @param int $installment
     * @return array
     * @throws SmartUcfException
     */
    public function getPricingData(array $productIds, $price, $downPayment = 0, $installment = 0)
    {
        $schemes = $this->getPricingSchemes($price, $productIds, $downPayment);

        foreach ($schemes as $scheme) {
            $scheme->variants = $this->getPricingVariants($price, $productIds,
                $scheme->PricingSchemeId, $downPayment, $installment);
        }

        return [
            'schemes' => $schemes,
            'downPayment' => (float)$downPayment ?: null,
        ];
    }

    /**
     * @param $pricingVariantId
     * @param $productIds
     * @param $price
     * @param int $downPayment
     * @param int $installment
     * @return array
     * @throws SmartUcfException
     */
    public function getPriceVariantId($pricingVariantId, $productIds, $price, $downPayment = 0, $installment = 0)
    {
        $data = $this->getPricingData($productIds, $price, $downPayment, $installment);
        foreach (!empty($data['schemes']) ? $data['schemes'] : [] AS $scheme) {
            foreach ($scheme->variants AS $variant) {
                if ($variant->PricingVariantId == $pricingVariantId) {
                    return $variant;
                }
            }
        }

        return null;
    }

    /**
     * @param $method
     * @param array $args
     * @return \stdClass|mixed
     */
    public function __call($method, array $args)
    {
        return call_user_func_array([$this->client, $method], $args);
    }

    /**
     * @param $amount
     * @param $installmentCount
     * @param $installmentAmount
     * @return float
     */
    protected function calculateAPR($amount, $installmentCount, $installmentAmount)
    {
        $monthlyPayments[] = $amount * -1;
        for ($i = 1; $i <= $installmentCount; $i++) {
            $monthlyPayments[] = $installmentAmount;
        }

        $irr = Financial::IRR($monthlyPayments, 0.005);
        $pow = pow($irr * 100 + 100, 12);

        return round(substr(sprintf('%F', $pow), 1, 6) / 10000, 3);
    }
}