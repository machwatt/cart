<?php

namespace Extcode\Cart\Controller\Cart;

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
use Extcode\Cart\Hooks\CartProductHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Cart Product Controller
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class ProductController extends ActionController
{
    /**
     * Stock Utility
     *
     * @var \Extcode\Cart\Utility\StockUtility
     */
    protected $stockUtility;

    /**
     * GpValues
     *
     * @var array
     */
    protected $gpValues = [];

    /**
     * TaxClasses
     *
     * @var array
     */
    protected $taxClasses = [];

    /**
     * @param \Extcode\Cart\Utility\StockUtility $stockUtility
     */
    public function injectStockUtility(
        \Extcode\Cart\Utility\StockUtility $stockUtility
    ) {
        $this->stockUtility = $stockUtility;
    }

    /**
     * Action Add
     *
     * @return string
     */
    public function addAction()
    {
        if (!$this->request->hasArgument('productType')) {
            // TODO: add own Exception
            throw new \Exception('productType is needed');
        }

        $productType = $this->request->getArgument('productType');

        $this->cart = $this->cartUtility->getCartFromSession($this->pluginSettings);

        if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart'][$productType])) {
            // TODO: throw own exception
            throw new \Exception('Hook is not configured for this product type!');
        }

        $className = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cart'][$productType];

        $hookObject = GeneralUtility::makeInstance($className);
        if (!$hookObject instanceof CartProductHookInterface) {
            throw new \UnexpectedValueException($className . ' must implement interface ' . CartProductHookInterface::class, 123);
        }

        list($errors, $cartProducts) = $hookObject->getProductFromRequest(
            $this->request,
            $this->cart
        );

        $errors = [];

        foreach ($cartProducts as $cartProductKey => $cartProduct) {
            $availabilityResponse = $this->stockUtility->checkAvailability(
                $this->request,
                $cartProduct,
                $this->cart,
                'add'
            );

            if (!$availabilityResponse->isAvailable()) {
                $errors = array_merge($errors, $availabilityResponse->getMessages());
            }
        }

        if (!empty($errors)) {
            $messageBody = '';
            $messageTitle = '';
            $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK;

            foreach ($errors as $error) {
                if ($error->getSeverity() >= $severity) {
                    $severity = $error->getSeverity();
                    $messageBody = $error->getMessage();
                    $messageTitle = $error->getTitle();
                }
            }

            if (isset($_GET['type'])) {
                $response = [
                    'status' => '200',
                    'count' => $this->cart->getCount(),
                    'net' => $this->cart->getNet(),
                    'gross' => $this->cart->getGross(),
                    'messageBody' => $messageBody,
                    'messageTitle' => $messageTitle,
                    'severity' => $severity
                ];

                return json_encode($response);
            } else {
                $this->addFlashMessage(
                    $messageBody,
                    $messageTitle,
                    $severity,
                    true
                );

                $this->redirect('show', 'Cart\Cart');
            }
        }

        $quantity = $this->addProductsToCart($cartProducts);

        $this->updateService();

        $this->sessionHandler->write($this->cart, $this->settings['cart']['pid']);

        if (isset($_GET['type'])) {
            // TODO: add json response

            return;
        } else {
            // TODO: add flash message response and redirect
        }

        $this->redirect('show', 'Cart\Cart');
    }

    /**
     * Action remove
     */
    public function removeAction()
    {
        if ($this->request->hasArgument('product')) {
            $this->cart = $this->sessionHandler->restore($this->settings['cart']['pid']);
            $this->cart->removeProductById($this->request->getArgument('product'));

            $this->updateService();

            $this->sessionHandler->write($this->cart, $this->settings['cart']['pid']);
        }
        $this->redirect('show', 'Cart\Cart');
    }

    /**
     * returns list of changed products
     *
     * @param $products
     *
     * @return array
     */
    protected function getChangedProducts($products)
    {
        $productsChanged = [];

        foreach ($products as $product) {
            if ($product instanceof \Extcode\Cart\Domain\Model\Cart\Product) {
                $productChanged = $this->cart->getProduct($product->getId());
                $productsChanged[$product->getId()] = $productChanged->toArray();
            }
        }
        return $productsChanged;
    }

    /**
     * @param array $products
     * @return int
     */
    protected function addProductsToCart($products)
    {
        $quantity = 0;

        foreach ($products as $product) {
            if ($product instanceof \Extcode\Cart\Domain\Model\Cart\Product) {
                $quantity += $product->getQuantity();
                $this->cart->addProduct($product);
            }
        }
        return $quantity;
    }
}
