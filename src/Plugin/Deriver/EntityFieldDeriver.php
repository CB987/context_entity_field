<?php

namespace Drupal\context_entity_field\Plugin\Deriver;

use Drupal\ctools\Plugin\Deriver\EntityBundle;

/**
 * Deriver that creates a condition for each entity type with bundles.
 */
class EntityFieldDeriver extends EntityBundle {

  /**
   * Provides the bundle label with a fallback when not defined.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type we are looking the bundle label for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The entity bundle label or a fallback label.
   */
  protected function getEntityBundleLabel($entity_type) {

    if ($label = $entity_type->getBundleLabel()) {
      return $this->t('@label field', ['@label' => $label]);
    }

    $fallback = $entity_type->getLabel();
    if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
      // This is a better fallback.
      $fallback = $this->entityManager->getDefinition($bundle_entity_type)->getLabel();
    }

    return $this->t('@label bundle field', ['@label' => $fallback]);

  }

}
