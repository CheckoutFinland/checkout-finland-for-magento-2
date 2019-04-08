<?php

namespace Op\Checkout\Block\Redirect;

class Checkout extends \Magento\Framework\View\Element\AbstractBlock
{
    protected $form;
    protected $params;
    protected $url;
    protected $formId = 'checkout_form';

    public function __construct(
        \Magento\Framework\Data\Form $form,
        \Magento\Framework\View\Element\Context $context,
        array $data = []
    ) {
        $this->form = $form;
        parent::__construct($context, $data);
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    protected function _toHtml()
    {
        $this->form->setAction($this->url)
            ->setId($this->formId)
            ->setName($this->formId)
            ->setMethod('POST')
            ->setUseContainer(true);

        foreach ($this->params as $key => $value) {
            $this->form->addField($key, 'text', [
                'name' => $key,
                'value' => $value,
            ]);
        }

        return $this->form->toHtml() . $this->_jsSubmit();
    }

    protected function _jsSubmit()
    {
        return '<script type="text/javascript">document.getElementById("' . $this->formId . '").submit();</script>';
    }
}
