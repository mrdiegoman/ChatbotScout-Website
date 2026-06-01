<?php
/**
 * Shared promotion pricing helpers.
 */

function promotionPricingConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/promotion_pricing.php';
    }

    return $config;
}

function promotionYearlyDiscountPercentage(): float
{
    $config = promotionPricingConfig();
    return (float) ($config['yearly_discount_percentage'] ?? 0);
}

function promotionBundleDiscountPercentage(): float
{
    $config = promotionPricingConfig();
    return (float) ($config['bundle_discount_percentage'] ?? 0);
}

function promotionClampDiscountPercentage(float $discountPercentage): float
{
    return max(0.0, min(100.0, $discountPercentage));
}

function promotionSingleDiscountPercentage(float $dbDiscountPercentage, string $periodType): float
{
    $discount = $dbDiscountPercentage;

    if ($periodType === 'yearly') {
        $discount += promotionYearlyDiscountPercentage();
    }

    return promotionClampDiscountPercentage($discount);
}

function promotionBundleDiscountPercentageForPeriod(string $periodType): float
{
    $discount = promotionBundleDiscountPercentage();

    if ($periodType === 'yearly') {
        $discount += promotionYearlyDiscountPercentage();
    }

    return promotionClampDiscountPercentage($discount);
}

function promotionDiscountedMonthlyPrice(float $monthlyPrice, float $discountPercentage): float
{
    return $monthlyPrice * (1 - promotionClampDiscountPercentage($discountPercentage) / 100);
}

function promotionBillingMultiplier(string $periodType): int
{
    return $periodType === 'yearly' ? 12 : 1;
}

function promotionSingleMonthlyPrice(float $monthlyPrice, float $dbDiscountPercentage, string $periodType): float
{
    return promotionDiscountedMonthlyPrice(
        $monthlyPrice,
        promotionSingleDiscountPercentage($dbDiscountPercentage, $periodType)
    );
}

function promotionSingleTotalPrice(float $monthlyPrice, float $dbDiscountPercentage, string $periodType): float
{
    return promotionSingleMonthlyPrice($monthlyPrice, $dbDiscountPercentage, $periodType)
        * promotionBillingMultiplier($periodType);
}

function promotionBundleMonthlyPrice(float $monthlyBaseTotal, string $periodType): float
{
    return promotionDiscountedMonthlyPrice(
        $monthlyBaseTotal,
        promotionBundleDiscountPercentageForPeriod($periodType)
    );
}

function promotionBundleLineTotalPrice(float $monthlyPrice, string $periodType): float
{
    return promotionBundleMonthlyPrice($monthlyPrice, $periodType)
        * promotionBillingMultiplier($periodType);
}

function promotionBundleTotalPrice(float $monthlyBaseTotal, string $periodType): float
{
    return promotionBundleMonthlyPrice($monthlyBaseTotal, $periodType)
        * promotionBillingMultiplier($periodType);
}
