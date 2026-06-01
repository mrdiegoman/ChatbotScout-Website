<?php
/**
 * Promotion pricing configuration.
 *
 * Prices stored in the promotions table are monthly base prices.
 */

return [
    // Applied to yearly billing for single promotion packages.
    'yearly_discount_percentage' => 20,

    // Extra bundle discount. Yearly bundle discount = yearly + bundle.
    'bundle_discount_percentage' => 10,
];
