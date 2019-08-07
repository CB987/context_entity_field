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
 *   deriver = "\Drupal\context_entity_field\Plugin\Deriver\EntityField"
 * )
 */
class EntityField extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected $bundleOf;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->bundleOf = $entity_type_manager->getDefinition($this->getDerivativeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $fields = \Drupal::service('entity_field.manager')->getFieldMap();
    $fields_name = array_keys($fields[$this->bundleOf->id()]);

    $fields_name = array_combine($fields_name, $fields_name);
    asort($fields_name);

    $form['field_name'] = [
      '#title' => $this->t('Field name'),
      '#type' => 'select',
      '#options' => $fields_name,
      '#description' => $this->t('Select @bundle_type field to check', ['@bundle_type' => $this->bundleOf->getBundleLabel()]),
      '#default_value' => $this->configuration['field_name'],
    ];

    $form['field_status'] = [
      '#title' => $this->t('Field status'),
      '#type' => 'select',
      '#options' => [
        'all'   => $this->t('All values'),
        'empty' => $this->t('Empty value'),
        'match' => $this->t('Match'),
      ],
      '#description' => t('Status of field to evaluate.'),
      '#default_value' => $this->configuration['field_status'],
    ];

    $form['field_value'] = [
      '#title' => $this->t('Field value'),
      '#type' => 'textfield',
      '#description' => $this->t('Write the entity field value to compare'),
      '#default_value' => $this->configuration['field_value'],
      '#states' => [
        'visible' => [
          ':input[name*="field_status"]' => array('value' => 'match'),
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
    $entity = $this->getContextValue($this->bundleOf->id());

    if ($entity && $entity->hasField($this->configuration['field_name'])) {
      $is_empty = $entity->get($this->configuration['field_name'])->isEmpty();

      // Field value is empty.
      if ($this->configuration['field_status'] == 'empty' && $is_empty) {
        return TRUE;
      }

      // Field value is not empty.
      if ($this->configuration['field_status'] == 'all' && !$is_empty) {
        return TRUE;
      }

      // Field value match.
      if ($this->configuration['field_status'] == 'match' && !$is_empty) {
        // Control value in available values.
        foreach ($entity->get($this->configuration['field_name']) as $item) {
          if ($item->getString() == $this->configuration['field_value']) {
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
    return $this->t('@bundle_type field', ['@bundle_type' => $this->bundleOf->getBundleLabel()]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'field_name' => '',
      'field_status' => 'all',
      'field_value' => '',
    ];
  }

}
