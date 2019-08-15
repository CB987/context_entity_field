<?php
namespace Drupal\context_entity_field\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Entity Field' condition.
 *
 * @Condition(
 *   id = "entity_field",
 *   deriver = "\Drupal\context_entity_field\Plugin\Deriver\EntityFieldDeriver"
 * )
 */
class EntityFieldCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected $entityType;

  /**
   * Creates a new EntityField instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityType = $entity_type_manager->getDefinition($this->getDerivativeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $fields = \Drupal::service('entity_field.manager')->getFieldMap();
    $fields_name = array_keys($fields[$this->entityType->id()]);

    $fields_name = array_combine($fields_name, $fields_name);
    asort($fields_name);

    $form['field_name'] = [
      '#title' => $this->t('Field name'),
      '#type' => 'select',
      '#options' => $fields_name,
      '#description' => $this->t('Select @bundle_type field to check.', ['@bundle_type' => $this->entityType->getBundleLabel()]),
      '#default_value' => $this->configuration['field_name'],
    ];

    $form['field_state'] = [
      '#title' => $this->t('Field state'),
      '#type' => 'select',
      '#options' => [
        'filled'   => $this->t('Filled'),
        'empty' => $this->t('Empty'),
        'value' => $this->t('Value is'),
      ],
      '#description' => $this->t('State of field to evaluate.'),
      '#default_value' => $this->configuration['field_state'],
    ];

    $form['field_value'] = [
      '#title' => $this->t('Field value'),
      '#type' => 'textfield',
      '#description' => $this->t('Value to compare against.'),
      '#default_value' => $this->configuration['field_value'],
      '#states' => [
        'visible' => [
          ':input[name*="field_state"]' => array('value' => 'value'),
        ],
      ],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getContextValue($this->entityType->id());

    if ($entity && $entity->hasField($this->configuration['field_name'])) {
      $is_empty = $entity->get($this->configuration['field_name'])->isEmpty();

      // Field value is empty.
      if ($this->configuration['field_state'] == 'empty' && $is_empty) {
        return TRUE;
      }

      // Field value is not empty.
      if ($this->configuration['field_state'] == 'filled' && !$is_empty) {
        return TRUE;
      }

      // Field value matches given value.
      if ($this->configuration['field_state'] == 'value' && !$is_empty) {
        // Control value in available values.
        foreach ($entity->get($this->configuration['field_name']) as $item) {
          if ($item->getString() === $this->configuration['field_value']) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('@bundle_type field', ['@bundle_type' => $this->entityType->getBundleLabel()]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'field_name' => '',
      'field_state' => 'filled',
      'field_value' => '',
    ];
  }

}
