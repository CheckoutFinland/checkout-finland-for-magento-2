<?php

namespace Op\Checkout\Block\Redirect;

/**
 * Class Checkout
 */
class Checkout extends \Magento\Framework\View\Element\AbstractBlock
{
    protected $form;
    protected $params;
    protected $url;
    protected $formId = 'checkout_form';

    /**
     * Checkout constructor.
     * @param \Magento\Framework\Data\Form $form
     * @param \Magento\Framework\View\Element\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Data\Form $form,
        \Magento\Framework\View\Element\Context $context,
        array $data = []
    ) {
        $this->form = $form;
        parent::__construct($context, $data);
    }

    /**
     * @param $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return string
     */
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

    /**
     * @return string
     */
    protected function _jsSubmit()
    {
        return '<script type="text/javascript">document.getElementById("' . $this->formId . '").submit();</script>';
    }
}
