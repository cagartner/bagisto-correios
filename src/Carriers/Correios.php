<?php

namespace Cagartner\Correios\Carriers;

use Cagartner\Correios\Helpers\Consult;
use Config;
use Illuminate\Support\Collection;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Checkout\Facades\Cart;
use Webkul\Shipping\Carriers\AbstractShipping;

/**
 * Correios Shipping Methods.
 *
 * @author Carlos Gartner <contato@carlosgartner.com.br>
 */
class Correios extends AbstractShipping
{
    /**
     * Correios methods map
     */
    const METHODS_TITLE = [
        'sedex' => 'Sedex',
        'sedex_a_cobrar' => 'Sedex a Cobrar',
        'sedex_10' => 'Sedex 10',
        'sedex_hoje' => 'Sedex Hoje',
        'pac' => 'PAC',
        'pac_contrato' => 'PAC',
        'sedex_contrato' => 'Sedex',
        'esedex' => 'e-Sedex',
    ];

    /**
     * Payment method code
     *
     * @var string
     */
    protected $code = 'cagartner_correios';

    /**
     * Returns rate for correios
     *
     * @return array
     */
    public function calculate()
    {
        if (!$this->isAvailable())
            return false;

        /** @var \Webkul\Checkout\Models\Cart $cart */
        $cart = Cart::getCart();
        $total_weight = $cart->items->sum('total_weight');

        $data = [
            'tipo' => $this->getConfigData('methods'),
            'formato' => $this->getConfigData('package_type'), // opções: `caixa`, `rolo`, `envelope`
            'cep_destino' => $cart->shipping_address->postcode,
            'cep_origem' => core()->getConfigData('sales.shipping.origin.zipcode'),
            'peso' => $total_weight, // Peso em kilos
            'comprimento' => $this->getConfigData('package_length'), // Em centímetros
            'altura' => $this->getConfigData('package_height'), // Em centímetros
            'largura' => $this->getConfigData('package_width'), // Em centímetros
            'diametro' => $this->getConfigData('roll_diameter'), // Em centímetros, no caso de rolo
        ];

        if ($this->getConfigData('cod_company') && $this->getConfigData('password')) {
            $data['empresa'] = $this->getConfigData('cod_company');
            $data['senha'] = $this->getConfigData('password');
        }

        $consult = new Consult();
        /** @var Collection $result */
        $result = $consult->carriers($data);
        $rates = [];
        $tax_handling = (int)core()->convertPrice($this->getConfigData('tax_handling')) ?: 0;

        foreach ($result as $item) {
            $object = new CartShippingRate;
            $object->carrier = 'correios';
            $object->carrier_title = $this->getConfigData('title');
            $object->method = 'cagartner_correios_' . Consult::getTipoIndex($item['codigo']);
            $object->method_title = $this->getMethodTitle($item['codigo']);
            $object->method_description = $this->getMethodDescription($item['prazo']);
            $object->price = core()->convertPrice($item['valor']) + $tax_handling;
            $object->base_price = core()->convertPrice($item['valor']) + $tax_handling;
            array_push($rates, $object);
        }
        return $rates;
    }

    /**
     * @param $code
     * @return mixed
     */
    protected function getMethodTitle($code)
    {
        $method = Consult::getTipoIndex($code);
        return self::METHODS_TITLE[$method];
    }

    /**
     * @param $deadline
     * @return mixed
     */
    protected function getMethodDescription($deadline)
    {
        $template = $this->getConfigData('method_template');
        $extra_time = (int)core()->convertPrice($this->getConfigData('extra_time')) ?: 0;
        if (strpos($template, ':dia')) {
            $template = str_replace(':dia', ($deadline + $extra_time), $template);
        }
        return $template;
    }
}