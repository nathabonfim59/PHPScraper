<?php

// Namespace
namespace spekulatius\apis;

use spekulatius\core;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Product variation
 */
class mp_variation
{
    /**
     * The varition name
     *
     * Example: 'color'
     *
     * @var string $name
     */
    public $name;

    /**
     * The variation options
     *
     * Example: ['red', 'blue']
     *
     * @var array $options
     */
    public $options;
}

/**
 * Product image and metadata
 */
class mp_image
{
    /** @var string $src */
    public $src;
    /** @var string $altText */
    public $altText;
    /** @var int $width */
    public $width;
    /** @var int $height */
    public $height;
}

/**
 * A product from Mercado Libre
 */
class mp_product {
    /**
     * The product identifier
     *
     * @var int;
     */
    public $id;

    /**
     * The path which the product is located in
     *
     * @var array $breadcrumbs
     */
    public $breadcrumbs;

    /**
     * The product URL
     *
     * @var string $url
     */
    public $url;

    /**
     * The product name
     *
     * @var string $name
     */
    public $name;

    /**
     * The product description (may include HTML)
     *
     * @var string $description
     */
    public $description;

    /**
     * The product priceo
     *
     * @var float $price
     */
    public $price;

    /**
     * The product avarage rating
     *
     * @var float $rating
     */
    public $rating;

    /**
     * The product sizes
     *
     * Syntax: ['name' => size]
     *
     * @var array $sizes
     */
    public $sizes;

    /**
     * The product variations
     *
     * Example:
     * ```php
     * $variations = [
     *     'color' => mp_variation[]
     * ];
     * ```
     *
     * @var ApisMp_variation[] $variations
     */
    public $variations;

    /**
     * A list of the image URLs of the product
     * (does not support videos)
     *
     * @var string[] $images
     */
    public $images;

    /**
     * Additional information that are not variations
     *
     * Ex: product specifications (memory, camera..)
     *
     * ```php
     * $specs = [
     *     'Megapixels' => '5MP'
     * ];
     * ```
     *
     * @var array $specs
     */
    public $specs;
}

class mercado_libre
{
    protected $core = null;

    public function __construct(core &$core)
    {
        $this->core = $core;
    }

    /**
     * Get all information about a product
     *
     * @param string $url
     * @return mp_product
     */
    public function getProduct(): mp_product
    {
        $product = new mp_product();

        $product->id = $this->productId();
        $product->url = $this->core->currentURL();
        $product->breadcrumbs = $this->breadcrumbs();
        $product->name = $this->productName();
        $product->price = $this->productPrice();
        $product->rating = $this->productRating();
        $product->description = $this->productDescription();
        $product->images = $this->productImages();
        $product->variations = $this->productVariations();
        $product->specs = $this->productSpecs();

        return $product;
    }

    /**
     * Get the product Id
     *
     * @return string|null
     */
    public function productId()
    {
        $id = $this->core
            ->filter('//input[@name="item_id"]')
            ->attr('value');

        return $id ?? null;
    }

    /**
     * Get the product name
     *
     * @return string|null
     */
    public function productName()
    {
        return $this->core->filterFirstText('//h1[@class="ui-pdp-title"]');
    }

    /**
     * Get the product price
     *
     * @return float|null
     */
    public function productPrice()
    {
        $priceInteger = $this->core->filterFirstText('//span[@class="andes-money-amount__fraction"]');
        $priceCents = $this->core->filterFirstText('//span[@class="andes-money-amount__cents"]');

        $price = $priceInteger . '.' . $priceCents;

        return (float)$price ?? null;
    }


    /**
     * Get the product avarage rating
     *
     * @return float|null
     */
    public function productRating()
    {
        $rating = $this->core->filterFirstText(
            '//p[contains(@class, "ui-review-capability__rating__average")]'
        );

        return (float)$rating ?? null;
    }

    /**
     * Get the product description in text or html
     *
     * @param bool $html Returns the content as HTML
     *
     * @return string|null;
     */
    public function productDescription(bool $html = false)
    {
        $description = $this->core->filter(
            '//p[@class="ui-pdp-description__content"]'
        );

        if (!$description) return null;

        return $html ? $description->html() : $description->text();
    }

    public function productImages()
    {
        $images = [];
        $imageGalery = $this->core->filter(
            '//img[contains(@class,"ui-pdp-gallery__figure__image") and contains(@class, "ui-pdp-image")]'
        );

        foreach ($imageGalery as $imageNode) {
            $imageElement = new Crawler($imageNode);
            $image = new mp_image();

            $image->src = $imageElement->attr('data-zoom');
            $image->width = $imageElement->attr('width');
            $image->height = $imageElement->attr('height');
            $image->altText = $imageElement->attr('alt');

            $images[] = $image;
        }

        return $images;
    }

    /**
     * Get product variations
     *
     * @return mp_variation[]
     */
    public function productVariations()
    {
        $variations = [];
        $variationSelectors = $this->core->filter(
            '//div[@class="ui-pdp-variations__picker"]'
        );

        foreach ($variationSelectors as $variationSelectorNode) {
            $variation = new mp_variation();
            $variationSeletor = new Crawler($variationSelectorNode);

            $variation->name = $variationSeletor
                ->filterXPath('//p[contains(@class, "ui-pdp-variations__label")]/text()')
                ->text();
            $variation->name = rtrim($variation->name, ':');

            $options = $variationSeletor->filterXPath('//a[contains(@class, "ui-pdp-thumbnail")]');

            foreach ($options as $optionNode) {
                $option = new Crawler($optionNode);

                $variation->options[] = $option->attr('title');
            }

            $variations[] = $variation;
        }

        return $variations;
    }

    /**
     * Get the current page breadcrumbs
     *
     * @return string|null
     */
    public function breadcrumbs()
    {
        return $this->core->filterTexts('//a[@class="andes-breadcrumb__link"]') ?? null;
    }

    /**
     * Get all the content from the specs tables
     *
     * @return array|null
     */
    public function productSpecs()
    {
        $specs = [];
        $specsTables = $this->core->filter('//table[@class="andes-table"]/tbody');

        foreach ($specsTables as $tableNode) {
            $table = new Crawler($tableNode);

            foreach ($table->children() as $rowNode) {
                $row = new Crawler($rowNode);

                $specName = $row->filterXPath('//text()')->text();
                $specValue = $row->filterXPath('//span')->text();

                $specs[] = [
                    'name' => $specName,
                    'value' => $specValue,
                ];
            }
        }

        return $specs ?? null;
    }
}
