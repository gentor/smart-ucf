<?php

namespace Gentor\SmartUcf\Service;


/**
 * Class SmartUcf
 * @package Gentor\SmartUcf\Service
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
            $installmentAmount = $price * $variant->coeff;

//            $glp = $variant->interestPercent;
            $glp = CreditCalculator::rate($price, $variant->installmentCount, $installmentAmount) * 12;
            $gpr = CreditCalculator::getGPR($price, $variant->installmentCount, $installmentAmount);
            if ($gpr < $glp) {
                $gpr = $glp;
            }

            $variants[] = (object)[
                'PricingSchemeId' => $variant->onlineProductCode ?: $schemeId,
                'PricingSchemeName' => $variant->installmentCount . ' months',
                'PricingVariantId' => $variant->installmentCount,
                'Maturity' => $variant->installmentCount,
                'InstallmentAmount' => $installmentAmount,
                'CorrectDownPaymentAmount' => $downPayment,
                'NIR' => $glp,
                'APR' => $gpr,
                'TotalRepaymentAmount' => $downPayment + $installmentAmount * $variant->installmentCount,
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
}