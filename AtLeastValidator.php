<?php

namespace codeonyii\yii2validators;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\VarDumper;
use yii\i18n\PhpMessageSource;
use yii\validators\Validator;

/**
 * Checks if one or more in a list of attributes are filled.
 *
 * In the following example, the `attr1` and `attr2` attributes will
 * be verified. If none of them are filled `attr1` will receive an error:
 *
 * ~~~[php]
 *      // in rules()
 *      return [
 *          ['attr1', AtLeastValidator::className(), 'in' => ['attr1', 'attr2']],
 *      ];
 * ~~~
 *
 * In the following example, the `attr1`, `attr2` and `attr3` attributes will
 * be verified. If at least 2 (`min`) of them are not filled, `attr1` will
 * receive an error:
 *
 * ~~~[php]
 *      // in rules()
 *      return [
 *          ['attr1', AtLeastValidator::className(), 'min' => 2, 'in' => ['attr1', 'attr2', 'attr3']],
 *      ];
 * ~~~
 *
 * If you want to show errors in a summary instead in the own attributes, you can do this:
 * ~~~[php]
 *      // in rules()
 *      return [
 *          ['!id', AtLeastValidator::className(), 'in' => ['attr1', 'attr2', 'attr3']], // where `id` is the pk
 *      ];
 *
 *      // view:
 *      ...
 *      echo yii\helpers\Html::errorSummary($model, ['class' => ['text-danger']]);
 *      // OR, to show only `id` errors:
 *      echo yii\helpers\Html::error($model, 'id', ['class' => ['text-danger']]); 
 * ~~~
 *
 *
 * @author Sidney Lins <slinstj@gmail.com>
 */
class AtLeastValidator extends Validator
{
    const DIRECTION_ATLEAST = 'atleast';
    const DIRECTION_ATMOST = 'atmost';

    /**
     * @var integer the minimun required quantity of attributes that must to be filled.
     * Defaults to 1.
     */
    public $min = 1;

    /**
     * @var string|array the list of attributes that should receive the error message. Required.
     */
    public $in;

    /**
     * @inheritdoc
     */
    public $skipOnEmpty = false;

    /**
     * @inheritdoc
     */
    public $skipOnError = false;

    /**
     * @var string
     */
    public $direction = self::DIRECTION_ATLEAST;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->in === null) {
            throw new InvalidConfigException('The `in` parameter is required.');
        } elseif (! is_array($this->in) && count(preg_split('/\s*,\s*/', $this->in, -1, PREG_SPLIT_NO_EMPTY)) <= 1) {
            throw new InvalidConfigException('The `in` parameter must have at least 2 attributes.');
        }
		if (!isset(Yii::$app->get('i18n')->translations['message*'])) {
			Yii::$app->get('i18n')->translations['message*'] = [
				'class' => PhpMessageSource::className(),
				'basePath' => __DIR__ . '/messages',
				'sourceLanguage' => 'en-US'
			];
		}
        if ($this->message === null) {
            $this->message = Yii::t('messages', 'You must fill {direction} {min} of the attributes {attributes}.', [
                'direction' => $this->direction === self::DIRECTION_ATLEAST ?
                    Yii::t('messages', 'at least') : Yii::t('messages', 'at most'),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $attributes = is_array($this->in) ? $this->in : preg_split('/\s*,\s*/', $this->in, -1, PREG_SPLIT_NO_EMPTY);
        $chosen = 0;

        foreach ($attributes as $attributeName) {
            $value = $model->$attributeName;
            $attributesListLabels[] = '"' . $model->getAttributeLabel($attributeName). '"';
            $chosen += !empty($value) ? 1 : 0;
        }

        $condition = $this->direction === self::DIRECTION_ATLEAST ? ($chosen < $this->min) : ($chosen > $this->min);
        if (!$chosen || $condition) {
            $attributesList = implode(', ', $attributesListLabels);
            $message = strtr($this->message, [
                '{min}' => $this->min,
                '{attributes}' => $attributesList,
            ]);
            $model->addError($attribute, $message);
        }
    }

    /**
     * @inheritdoc
     * @since: 1.1
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        $attributes = is_array($this->in) ? $this->in : preg_split('/\s*,\s*/', $this->in, -1, PREG_SPLIT_NO_EMPTY);
        $attributes = array_map('strtolower',$attributes); // yii lowercases attributes
        $attributesJson = json_encode($attributes);

        $attributesLabels = [];
        foreach ($attributes as $attr) {
            $attributesLabels[] = '"' . addcslashes($model->getAttributeLabel($attr), "'") . '"';
        }
        $message = strtr($this->message, [
            '{min}' => $this->min,
            '{attributes}' => implode(Yii::t('messages', ' or '), $attributesLabels),
        ]);

        $form = $model->formName();

        return <<<JS
            function atLeastValidator() {
                var atributes = $attributesJson;
                var formName = '$form';
                var chosen = 0; 
                $.each(atributes, function(key, attr){
                    var obj = $('#' + formName.toLowerCase() + '-' + attr);
                    if(obj.length == 0){
                        obj = $("[name=\""+formName + '[' + attr + ']'+"\"]");
                    }

                    var val = obj.val();
                    chosen += val ? 1 : 0;
                });
                if (!chosen || chosen < $this->min) {
                    messages.push('$message');
                } else {
                    $.each(atributes, function(key, attr){
                        var attrId = formName.toLowerCase() + '-' + attr;
                        if($('#' + attrId).length == 0){
                            attrId = $("[name=\""+formName + '[' + attr + ']'+"\"]").attr('id');
                        }

                        \$form.yiiActiveForm('updateAttribute', attrId, '');
                    });
                }
            }
            atLeastValidator();
JS;
    }
}

