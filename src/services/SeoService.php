<?php
/**
 * SEO for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\seo\services;

use Craft;
use craft\base\Component;
use craft\web\View;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use ether\seo\models\SeoData;
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class SeoService
 *
 * @author  Ether Creative
 * @package ether\seo\services
 */
class SeoService extends Component
{

	const DOM_CONFIG =
		LIBXML_HTML_NOIMPLIED |
		LIBXML_HTML_NODEFDTD |
		LIBXML_NOBLANKS |
		LIBXML_COMPACT;

	/**
	 * Will get the SEO field data from the given Twig context and field handle
	 *
	 * @param        $context
	 * @param string $handle
	 *
	 * @return void
	 */
	public function getSeoVariable (&$context, $handle)
	{
		if (isset($context['__seo']))
		{
			$context['seo'] = $context['__seo'];
			return;
		}

		$seo = null;

		try {
			if (isset($context[$handle]))
			{
				$seo = $context[$handle];
			}
			else
			{
				$handles = [
					'entry',
					'product',
					'category',
				];

				// TODO: Event to allow plugins to add their own top level element handles

				foreach ($handles as $elemHandle)
				{
					if (
						isset($context[$elemHandle]) &&
					    isset($context[$elemHandle][$handle])
					) {
						$seo = $context[$elemHandle][$handle];
						break;
					}
				}
			}
		} catch (Exception $e) {}

		if (!($seo instanceof SeoData))
			$seo = new SeoData();

		$context['__seo'] = $seo;
		$context['seo'] = $seo;
	}

	/**
	 * @param        $isParent
	 * @param        $context
	 * @param array  $data
	 * @param string $markup
	 *
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws \yii\base\Exception
	 */
	public function output ($isParent, &$context, $data = [], $markup = '')
	{
		/** @var SeoData $seo */
		$seo = $context['__seo'];

		if (!empty($data))
			$seo->overrideData($data);

		if (!empty($markup))
			$seo->overrideMarkup($markup);

		if ($isParent)
			$this->render($seo);
	}

	/**
	 * @param SeoData $seo
	 *
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 * @throws \yii\base\Exception
	 */
	public function render (SeoData $seo)
	{
		$seo->mergeDataOverrides();

		$view = Craft::$app->getView();

		$oldTemplate = $view->getTemplateMode();
		$view->setTemplateMode(View::TEMPLATE_MODE_CP);

		echo $this->_merge(
			$view->renderTemplate('seo/_meta', compact('seo')),
			$seo->getMarkupOverrides()
		);

		$view->setTemplateMode($oldTemplate);
	}

	// Helpers
	// =========================================================================

	/**
	 * @param       $markup
	 * @param array $overrides
	 *
	 * @return string
	 */
	private function _merge ($markup, $overrides = [])
	{
		$dom = $this->_getDom($markup);
		$dom->preserveWhiteSpace = false;

		foreach ($overrides as $override)
		{
			$oDom = $this->_getDom($override);

			/** @var DOMNode $node */
			foreach ($this->_nodes($oDom) as $node)
				$this->_replaceNode($dom, $node);
		}

		$html = '';

		foreach ($this->_nodes($dom) as $node)
			$html .= $dom->saveHTML($node);

		return $html;
	}

	/**
	 * @param DOMDocument $dom
	 * @param DOMNode     $replacement
	 */
	private function _replaceNode (DOMDocument $dom, DOMNode $replacement)
	{
		$replacement = $dom->importNode($replacement, true);

		if (!$replacement)
			return;

		$tag = $replacement->nodeName;
		$attributes = [];

		switch ($tag)
		{
			case 'meta':
				$attributes = ['name', 'property'];
				break;
			case 'link':
				$attributes = ['rel'];
				break;
			case 'title':
				$attributes = null;
		}

		if ($attributes === null)
		{
			$originals = $dom->getElementsByTagName($tag);

			if ($originals === false || $originals->count() > 1)
				$dom->appendChild($replacement);
			else
				$this->_replace($originals->item(0), $replacement);

			return;
		}

		$x = new DOMXPath($dom);
		$query = '//' . $tag;

		foreach ($attributes as $attribute)
			if ($attr = $replacement->attributes->getNamedItem($attribute))
				if ($value = $attr->nodeValue)
					$query .= '[@' . $attribute . '="' . $value . '"]';

		$nodes = $x->query($query);

		if ($nodes === false || $nodes->count() > 1)
			$dom->appendChild($replacement);
		else
			$this->_replace($nodes->item(0), $replacement);
	}

	/**
	 * @param $markup
	 *
	 * @return DOMDocument
	 */
	private function _getDom ($markup)
	{
		$dom = new DOMDocument();
		$dom->formatOutput = true;
		$dom->loadHTML('<html><head>' . $markup . '</head></html>', self::DOM_CONFIG);

		return $dom;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return DOMNodeList
	 */
	private function _nodes (DOMDocument $dom)
	{
		return $dom->getElementsByTagName('head')->item(0)->childNodes;
	}

	/**
	 * @param DOMNode $target
	 * @param DOMNode $replacement
	 */
	private function _replace (DOMNode $target, DOMNode $replacement)
	{
		$target->parentNode->replaceChild($replacement, $target);
	}

}