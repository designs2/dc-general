<?php
/**
 * PHP version 5
 *
 * @package    generalDriver
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Subscriber;

use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Date\ParseDateEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetGroupHeaderEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\ViewHelpers;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\View\GroupAndSortingInformationInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;
use ContaoCommunityAlliance\Translator\TranslatorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles the group header formatting.
 *
 * @package ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Subscriber
 */
class GetGroupHeaderSubscriber
{
    /**
     * Handle the subscribed event.
     *
     * @param GetGroupHeaderEvent $event
     */
    public static function handle(GetGroupHeaderEvent $event)
    {
        if ($event->getValue() !== null) {
            return;
        }

        $handler = new static();
        $value   = $handler->formatGroupHeader(
            $event->getEnvironment(),
            $event->getModel(),
            $event->getGroupField(),
            $event->getGroupingMode(),
            $event->getGroupingLength()
        );

        if ($value !== null) {
            $event->setValue($value);
        }
    }

    /**
     * Get the group header.
     *
     * @param EnvironmentInterface $environment    The environment.
     *
     * @param ModelInterface       $model          The model interface.
     *
     * @param string               $field          The grouping field name.
     *
     * @param int                  $groupingMode   The grouping mode.
     *
     * @param int                  $groupingLength The grouping length.
     *
     * @return string
     */
    public function formatGroupHeader($environment, $model, $field, $groupingMode, $groupingLength)
    {
        $property = $environment->getDataDefinition()->getPropertiesDefinition()->getProperty($field);

        // No property? Get out!
        if (!$property) {
            return '-';
        }

        $translator = $environment->getTranslator();
        $value      = $model->getProperty($property->getName());
        $dispatcher = $environment->getEventDispatcher();
        $evaluation = $property->getExtra();

        if ($property->getWidgetType() == 'checkbox' && !$evaluation['multiple']) {
            return $this->formatCheckboxOptionLabel($value, $translator);
        } elseif (false && $property->getForeignKey()) {
            // TODO: refactor foreignKey is yet undefined.
            // if ($objParentModel->hasProperties()) {
            //    $remoteNew = $objParentModel->getProperty('value');
            // }
        } elseif ($groupingMode != GroupAndSortingInformationInterface::GROUP_NONE) {
            return $this->formatByGroupingMode($value, $groupingMode, $groupingLength, $translator, $dispatcher);
        }

        $value = ViewHelpers::getReadableFieldValue($environment, $property, $model);

        if (isset($evaluation['reference'])) {
            $remoteNew = $evaluation['reference'][$value];
        } elseif (array_is_assoc($property->getOptions())) {
            $options   = $property->getOptions();
            $remoteNew = $options[$value];
        } else {
            $remoteNew = $value;
        }

        if (is_array($remoteNew)) {
            $remoteNew = $remoteNew[0];
        }

        if (empty($remoteNew)) {
            $remoteNew = '-';
        }

        return $remoteNew;
    }

    /**
     * Format the grouping header for a checkbox option.
     *
     * @param mixed               $value      The given value.
     * @param TranslatorInterface $translator The translator.
     *
     * @return string
     */
    private function formatCheckboxOptionLabel($value, $translator)
    {
        return ($value != '')
            ? ucfirst($translator->translate('yes', 'MSC'))
            : ucfirst($translator->translate('no', 'MSC'));
    }

    /**
     * Format the group header by the grouping mode.
     *
     * @param mixed                    $value          The given value.
     *
     * @param int                      $groupingMode   The grouping mode.
     *
     * @param int                      $groupingLength The grouping length.
     *
     * @param TranslatorInterface      $translator     The translator.
     *
     * @param EventDispatcherInterface $dispatcher     The event dispatcher.
     *
     * @return string
     */
    private function formatByGroupingMode($value, $groupingMode, $groupingLength, $translator, $dispatcher)
    {
        switch ($groupingMode) {
            case GroupAndSortingInformationInterface::GROUP_CHAR:
                return ($value != '') ? ucfirst(utf8_substr($value, 0, $groupingLength ?: null)) : '-';

            case GroupAndSortingInformationInterface::GROUP_DAY:
                if ($value instanceof \DateTime) {
                    $value = $value->getTimestamp();
                }

                $event = new ParseDateEvent($value, \Config::get('dateFormat'));
                $dispatcher->dispatch(ContaoEvents::DATE_PARSE, $event);

                return ($value != '') ? $event->getResult() : '-';

            case GroupAndSortingInformationInterface::GROUP_MONTH:
                if ($value instanceof \DateTime) {
                    $value = $value->getTimestamp();
                }

                $event = new ParseDateEvent($value, 'F Y');
                $dispatcher->dispatch(ContaoEvents::DATE_PARSE, $event);

                return $event->getResult();

            case GroupAndSortingInformationInterface::GROUP_YEAR:
                if ($value instanceof \DateTime) {
                    $value = $value->getTimestamp();
                }

                return ($value != '') ? date('Y', $value) : '-';

            default:
                return null;
        }
    }
}
