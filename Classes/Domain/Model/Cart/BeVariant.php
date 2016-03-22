<?php

namespace Extcode\Cart\Domain\Model\Cart;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Cart BeVariant Model
 *
 * @package cart
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class BeVariant
{

    /**
     * Id
     *
     * @var string
     */
    private $id = '';

    /**
     * Product
     *
     * @var \Extcode\Cart\Domain\Model\Cart\Product
     */
    private $product = null;

    /**
     * BeVariant
     *
     * @var \Extcode\Cart\Domain\Model\Cart\BeVariant
     */
    private $parentBeVariant = null;

    /**
     * Title
     *
     * @var string
     */
    private $title = '';

    /**
     * SKU
     *
     * @var string
     */
    private $sku = '';

    /**
     * SKU Delimiter
     *
     * @var string
     */
    private $skuDelimiter = '-';

    /**
     * Price Calc Method
     *
     * @var int
     */
    private $priceCalcMethod = 0;

    /**
     * Price
     *
     * @var float
     */
    private $price = 0.0;

    /**
     * Quantity
     *
     * @var int
     */
    private $quantity = 0;

    /**
     * Variants
     *
     * @var \Extcode\Cart\Domain\Model\Cart\BeVariant[]
     */
    private $beVariants;

    /**
     * Gross
     *
     * @var float
     */
    private $gross = 0.0;

    /**
     * Net
     *
     * @var float
     */
    private $net = 0.0;

    /**
     * Tax
     *
     * @var float
     */
    private $tax = 0.0;

    /**
     * Is Fe Variant
     *
     * @var bool
     */
    private $isFeVariant = false;

    /**
     * Number Of Fe Variant
     *
     * @var int
     */
    private $hasFeVariants;

    /**
     * Min
     *
     * @var int
     */
    private $min = 0;

    /**
     * Max
     *
     * @var int
     */
    private $max = 0;

    /**
     * Additional
     *
     * @var array Additional
     */
    private $additional = array();

    /**
     * __construct
     *
     * @param string $id
     * @param \Extcode\Cart\Domain\Model\Cart\Product $product
     * @param \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant
     * @param string $title
     * @param string $sku
     * @param int $priceCalcMethod
     * @param float $price
     * @param int $quantity
     *
     * @return \Extcode\Cart\Domain\Model\Cart\BeVariant
     */
    public function __construct(
        $id,
        $product = null,
        $beVariant = null,
        $title,
        $sku,
        $priceCalcMethod,
        $price,
        $quantity = 0
    ) {
        if ($product === null && $beVariant === null) {
            throw new \InvalidArgumentException;
        }

        if ($product != null && $beVariant != null) {
            throw new \InvalidArgumentException;
        }

        if (!$title) {
            throw new \InvalidArgumentException(
                'You have to specify a valid $title for constructor.',
                1437166475
            );
        }

        if (!$sku) {
            throw new \InvalidArgumentException(
                'You have to specify a valid $sku for constructor.',
                1437166615
            );
        }

        if (!$quantity) {
            throw new \InvalidArgumentException(
                'You have to specify a valid $quantity for constructor.',
                1437166805
            );
        }

        $this->id = $id;

        if ($product != null) {
            $this->product = $product;
        }

        if ($beVariant != null) {
            $this->parentBeVariant = $beVariant;
        }

        $this->title = $title;
        $this->sku = $sku;
        $this->priceCalcMethod = $priceCalcMethod;
        $this->price = floatval(str_replace(',', '.', $price));
        $this->quantity = $quantity;

        $this->reCalc();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $variantArr = array(
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'price_calc_method' => $this->priceCalcMethod,
            'price' => $this->getPrice(),
            'taxClass' => $this->getTaxClass(),
            'quantity' => $this->quantity,
            'price_total_gross' => $this->gross,
            'price_total_net' => $this->net,
            'tax' => $this->tax,
            'additional' => $this->additional
        );

        if ($this->beVariants) {
            $innerVariantArr = array();

            foreach ($this->beVariants as $variant) {
                /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $variant */
                array_push($innerVariantArr, array($variant->getId() => $variant->toArray()));
            }

            array_push($variantArr, array('variants' => $innerVariantArr));
        }

        return $variantArr;
    }

    /**
     * Gets Product
     *
     * @return \Extcode\Cart\Domain\Model\Cart\Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Gets Parent Variant
     *
     * @return \Extcode\Cart\Domain\Model\Cart\BeVariant
     */
    public function getParentBeVariant()
    {
        return $this->parentBeVariant;
    }

    /**
     * Gets Is Net Price
     *
     * @return bool
     */
    public function getIsNetPrice()
    {
        $isNetPrice = false;

        if ($this->getParentBeVariant()) {
            $isNetPrice = $this->getParentBeVariant()->getIsNetPrice();
        } elseif ($this->getProduct()) {
            $isNetPrice = $this->getProduct()->getIsNetPrice();
        }

        return $isNetPrice;
    }

    /**
     * Gets Id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets Title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Gets Price
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }


    /**
     * Gets Discount
     *
     * @return float
     */
    public function getDiscount()
    {
        $price = $this->getPrice();

        if ($this->getParentBeVariant()) {
            $parentPrice = $this->getParentBeVariant()->getPrice();
        } elseif ($this->getProduct()) {
            $parentPrice = $this->getProduct()->getBestPrice();
        } else {
            $parentPrice = 0;
        }

        switch ($this->priceCalcMethod) {
            case 2:
                $discount = -1 * (($price / 100) * ($parentPrice));
                break;
            case 4:
                $discount = ($price / 100) * ($parentPrice);
                break;
            default:
                $discount = 0;
        }

        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart']['changeVariantDiscount']) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart']['changeVariantDiscount'] as $funcRef) {
                if ($funcRef) {
                    $params = array(
                        'price_calc_method' => $this->priceCalcMethod,
                        'price' => &$price,
                        'parent_price' => &$parentPrice,
                        'discount' => &$discount,
                    );

                    GeneralUtility::callUserFunction($funcRef, $params, $this);
                }
            }
        }

        return $discount;
    }

    /**
     * Gets Price Calculated
     *
     * @return float
     */
    public function getPriceCalculated()
    {
        $price = $this->getPrice();

        if ($this->getParentBeVariant()) {
            $parentPrice = $this->getParentBeVariant()->getPrice();
        } elseif ($this->getProduct()) {
            $parentPrice = $this->getProduct()->getBestPrice();
        } else {
            $parentPrice = 0;
        }

        switch ($this->priceCalcMethod) {
            case 3:
                $discount = -1 * (($price / 100) * ($parentPrice));
                break;
            case 5:
                $discount = ($price / 100) * ($parentPrice);
                break;
            default:
                $discount = 0;
        }

        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart']['changeVariantDiscount']) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart']['changeVariantDiscount'] as $funcRef) {
                if ($funcRef) {
                    $params = array(
                        'price_calc_method' => $this->priceCalcMethod,
                        'price' => &$price,
                        'parent_price' => &$parentPrice,
                        'discount' => &$discount,
                    );

                    GeneralUtility::callUserFunction($funcRef, $params, $this);
                }
            }
        }

        switch ($this->priceCalcMethod) {
            case 1:
                $parentPrice = 0.0;
                break;
            case 2:
                $price = -1 * $price;
                break;
            case 4:
                break;
            default:
                $price = 0.0;
        }

        return $parentPrice + $price + $discount;
    }

    /**
     * Gets Parent Price
     *
     * @return float
     */
    public function getParentPrice()
    {
        if ($this->priceCalcMethod == 1) {
            return 0.0;
        }

        if ($this->getParentBeVariant()) {
            return $this->getParentBeVariant()->getPrice();
        } elseif ($this->getProduct()) {
            return $this->getProduct()->getPrice();
        }

        return 0.0;
    }

    /**
     * Sets Price
     *
     * @param $price
     */
    public function setPrice($price)
    {
        $this->price = $price;

        $this->reCalc();
    }

    /**
     * Gets Price Calc Method
     *
     * @return int
     */
    public function getPriceCalcMethod()
    {
        return $this->priceCalcMethod;
    }

    /**
     * Sets Price Calc Method
     *
     * @param $priceCalcMethod
     *
     * @return void
     */
    public function setPriceCalcMethod($priceCalcMethod)
    {
        $this->priceCalcMethod = $priceCalcMethod;
    }

    /**
     * Returns the SKU Delimiter
     *
     * @return string
     */
    public function getSkuDelimiter()
    {
        return $this->skuDelimiter;
    }

    /**
     * Sets the SKU Delimiter
     *
     * @param string $skuDelimiter
     * @return void
     */
    public function setSkuDelimiter($skuDelimiter)
    {
        $this->skuDelimiter = $skuDelimiter;
    }

    /**
     * Gets Sku
     *
     * @return string
     */
    public function getSku()
    {
        $sku = '';

        if ($this->getParentBeVariant()) {
            $sku = $this->getParentBeVariant()->getSku();
        } elseif ($this->getProduct()) {
            $sku = $this->getProduct()->getSku();
        }

        if ($this->isFeVariant) {
            $sku .= $this->skuDelimiter . $this->id;
        } else {
            $sku .= $this->skuDelimiter . $this->sku;
        }


        return $sku;
    }

    /**
     * Sets Sku
     *
     * @param $sku
     *
     * @retrun void
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
    }

    /**
     * Gets Has Fe Variants
     *
     * @return int
     */
    public function getHasFeVariants()
    {
        return $this->hasFeVariants;
    }

    /**
     * Sets Has Fe Variants
     *
     * @param $hasFeVariants
     *
     * @return void
     */
    public function setHasFeVariants($hasFeVariants)
    {
        $this->hasFeVariants = $hasFeVariants;
    }

    /**
     * Gets Is Fe Variant
     *
     * @return bool
     */
    public function getIsFeVariant()
    {
        return $this->isFeVariant;
    }

    /**
     * Sets Is Fe Variant
     *
     * @param bool $isFeVariant
     */
    public function setIsFeVariant($isFeVariant)
    {
        $this->isFeVariant = $isFeVariant;
    }

    /**
     * Gets Quantity
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Gets Gross
     *
     * @return float
     */
    public function getGross()
    {
        $this->calcGross();
        return $this->gross;
    }

    /**
     * Gets Net
     *
     * @return float
     */
    public function getNet()
    {
        $this->calcNet();
        return $this->net;
    }

    /**
     * Gets Tax
     *
     * @return float
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * Gets TaxClass
     *
     * @return \Extcode\Cart\Domain\Model\Cart\TaxClass
     */
    public function getTaxClass()
    {
        if ($this->getParentBeVariant()) {
            $taxClass = $this->getParentBeVariant()->getTaxClass();
        } elseif ($this->getProduct()) {
            $taxClass = $this->getProduct()->getTaxClass();
        }
        return $taxClass;
    }

    /**
     * Sets Quantity
     *
     * @param $newQuantity
     *
     * @return void
     */
    public function setQuantity($newQuantity)
    {
        $this->quantity = $newQuantity;

        $this->reCalc();
    }

    /**
     * @param $newQuantity
     */
    public function changeQuantity($newQuantity)
    {
        $this->quantity = $newQuantity;

        if ($this->beVariants) {
            foreach ($this->beVariants as $beVariant) {
                /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
                $beVariant->changeQuantity($newQuantity);
            }
        }

        $this->reCalc();
    }

    /**
     * @param $variantQuantityArray
     * @internal param $id
     * @internal param $newQuantity
     */
    public function changeVariantsQuantity($variantQuantityArray)
    {
        foreach ($variantQuantityArray as $beVariantId => $quantity) {
            /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
            $beVariant = $this->beVariants[$beVariantId];

            if (is_array($quantity)) {
                $beVariant->changeVariantsQuantity($quantity);
                $this->reCalc();
            } else {
                $beVariant->changeQuantity($quantity);
                $this->reCalc();
            }
        }
    }

    /**
     * @param array $newVariants
     * @return mixed
     */
    public function addBeVariants($newVariants)
    {
        foreach ($newVariants as $newVariant) {
            $this->addBeVariant($newVariant);
        }
    }

    /**
     * @param \Extcode\Cart\Domain\Model\Cart\BeVariant $newBeVariant
     * @return mixed
     */
    public function addBeVariant(\Extcode\Cart\Domain\Model\Cart\BeVariant $newBeVariant)
    {
        $newBeVariantId = $newBeVariant->getId();

        /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
        $beVariant = $this->beVariants[$newBeVariantId];

        if ($beVariant) {
            if ($beVariant->getBeVariants()) {
                $beVariant->addBeVariants($newBeVariant->getBeVariants());
            } else {
                $newQuantity = $beVariant->getQuantity() + $newBeVariant->getQuantity();
                $beVariant->setQuantity($newQuantity);
            }
        } else {
            $this->beVariants[$newBeVariantId] = $newBeVariant;
        }

        $this->reCalc();
    }

    /**
     * @return array
     */
    public function getBeVariants()
    {
        return $this->beVariants;
    }

    /**
     * @param $beVariantId
     *
     * @return \Extcode\Cart\Domain\Model\Cart\BeVariant
     */
    public function getBeVariantById($beVariantId)
    {
        return $this->beVariants[$beVariantId];
    }

    /**
     * @param $beVariantId
     *
     * @return \Extcode\Cart\Domain\Model\Cart\BeVariant
     */
    public function getBeVariant($beVariantId)
    {
        return $this->getBeVariantById($beVariantId);
    }

    /**
     * @param $beVariantsArray
     * @return bool|int
     */
    public function removeBeVariants($beVariantsArray)
    {
        foreach ($beVariantsArray as $beVariantId => $value) {
            /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
            $beVariant = $this->beVariants[$beVariantId];
            if ($beVariant) {
                if (is_array($value)) {
                    $beVariant->removeBeVariants($value);

                    if (!$beVariant->getBeVariants()) {
                        unset($this->beVariants[$beVariantId]);
                    }

                    $this->reCalc();
                } else {
                    unset($this->beVariants[$beVariantId]);

                    $this->reCalc();
                }
            } else {
                return -1;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    private function calcGross()
    {
        if ($this->getIsNetPrice() == false) {
            if ($this->beVariants) {
                $sum = 0.0;
                foreach ($this->beVariants as $beVariant) {
                    /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
                    $sum += $beVariant->getGross();
                }
                $this->gross = $sum;
            } else {
                $this->gross = $this->getPriceCalculated() * $this->quantity;
            }
        } else {
            $this->calcNet();
            $this->calcTax();
            $this->gross = $this->net + $this->tax;
        }
    }

    /**
     * @return void
     */
    private function calcTax()
    {
        if ($this->getIsNetPrice() == false) {
            $this->calcGross();
            $this->tax = ($this->gross / (1 + $this->getTaxClass()->getCalc())) * ($this->getTaxClass()->getCalc());
        } else {
            $this->calcNet();
            $this->tax = ($this->net * $this->getTaxClass()->getCalc());
        }
    }

    /**
     * @return void
     */
    private function calcNet()
    {
        if ($this->getIsNetPrice() == true) {
            if ($this->beVariants) {
                $sum = 0.0;
                foreach ($this->beVariants as $beVariant) {
                    /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
                    $sum += $beVariant->getNet();
                }
                $this->net = $sum;
            } else {
                $this->net = $this->getPriceCalculated() * $this->quantity;
            }
        } else {
            $this->calcGross();
            $this->calcTax();
            $this->net = $this->gross - $this->tax;
        }
    }

    /**
     * @return void
     */
    private function reCalc()
    {
        if ($this->beVariants) {
            $quantity = 0;
            foreach ($this->beVariants as $beVariant) {
                /** @var \Extcode\Cart\Domain\Model\Cart\BeVariant $beVariant */
                $quantity += $beVariant->getQuantity();
            }

            if ($this->quantity != $quantity) {
                $this->quantity = $quantity;
            }
        }

        if ($this->getIsNetPrice() == false) {
            $this->calcGross();
            $this->calcTax();
            $this->calcNet();
        } else {
            $this->calcNet();
            $this->calcTax();
            $this->calcGross();
        }
    }

    /**
     * @return array
     */
    public function getAdditionalArray()
    {
        return $this->additional;
    }

    /**
     * @param $additional
     * @return void
     */
    public function setAdditionalArray($additional)
    {
        $this->additional = $additional;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getAdditional($key)
    {
        return $this->additional[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAdditional($key, $value)
    {
        $this->additional[$key] = $value;
    }

    /**
     * @return int
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * @param int $min
     *
     * @return void
     */
    public function setMin($min)
    {
        if ($min < 0 || $min > $this->max) {
            throw new \InvalidArgumentException;
        }

        $this->min = $min;
    }

    /**
     * @return int
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * @param int $max
     *
     * @return void
     */
    public function setMax($max)
    {
        if ($max < 0 || $max < $this->min) {
            throw new \InvalidArgumentException;
        }

        $this->max = $max;
    }
}
