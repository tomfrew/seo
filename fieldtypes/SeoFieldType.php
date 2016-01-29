<?php

namespace Craft;

class SeoFieldType extends BaseFieldType {

	public function getName()
	{
		return Craft::t('SEO');
	}

	public function defineContentAttribute()
	{
		return AttributeType::Mixed;
	}

	public function getInputHtml($name, $value)
	{
		$id = craft()->templates->formatInputId($name);
		$namespaceId = craft()->templates->namespaceInputId($id);

		$settings = craft()->plugins->getPlugin('seo')->getSettings();

		craft()->templates->includeCssResource('seo/css/seo.css');
		craft()->templates->includeJsResource('seo/js/seo-field.js');
		craft()->templates->includeJs("new SeoField('{$namespaceId}');");

		return craft()->templates->render('seo/seo-fieldtype', array(
			'id' => $id,
			'name' => $name,
			'value' => $value,
			'settings' => $settings,
			'isEntry' => $this->element->elementType === 'Entry',
			'isNew' => $this->element->title === null,
			'ref' => $this->element->getRef()
		));
	}

}